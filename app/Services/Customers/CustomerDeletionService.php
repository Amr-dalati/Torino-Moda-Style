<?php

namespace App\Services\Customers;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Admin\OrderFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerDeletionService
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillment,
    ) {}

    /**
     * Permanently anonymize a customer account while preserving financial records.
     *
     * Service-level idempotency: if the customer is already deleted, stray tokens are
     * revoked and the anonymized record is returned without re-running mutations.
     * The public API is not idempotent after success because tokens are revoked.
     */
    public function deleteAccount(
        Customer $customer,
        string $password,
        ?string $deletionReason = null,
    ): Customer {
        return DB::transaction(function () use ($customer, $password, $deletionReason) {
            /** @var Customer $locked */
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->isDeleted()) {
                $locked->tokens()->delete();

                return $locked->fresh();
            }

            if (! $locked->password || ! Hash::check($password, $locked->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided password is incorrect.'],
                ]);
            }

            $this->cancelOpenUnpaidOrders($locked);

            $locked->addresses()->delete();

            $orderLinkedCartIds = Order::query()
                ->where('customer_id', $locked->id)
                ->whereNotNull('cart_id')
                ->pluck('cart_id');

            Cart::query()
                ->where('customer_id', $locked->id)
                ->where('status', 'active')
                ->when($orderLinkedCartIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $orderLinkedCartIds))
                ->each(function (Cart $cart): void {
                    $cart->items()->delete();
                    $cart->forceFill(['subtotal' => '0.00'])->save();
                });

            $now = now();
            $locked->forceFill([
                'name' => "Deleted Customer #{$locked->id}",
                'email' => "deleted-{$locked->id}@example.invalid",
                'phone' => "deleted-phone-{$locked->id}",
                'password' => null,
                'phoenix_id' => null,
                'is_active' => false,
                'last_login_at' => null,
                'deletion_requested_at' => $now,
                'deleted_at' => $now,
                'anonymized_at' => $now,
                'deletion_reason' => $deletionReason,
            ])->save();

            $locked->tokens()->delete();

            return $locked->fresh();
        });
    }

    protected function cancelOpenUnpaidOrders(Customer $customer): void
    {
        Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_status', '!=', 'paid')
            ->whereNull('stock_committed_at')
            ->whereNotIn('order_status', ['cancelled', 'shipped', 'delivered'])
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $orderId): void {
                $this->orderFulfillment->cancelUnpaid($orderId);
            });
    }
}
