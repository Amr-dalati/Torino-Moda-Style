<?php

namespace App\Services\Customers;

use App\Models\Customer;

class CustomerProfileService
{
    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill([
            'name' => $data['name'] ?? $customer->name,
            'email' => $data['email'] ?? $customer->email,
            'phone' => $data['phone'] ?? $customer->phone,
        ]);

        $customer->save();

        return $customer->fresh();
    }
}

