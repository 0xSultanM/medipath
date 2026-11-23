<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required', 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->ignore($target?->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}