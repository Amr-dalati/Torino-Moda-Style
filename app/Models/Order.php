<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'order_status',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'discount_total',
        'total',
        'currency',
        'shipping_label',
        'shipping_recipient_name',
        'shipping_recipient_phone',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_area_name',
        'shipping_postal_code',
        'shipping_delivery_region_code',
        'shipping_delivery_area_code',
        'shipping_delivery_area_id',
        'customer_address_id',
        'cart_id',
        'phoenix_order_id',
        'sync_status',
        'sync_attempts',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class, 'shipping_delivery_area_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

