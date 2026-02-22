<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class VicidialCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vicidial_user' => ['nullable', 'string'],
            'vicidial_pass' => ['nullable', 'string'],
            'vicidial_phone_login' => ['nullable', 'string'],
            'vicidial_phone_pass' => ['nullable', 'string'],
        ];
    }
}
