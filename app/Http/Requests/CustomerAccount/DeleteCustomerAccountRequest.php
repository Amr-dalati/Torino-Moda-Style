<?php

namespace App\Http\Requests\CustomerAccount;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCustomerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:100'],
            'confirmation' => ['required', 'string', 'in:DELETE'],
            'deletion_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'The confirmation text is incorrect.',
        ];
    }
}
