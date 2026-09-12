<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\UpdateIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Incident::class);

        $incidents = Incident::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('created_at')
            ->paginate(20);

        return IncidentResource::collection($incidents);
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): IncidentResource
    {
        $incident->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'description' => trim(($incident->description ?? '')."\n\nResolución: ".$request->string('resolution_note')),
        ]);

        return new IncidentResource($incident->fresh());
    }
}
