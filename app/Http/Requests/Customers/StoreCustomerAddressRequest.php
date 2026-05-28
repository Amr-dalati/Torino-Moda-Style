<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_area_id' => ['sometimes', 'nullable', 'integer', 'exists:delivery_areas,id'],
            'label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'recipient_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'recipient_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:190'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:190'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'area_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}

