<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class ManualDialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
