<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lecturerId = $this->route('lecturer')?->id;

        return [
            'name' => 'required|string|max:255',
            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('lecturers', 'nip')->ignore($lecturerId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('lecturers', 'email')->ignore($lecturerId),
            ],
            'phone' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama dosen wajib diisi.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'email.unique' => 'Email sudah terdaftar.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
