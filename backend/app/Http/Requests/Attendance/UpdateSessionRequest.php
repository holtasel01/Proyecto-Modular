<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendanceSession'));
    }

    public function rules(): array
    {
        return [
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after:started_at'],
            'status' => ['sometimes', Rule::in(['open', 'closed', 'inconsistent'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
