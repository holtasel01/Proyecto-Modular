<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Usada tanto por POST (crear) como PUT (reemplazar) /students/{student}/face-photo.
 * La ability varía según el método: ver docs/02-diseno.md §1 y §7.
 */
class UploadFacePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        $ability = $this->isMethod('put') ? 'replaceFacePhoto' : 'uploadFacePhoto';

        return $this->user()->can($ability, $student);
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png'],
        ];
    }
}
