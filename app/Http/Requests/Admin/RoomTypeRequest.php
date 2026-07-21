<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomType = $this->route('room_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('room_types', 'slug')->ignore($roomType),
            ],
            'description' => ['nullable', 'string'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', new Enum(Currency::class)],
            'max_occupancy' => ['required', 'integer', 'min:1'],
        ];
    }

    public function validatedForModel(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'base_rate_cents' => (int) round($data['base_rate'] * 100),
            'currency' => $data['currency'],
            'max_occupancy' => $data['max_occupancy'],
        ];
    }
}
