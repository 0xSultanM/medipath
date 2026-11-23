<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = (string) $this->input('role');

        return [
            'role' => ['required', Rule::in(['patient', 'doctor', 'pharmacist'])],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,32}$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
            'terms' => ['required', 'accepted'],
            'claim_code' => ['nullable', 'string', 'max:32'],

            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', Rule::in(config('medipath.registration.genders'))],
            'condition' => ['nullable', 'string', 'max:120'],

            'license_number' => [
                Rule::requiredIf(in_array($role, ['doctor', 'pharmacist'], true)),
                'nullable',
                'string',
                'max:80',
                $role === 'doctor'
                    ? Rule::unique('doctors', 'license_number')
                    : Rule::unique('pharmacists', 'license_number'),
            ],
            'license_authority' => [
                Rule::requiredIf(in_array($role, ['doctor', 'pharmacist'], true)),
                'nullable',
                Rule::in(config('medipath.registration.license_authorities')),
            ],
            'specialty_id' => [
                Rule::requiredIf($role === 'doctor'),
                'nullable',
                'integer',
                Rule::exists('specialties', 'id')->where('is_active', true),
            ],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'credential' => [
                Rule::requiredIf(in_array($role, ['doctor', 'pharmacist'], true)),
                'nullable',
                'file',
                'mimes:'.implode(',', config('medipath.uploads.credential_mimes')),
                'max:'.config('medipath.uploads.credential_max_kb'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please accept the Terms and the Health Data Privacy Notice.',
            'credential.max' => 'The license document must be 5 MB or smaller.',
        ];
    }
}
