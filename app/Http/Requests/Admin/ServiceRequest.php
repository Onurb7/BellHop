<?php

namespace App\Http\Requests\Admin;

use App\Enums\ServicePricingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('services', 'slug')->ignore($service),
            ],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'pricing_type' => ['required', new Enum(ServicePricingType::class)],
            'active' => ['boolean'],
            'images' => ['array'],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['array'],
            'remove_images.*' => ['integer'],
        ];
    }

    public function validatedForModel(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'unit_price_cents' => (int) round($data['unit_price'] * 100),
            'pricing_type' => $data['pricing_type'],
            'active' => $data['active'] ?? true,
        ];
    }
}
