<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $courseId = $this->route('course')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('courses', 'code')->ignore($courseId),
            ],
            'name' => 'required|string|max:255',
            'credits' => 'required|integer|min:1|max:8',
            'type' => 'required|in:MKWK,MKWU,Prodi',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode mata kuliah wajib diisi.',
            'code.unique' => 'Kode mata kuliah sudah terdaftar.',
            'name.required' => 'Nama mata kuliah wajib diisi.',
            'credits.required' => 'SKS wajib diisi.',
            'credits.min' => 'SKS minimal 1.',
            'type.required' => 'Tipe mata kuliah wajib dipilih.',
            'type.in' => 'Tipe mata kuliah tidak valid.',
        ];
    }
}
