<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_format_and_parse_round_trip(): void
    {
        $this->assertSame('0.00', Money::format(Money::cents('0')));
        $this->assertSame('10.00', Money::format(Money::cents('10')));
        $this->assertSame('10.50', Money::format(Money::cents('10.5')));
        $this->assertSame('10.05', Money::format(Money::cents('10.05')));
        $this->assertSame('10.06', Money::format(Money::cents('10.055'))); // half-up
    }

    public function test_multiplication_is_deterministic(): void
    {
        $this->assertSame('19.98', Money::mul('9.99', 2));
        $this->assertSame('0.00', Money::mul('0.00', 999));
    }
}

