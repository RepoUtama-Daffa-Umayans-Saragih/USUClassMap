<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $buildingId = $this->route('building')?->id;

        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('buildings', 'code')->ignore($buildingId),
            ],
            'faculty' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string',
            'floor_count' => 'required|integer|min:1|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gedung wajib diisi.',
            'code.required' => 'Kode gedung wajib diisi.',
            'code.unique' => 'Kode gedung sudah terdaftar.',
            'latitude.required' => 'Latitude wajib diisi.',
            'latitude.numeric' => 'Latitude harus berupa angka.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.numeric' => 'Longitude harus berupa angka.',
            'floor_count.required' => 'Jumlah lantai wajib diisi.',
            'floor_count.min' => 'Jumlah lantai minimal 1.',
        ];
    }
}
