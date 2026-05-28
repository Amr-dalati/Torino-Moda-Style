<?php

namespace Tests\Unit\Support;

use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_redacts_nested_keys_and_headers(): void
    {
        $input = [
            'headers' => [
                'Authorization' => 'Bearer secret-token',
                'X-Api-Key' => 'abc123',
            ],
            'password' => 'pw',
            'password_confirmation' => 'pw',
            'customer' => [
                'email' => 'a@b.com',
                'phone' => '01000000000',
                'shipping_address' => [
                    'address_line1' => 'street',
                ],
            ],
            'payment' => [
                'card_number' => '4111111111111111',
                'cvv' => '123',
                'exp_month' => '12',
                'exp_year' => '2030',
            ],
            'non_sensitive' => [
                'provider' => 'mock',
                'status' => 'paid',
                'amount' => '110.00',
            ],
        ];

        $out = SensitiveDataRedactor::redact($input);

        $this->assertSame('[REDACTED]', $out['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $out['headers']['X-Api-Key']);
        $this->assertSame('[REDACTED]', $out['password']);
        $this->assertSame('[REDACTED]', $out['password_confirmation']);
        $this->assertSame('[REDACTED]', $out['customer']['email']);
        $this->assertSame('[REDACTED]', $out['customer']['phone']);
        $this->assertSame('[REDACTED]', $out['customer']['shipping_address']);
        $this->assertSame('[REDACTED]', $out['payment']['card_number']);
        $this->assertSame('[REDACTED]', $out['payment']['cvv']);
        $this->assertSame('[REDACTED]', $out['payment']['exp_month']);
        $this->assertSame('[REDACTED]', $out['payment']['exp_year']);

        $this->assertSame('mock', $out['non_sensitive']['provider']);
        $this->assertSame('paid', $out['non_sensitive']['status']);
        $this->assertSame('110.00', $out['non_sensitive']['amount']);
    }
}

