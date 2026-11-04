<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:50',
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1|max:500',
            'photo_360' => 'nullable|image|mimes:jpeg,jpg,png|max:15360',
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'Gedung wajib dipilih.',
            'building_id.exists' => 'Gedung tidak valid.',
            'name.required' => 'Nama ruangan wajib diisi.',
            'floor.required' => 'Lantai wajib diisi.',
            'floor.min' => 'Lantai minimal 1.',
            'capacity.required' => 'Kapasitas wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1.',
            'photo_360.image' => 'File harus berupa gambar.',
            'photo_360.mimes' => 'Format gambar harus JPEG atau PNG.',
            'photo_360.max' => 'Ukuran foto maksimal 15MB.',
        ];
    }
}
