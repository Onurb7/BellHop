<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promoCode = $this->route('promoCode');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('promo_codes', 'code')->ignore($promoCode),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'percentage' => ['required', 'integer', 'between:1,100'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['boolean'],
        ];
    }

    public function validatedForModel(): array
    {
        $data = $this->validated();

        return [
            'code' => strtoupper(trim($data['code'])),
            'description' => $data['description'] ?? null,
            'percentage' => $data['percentage'],
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => $data['active'] ?? true,
        ];
    }
}
