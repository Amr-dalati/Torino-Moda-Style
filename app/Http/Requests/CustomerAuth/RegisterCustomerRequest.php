<?php

namespace App\Http\Requests\CustomerAuth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:customers,email'],
            'phone' => ['required', 'string', 'min:6', 'max:30', 'unique:customers,phone'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}

