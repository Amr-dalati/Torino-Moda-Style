<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    public static function legalRoutesProvider(): array
    {
        return [
            ['/legal/privacy'],
            ['/legal/terms'],
            ['/legal/returns'],
            ['/legal/shipping'],
            ['/legal/contact'],
            ['/legal/account-deletion'],
        ];
    }

    /**
     * @dataProvider legalRoutesProvider
     */
    public function test_legal_routes_load_in_english(string $path): void
    {
        $this->get("{$path}?lang=en")
            ->assertOk()
            ->assertSee('Last updated', false);
    }

    /**
     * @dataProvider legalRoutesProvider
     */
    public function test_legal_routes_load_in_arabic(string $path): void
    {
        $response = $this->get("{$path}?lang=ar");

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('آخر تحديث', false);
    }
}
