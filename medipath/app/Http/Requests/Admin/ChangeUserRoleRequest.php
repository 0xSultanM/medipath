<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = (string) $this->input('role');

        return [
            'role' => ['required', Rule::in(['patient', 'doctor', 'pharmacist', 'admin'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'license_number' => [Rule::requiredIf(in_array($role, ['doctor', 'pharmacist'], true)), 'nullable', 'string', 'max:80'],
            'license_authority' => [Rule::requiredIf(in_array($role, ['doctor', 'pharmacist'], true)), 'nullable', 'string', 'max:160'],
            'specialty_id' => [Rule::requiredIf($role === 'doctor'), 'nullable', 'integer', Rule::exists('specialties', 'id')->where('is_active', true)],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notify' => ['sometimes', 'boolean'],
        ];
    }
}