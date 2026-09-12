<?php

namespace App\Services;

use App\Exceptions\LowConfidenceRecognitionException;
use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Device;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * El "cerebro" de la asistencia: decide qué significa un evento de reconocimiento
 * entrante (umbrales de confianza, anti-duplicado, entrada vs. salida) y aplica
 * las reglas de negocio descritas en docs/02-diseno.md §4.
 */
class AttendanceService
{
    private const DUPLICATE_STREAK_THRESHOLD = 3;

    private const DUPLICATE_STREAK_TTL_SECONDS = 300;

    public function __construct(private readonly IncidentService $incidents)
    {
    }

    /**
     * @return array{status: string, event?: AttendanceEvent, session?: AttendanceSession}
     */
    public function handleRecognitionEvent(Student $student, float $confidence, ?Device $device): array
    {
        $minReject = (float) Setting::get('min_confidence_reject', 0.50);
        $minTrust = (float) Setting::get('min_confidence_trust', 0.75);
        $duplicateWindow = (int) Setting::get('duplicate_window_seconds', 30);

        if ($confidence < $minReject) {
            throw new LowConfidenceRecognitionException();
        }

        $lastEvent = AttendanceEvent::query()
            ->where('student_id', $student->id)
            ->orderByDesc('occurred_at')
            ->first();

        if ($lastEvent && $lastEvent->occurred_at->diffInSeconds(now()) < $duplicateWindow) {
            $this->registerDuplicateStreak($student, $lastEvent);

            return ['status' => 'duplicate_ignored'];
        }

        $this->resetDuplicateStreak($student);

        return DB::transaction(function () use ($student, $confidence, $device, $minTrust) {
            $openSession = AttendanceSession::query()
                ->where('student_id', $student->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            $event = AttendanceEvent::query()->create([
                'student_id' => $student->id,
                'device_id' => $device?->id,
                'type' => $openSession ? 'exit' : 'entry',
                'confidence' => $confidence,
                'occurred_at' => now(),
                'source' => 'face_recognition',
            ]);

            $session = $openSession
                ? $this->closeSession($openSession, $event)
                : $this->openSession($student, $event);

            if ($confidence < $minTrust) {
                $this->incidents->create(
                    type: 'baja_confianza',
                    session: $session,
                    event: $event,
                    description: sprintf('Confianza %.4f por debajo del umbral de confianza plena (%.4f).', $confidence, $minTrust),
                );
            }

            return ['status' => 'created', 'event' => $event, 'session' => $session];
        });
    }

    private function openSession(Student $student, AttendanceEvent $entryEvent): AttendanceSession
    {
        return AttendanceSession::query()->create([
            'student_id' => $student->id,
            'entry_event_id' => $entryEvent->id,
            'started_at' => $entryEvent->occurred_at,
            'status' => 'open',
        ]);
    }

    private function closeSession(AttendanceSession $session, AttendanceEvent $exitEvent): AttendanceSession
    {
        $session->update([
            'exit_event_id' => $exitEvent->id,
            'ended_at' => $exitEvent->occurred_at,
            // Carbon 3 (Laravel 11) devuelve diffInMinutes() como float; la columna es integer.
            'duration_minutes' => (int) $session->started_at->diffInMinutes($exitEvent->occurred_at),
            'status' => 'closed',
        ]);

        return $session;
    }

    private function registerDuplicateStreak(Student $student, AttendanceEvent $lastEvent): void
    {
        $key = "attendance.duplicate_streak.{$student->id}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, self::DUPLICATE_STREAK_TTL_SECONDS);

        if ($count >= self::DUPLICATE_STREAK_THRESHOLD) {
            $this->incidents->create(
                type: 'duplicado',
                event: $lastEvent,
                description: "Se recibieron {$count} reconocimientos duplicados seguidos dentro de la ventana anti-duplicado.",
            );
            Cache::forget($key);
        }
    }

    private function resetDuplicateStreak(Student $student): void
    {
        Cache::forget("attendance.duplicate_streak.{$student->id}");
    }
}
