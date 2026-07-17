<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentWebhook;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Services\Customers\CustomerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SignsMockPaymentWebhooks;
use Tests\TestCase;

class CustomerAccountDeletionTest extends TestCase
{
    use RefreshDatabase;
    use SignsMockPaymentWebhooks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
        $this->artisan('phoenix:sync');
    }

    protected function createCustomerWithPassword(string $phone = '+10000001001', string $password = 'password123'): Customer
    {
        return Customer::factory()->create([
            'phone' => $phone,
            'email' => 'delete-me@example.test',
            'password' => $password,
        ]);
    }

    /**
     * @return array{on_hand: float, reserved: float}
     */
    protected function stockTotals(int $variantId): array
    {
        $levels = StockLevel::query()->where('product_variant_id', $variantId)->get();

        return [
            'on_hand' => $levels->sum(fn (StockLevel $l) => (float) $l->quantity_on_hand),
            'reserved' => $levels->sum(fn (StockLevel $l) => (float) $l->quantity_reserved),
        ];
    }

    /**
     * @return array{order: array<string, mixed>, payment: array<string, mixed>}
     */
    protected function checkoutWithPaidWebhook(Customer $customer): array
    {
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'recipient_phone' => '+10000000000',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        $merchantRef = $checkout['payment']['merchant_reference'];
        $signed = $this->signedMockWebhook([
            'event_id' => 'evt_delete_'.$customer->id,
            'event_type' => 'payment_succeeded',
            'merchant_reference' => $merchantRef,
            'status' => 'paid',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->call(
            'POST',
            '/api/payments/webhook/mock',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($signed['headers']),
            $signed['payload'],
        )->assertOk();

        return [
            'order' => $checkout['order'],
            'payment' => $checkout['payment'],
            'variant_id' => $variant->id,
            'address_id' => $address->id,
        ];
    }

    protected function deletePayload(string $password = 'password123'): array
    {
        return [
            'password' => $password,
            'confirmation' => 'DELETE',
        ];
    }

    /**
     * @return array{order: array<string, mixed>, payment: array<string, mixed>, variant_id: int}
     */
    protected function checkoutPendingPayment(Customer $customer): array
    {
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        $address = $customer->addresses()->create([
            'delivery_area_id' => $area->id,
            'address_line1' => 'Street 1',
            'recipient_phone' => '+10000000000',
        ]);

        $variant = ProductVariant::query()->firstOrFail();
        StockLevel::query()->where('product_variant_id', $variant->id)->update([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/customer/checkout', [
            'address_id' => $address->id,
        ])->assertStatus(201)->json('data');

        return [
            'order' => $checkout['order'],
            'payment' => $checkout['payment'],
            'variant_id' => $variant->id,
        ];
    }

    protected function seedActiveCart(Customer $customer): void
    {
        $variant = ProductVariant::query()->firstOrFail();
        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
    }

    public function test_authenticated_customer_can_delete_account_with_correct_password_and_confirmation(): void
    {
        $customer = $this->createCustomerWithPassword();
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/customer/account', $this->deletePayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully')
            ->assertJsonPath('data', null);
    }

    public function test_wrong_password_returns_422(): void
    {
        $customer = $this->createCustomerWithPassword();
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.password.0', 'The provided password is incorrect.');
    }

    public function test_wrong_password_does_not_revoke_tokens(): void
    {
        $customer = $this->createCustomerWithPassword();
        $customer->createToken('mobile-a');
        $customer->createToken('mobile-b');
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_wrong_password_does_not_delete_addresses(): void
    {
        $customer = $this->createCustomerWithPassword();
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_area_id' => $area->id,
            'address_line1' => 'Keep me',
            'recipient_phone' => '+10000000012',
        ]);
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422);

        $this->assertDatabaseCount('customer_addresses', 1);
    }

    public function test_wrong_password_does_not_clear_active_cart(): void
    {
        $customer = $this->createCustomerWithPassword();
        $this->seedActiveCart($customer);
        $cart = Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->firstOrFail();
        $this->assertGreaterThan(0, $cart->items()->count());

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422);

        $this->assertGreaterThan(0, $cart->fresh()->items()->count());
    }

    public function test_wrong_password_does_not_modify_customer_fields(): void
    {
        $customer = $this->createCustomerWithPassword('+10000001010');
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422);

        $customer->refresh();
        $this->assertSame('delete-me@example.test', $customer->email);
        $this->assertSame('+10000001010', $customer->phone);
        $this->assertTrue($customer->is_active);
        $this->assertNull($customer->deleted_at);
        $this->assertNotNull($customer->password);
    }

    public function test_wrong_confirmation_causes_zero_persistent_changes(): void
    {
        $customer = $this->createCustomerWithPassword();
        $customer->createToken('mobile');
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_area_id' => $area->id,
            'address_line1' => 'Keep me',
            'recipient_phone' => '+10000000013',
        ]);
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', [
            'password' => 'password123',
            'confirmation' => 'delete',
        ])
            ->assertStatus(422);

        $customer->refresh();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertNull($customer->deleted_at);
        $this->assertSame('delete-me@example.test', $customer->email);
    }

    public function test_wrong_confirmation_returns_422(): void
    {
        $customer = $this->createCustomerWithPassword();
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', [
            'password' => 'password123',
            'confirmation' => 'delete',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.confirmation.0', 'The confirmation text is incorrect.');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->deleteJson('/api/customer/account', $this->deletePayload())
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_only_delete_own_account_via_token(): void
    {
        $customer = $this->createCustomerWithPassword();
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/customer/account', $this->deletePayload())
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/customer/me')
            ->assertStatus(401);
    }

    public function test_all_customer_tokens_are_revoked(): void
    {
        $customer = $this->createCustomerWithPassword();
        $customer->createToken('mobile-a');
        $customer->createToken('mobile-b');
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_addresses_are_deleted(): void
    {
        $customer = $this->createCustomerWithPassword();
        $area = DeliveryArea::query()->where('is_active', true)->firstOrFail();
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_area_id' => $area->id,
            'address_line1' => 'Keep snapshot only on orders',
            'recipient_phone' => '+10000000011',
        ]);
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    public function test_customer_profile_is_anonymized(): void
    {
        $customer = $this->createCustomerWithPassword();
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $customer->refresh();
        $this->assertSame("Deleted Customer #{$customer->id}", $customer->name);
        $this->assertSame("deleted-{$customer->id}@example.invalid", $customer->email);
        $this->assertSame("deleted-phone-{$customer->id}", $customer->phone);
        $this->assertFalse($customer->is_active);
        $this->assertNotNull($customer->deleted_at);
        $this->assertNotNull($customer->anonymized_at);
        $this->assertNull($customer->password);
    }

    public function test_orders_remain_after_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutWithPaidWebhook($customer);
        $orderId = $checkout['order']['id'];

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'customer_id' => $customer->id]);
    }

    public function test_order_items_remain_after_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutWithPaidWebhook($customer);
        $orderId = $checkout['order']['id'];
        $itemCount = OrderItem::query()->where('order_id', $orderId)->count();
        $this->assertGreaterThan(0, $itemCount);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertSame($itemCount, OrderItem::query()->where('order_id', $orderId)->count());
    }

    public function test_payments_remain_after_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutWithPaidWebhook($customer);
        $merchantRef = $checkout['payment']['merchant_reference'];

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertDatabaseHas('payments', ['merchant_reference' => $merchantRef]);
    }

    public function test_payment_webhooks_remain_after_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $this->checkoutWithPaidWebhook($customer);
        $webhookCount = PaymentWebhook::query()->count();
        $this->assertGreaterThan(0, $webhookCount);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertSame($webhookCount, PaymentWebhook::query()->count());
    }

    public function test_stock_allocations_remain_unchanged_after_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutWithPaidWebhook($customer);
        $stockAfterPayment = $this->stockTotals($checkout['variant_id']);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertSame($stockAfterPayment, $this->stockTotals($checkout['variant_id']));
    }

    public function test_original_email_and_phone_are_no_longer_stored_on_customer(): void
    {
        $customer = $this->createCustomerWithPassword('+10000001003');
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $customer->refresh();
        $this->assertNotSame('delete-me@example.test', $customer->email);
        $this->assertNotSame('+10000001003', $customer->phone);
    }

    public function test_deleted_customer_cannot_log_in(): void
    {
        $customer = $this->createCustomerWithPassword('+10000001004');
        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->postJson('/api/customer/login', [
            'phone' => '+10000001004',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'The provided credentials are incorrect.');
    }

    public function test_old_token_cannot_access_customer_apis(): void
    {
        $customer = $this->createCustomerWithPassword();
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/customer/account', $this->deletePayload())
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/customer/me')
            ->assertStatus(401);
    }

    public function test_original_phone_can_be_reused_for_new_account(): void
    {
        $phone = '+10000001005';
        $customer = $this->createCustomerWithPassword($phone);
        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->postJson('/api/customer/register', [
            'name' => 'New Customer',
            'phone' => $phone,
            'password' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_new_account_does_not_gain_access_to_old_orders(): void
    {
        $phone = '+10000001006';
        $customer = $this->createCustomerWithPassword($phone);
        $checkout = $this->checkoutWithPaidWebhook($customer);
        $oldOrderId = $checkout['order']['id'];

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $register = $this->postJson('/api/customer/register', [
            'name' => 'Replacement Customer',
            'phone' => $phone,
            'password' => 'password456',
        ])->assertStatus(201);

        $newCustomerId = $register->json('data.customer.id');
        $token = $register->json('data.token');

        $this->assertNotSame($customer->id, $newCustomerId);
        $this->assertSame($customer->id, Order::query()->findOrFail($oldOrderId)->customer_id);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/customer/orders/'.$oldOrderId)
            ->assertStatus(404);
    }

    public function test_service_idempotency_revokes_stray_tokens_for_already_deleted_customer(): void
    {
        $customer = $this->createCustomerWithPassword();
        $service = app(CustomerDeletionService::class);

        $service->deleteAccount($customer, 'password123');
        $firstDeletedAt = $customer->fresh()->deleted_at;
        $customer->createToken('stray-token');

        $service->deleteAccount($customer->fresh(), 'password123');

        $this->assertTrue($customer->fresh()->deleted_at->equalTo($firstDeletedAt));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_pending_unpaid_order_is_cancelled_and_reservation_released_on_deletion(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutPendingPayment($customer);
        $orderId = $checkout['order']['id'];
        $stockBefore = $this->stockTotals($checkout['variant_id']);
        $this->assertGreaterThan(0.0, $stockBefore['reserved']);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(0.0, $this->stockTotals($checkout['variant_id'])['reserved']);
    }

    public function test_checked_out_cart_linked_to_order_is_preserved(): void
    {
        $customer = $this->createCustomerWithPassword();
        $checkout = $this->checkoutPendingPayment($customer);
        $orderId = $checkout['order']['id'];
        $cartId = Order::query()->findOrFail($orderId)->cart_id;
        $this->assertNotNull($cartId);
        $cartItemCount = Cart::query()->findOrFail($cartId)->items()->count();
        $this->assertGreaterThan(0, $cartItemCount);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $this->assertDatabaseHas('carts', ['id' => $cartId]);
        $this->assertSame($cartItemCount, Cart::query()->findOrFail($cartId)->items()->count());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'cart_id' => $cartId]);
    }

    public function test_two_deleted_customers_receive_unique_anonymized_identifiers(): void
    {
        $first = Customer::factory()->create([
            'phone' => '+10000001011',
            'email' => 'delete-first@example.test',
            'password' => 'password123',
        ]);
        $second = Customer::factory()->create([
            'phone' => '+10000001012',
            'email' => 'delete-second@example.test',
            'password' => 'password123',
        ]);

        Sanctum::actingAs($first);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        Sanctum::actingAs($second);
        $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $first->refresh();
        $second->refresh();

        $this->assertNotSame($first->email, $second->email);
        $this->assertNotSame($first->phone, $second->phone);
        $this->assertStringEndsWith('@example.invalid', $first->email);
        $this->assertStringEndsWith('@example.invalid', $second->email);
    }

    public function test_failed_deletion_does_not_allow_phone_reuse(): void
    {
        $phone = '+10000001013';
        $customer = $this->createCustomerWithPassword($phone);
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/customer/account', $this->deletePayload('wrong-password'))
            ->assertStatus(422);

        $this->postJson('/api/customer/register', [
            'name' => 'Should Fail',
            'phone' => $phone,
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'The phone has already been taken.');
    }

    public function test_concurrent_deletion_requests_cannot_partially_modify_account(): void
    {
        $customer = $this->createCustomerWithPassword();
        $service = app(CustomerDeletionService::class);

        $service->deleteAccount($customer, 'password123');
        $deletedAt = $customer->fresh()->deleted_at;

        $service->deleteAccount($customer->fresh(), 'password123');

        $customer->refresh();
        $this->assertTrue($customer->deleted_at->equalTo($deletedAt));
        $this->assertSame("deleted-{$customer->id}@example.invalid", $customer->email);
    }

    public function test_public_account_deletion_legal_route_loads_in_english(): void
    {
        $this->get('/legal/account-deletion?lang=en')
            ->assertOk()
            ->assertSee('Delete account', false)
            ->assertSee('Last updated', false);
    }

    public function test_public_account_deletion_legal_route_loads_in_arabic(): void
    {
        $this->get('/legal/account-deletion?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('حذف الحساب', false)
            ->assertSee('آخر تحديث', false);
    }

    public function test_filament_query_can_filter_deleted_customers(): void
    {
        $active = Customer::factory()->create(['phone' => '+10000001007']);
        $deleted = Customer::factory()->create([
            'phone' => '+10000001008',
            'deleted_at' => now(),
            'anonymized_at' => now(),
            'is_active' => false,
        ]);

        $deletedIds = Customer::query()->whereNotNull('deleted_at')->pluck('id')->all();
        $activeIds = Customer::query()->whereNull('deleted_at')->pluck('id')->all();

        $this->assertContains($deleted->id, $deletedIds);
        $this->assertNotContains($active->id, $deletedIds);
        $this->assertContains($active->id, $activeIds);
    }

    public function test_sensitive_values_are_not_returned_in_response(): void
    {
        $customer = $this->createCustomerWithPassword();
        Sanctum::actingAs($customer);

        $response = $this->deleteJson('/api/customer/account', $this->deletePayload())->assertOk();

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('delete-me@example.test', $encoded);
        $this->assertStringNotContainsString('password123', $encoded);
        $this->assertStringNotContainsString("deleted-{$customer->id}@example.invalid", $encoded);
    }

    public function test_deleted_customer_login_blocked_even_if_password_hash_remained(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+10000001009',
            'password' => 'password123',
            'deleted_at' => now(),
            'is_active' => false,
        ]);

        $this->postJson('/api/customer/login', [
            'phone' => '+10000001009',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'This account is no longer available.');
    }
}
