<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $doctor = $this->route('doctor');
        $user = $doctor?->user;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$doctor ? 'nullable' : 'required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
            'specialty_id' => ['required', 'integer', Rule::exists('specialties', 'id')->where('is_active', true)],
            'license_number' => ['required', 'string', 'max:80', Rule::unique('doctors', 'license_number')->ignore($doctor?->id)],
            'license_authority' => ['required', 'string', 'max:160'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'clinic_name' => ['nullable', 'string', 'max:160'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:40'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}