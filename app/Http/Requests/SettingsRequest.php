<?php

namespace App\Http\Requests;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Enums\WeekStart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_format' => ['required', new Enum(DateFormat::class)],
            'time_format' => ['required', new Enum(TimeFormat::class)],
            'week_start' => ['required', new Enum(WeekStart::class)],
        ];
    }
}
