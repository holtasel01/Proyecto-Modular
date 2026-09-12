<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LowConfidenceRecognitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecognitionEventRequest;
use App\Http\Resources\AttendanceEventResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

/**
 * La llama recognition-app autenticada como device (auth:sanctum +
 * abilities:attendance:write, ver routes/api.php). docs/02-diseno.md §5 y §6.2.
 */
class AttendanceEventController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function store(RecognitionEventRequest $request): JsonResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));

        if ($device = $request->user()) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        try {
            $result = $this->attendanceService->handleRecognitionEvent(
                student: $student,
                confidence: (float) $request->input('confidence'),
                device: $device,
            );
        } catch (LowConfidenceRecognitionException $e) {
            abort(422, $e->getMessage());
        }

        if ($result['status'] === 'duplicate_ignored') {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        return response()->json([
            'status' => 'created',
            'event' => new AttendanceEventResource($result['event']),
            'session' => new AttendanceSessionResource($result['session']),
        ], 201);
    }
}
