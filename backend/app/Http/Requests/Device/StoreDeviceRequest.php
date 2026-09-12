<?php

namespace App\Http\Requests\Device;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Device::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
        ];
    }
}
