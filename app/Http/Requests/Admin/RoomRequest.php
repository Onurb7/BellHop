<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'number' => [
                'required', 'string', 'max:50',
                Rule::unique('rooms', 'number')->ignore($room),
            ],
            'floor' => ['nullable', 'string', 'max:50'],
            'status' => ['required', new Enum(RoomStatus::class)],
            'is_published' => ['boolean'],
            'amenities' => ['array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'images' => ['array'],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['array'],
            'remove_images.*' => ['integer'],
        ];
    }
}
