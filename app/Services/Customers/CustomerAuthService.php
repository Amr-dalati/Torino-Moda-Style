<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerAuthService
{
    /**
     * @return array{customer: Customer, token: string}
     */
    public function register(array $data, string $deviceName = 'mobile'): array
    {
        /** @var Customer $customer */
        $customer = Customer::query()->create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'password' => $data['password'] ?? null,
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        $token = $customer->createToken($deviceName)->plainTextToken;

        return ['customer' => $customer, 'token' => $token];
    }

    /**
     * @return array{customer: Customer, token: string}
     */
    public function login(string $phone, string $password, string $deviceName = 'mobile'): array
    {
        /** @var Customer|null $customer */
        $customer = Customer::query()->where('phone', $phone)->first();

        if (! $customer || ! $customer->password || ! Hash::check($password, $customer->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['This account is inactive.'],
            ]);
        }

        $customer->forceFill(['last_login_at' => now()])->save();

        $token = $customer->createToken($deviceName)->plainTextToken;

        return ['customer' => $customer->fresh(), 'token' => $token];
    }

    public function logout(Customer $customer): void
    {
        // Intelephense can struggle with nullsafe + polymorphic token type here;
        // runtime is correct because currentAccessToken() returns a PersonalAccessToken instance.
        /** @var PersonalAccessToken|null $token */
        $token = $customer->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }
}

