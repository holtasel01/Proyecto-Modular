<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\StoreDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Device::class);

        return DeviceResource::collection(Device::query()->orderBy('nombre')->get());
    }

    /**
     * Crea el device y emite su token de Sanctum con las únicas dos abilities
     * que necesita (docs/02-diseno.md §7). El token en texto plano solo se
     * muestra una vez, en esta respuesta — no se puede recuperar después.
     */
    public function store(StoreDeviceRequest $request): DeviceResource
    {
        $device = Device::query()->create($request->validated());

        $token = $device->createToken('recognition-app', ['sync', 'attendance:write']);

        $device->plain_text_token = $token->plainTextToken;

        return new DeviceResource($device);
    }

    public function destroy(Device $device): JsonResponse
    {
        $this->authorize('delete', Device::class);

        $device->tokens()->delete();
        $device->delete();

        return response()->json(['message' => 'Device revocado.']);
    }
}
