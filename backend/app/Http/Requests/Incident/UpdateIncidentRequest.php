<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('incident'));
    }

    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'max:500'],
        ];
    }
}
