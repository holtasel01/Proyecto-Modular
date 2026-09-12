<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Setting::class);

        return SettingResource::collection(Setting::query()->orderBy('key')->get());
    }

    public function update(UpdateSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            Setting::set($key, $value);
        }

        return SettingResource::collection(Setting::query()->orderBy('key')->get());
    }
}
