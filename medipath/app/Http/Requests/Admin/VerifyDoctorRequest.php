<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $decision = (string) $this->input('decision');

        return [
            'decision' => ['required', Rule::in(['approve', 'resubmit', 'revoke'])],
            'reason' => [
                Rule::requiredIf(in_array($decision, ['resubmit', 'revoke'], true)),
                'nullable', 'string', 'min:10', 'max:1000',
            ],
        ];
    }
}