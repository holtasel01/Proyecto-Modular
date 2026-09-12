<?php

namespace App\Http\Requests\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }

    public function rules(): array
    {
        return [
            'min_confidence_reject' => ['sometimes', 'numeric', 'between:0,1'],
            'min_confidence_trust' => ['sometimes', 'numeric', 'between:0,1', 'gte:min_confidence_reject'],
            'duplicate_window_seconds' => ['sometimes', 'integer', 'min:1'],
            'max_session_hours' => ['sometimes', 'integer', 'min:1'],
            'default_horas_meta' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
