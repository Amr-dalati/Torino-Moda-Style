<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    /**
     * @return list<CustomerAddress>
     */
    public function list(Customer $customer): array
    {
        return $customer->addresses()
            ->with(['deliveryArea.region'])
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function create(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $customer->addresses()->update(['is_default' => false]);
            }

            /** @var CustomerAddress $address */
            $address = $customer->addresses()->create([
                'delivery_area_id' => $data['delivery_area_id'] ?? null,
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'area_name' => $data['area_name'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'is_default' => $isDefault,
            ]);

            return $address->load(['deliveryArea.region']);
        });
    }

    public function update(Customer $customer, int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $addressId, $data) {
            /** @var CustomerAddress $address */
            $address = $customer->addresses()->whereKey($addressId)->firstOrFail();

            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                $customer->addresses()->update(['is_default' => false]);
                $address->is_default = true;
            }

            $address->fill([
                'delivery_area_id' => $data['delivery_area_id'] ?? $address->delivery_area_id,
                'label' => $data['label'] ?? $address->label,
                'recipient_name' => $data['recipient_name'] ?? $address->recipient_name,
                'recipient_phone' => $data['recipient_phone'] ?? $address->recipient_phone,
                'address_line1' => $data['address_line1'] ?? $address->address_line1,
                'address_line2' => $data['address_line2'] ?? $address->address_line2,
                'city' => $data['city'] ?? $address->city,
                'area_name' => $data['area_name'] ?? $address->area_name,
                'postal_code' => $data['postal_code'] ?? $address->postal_code,
            ]);

            $address->save();

            return $address->fresh()->load(['deliveryArea.region']);
        });
    }

    public function delete(Customer $customer, int $addressId): void
    {
        /** @var CustomerAddress $address */
        $address = $customer->addresses()->whereKey($addressId)->firstOrFail();
        $address->delete();
    }

    public function setDefault(Customer $customer, int $addressId): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $addressId) {
            $customer->addresses()->update(['is_default' => false]);

            /** @var CustomerAddress $address */
            $address = $customer->addresses()->whereKey($addressId)->firstOrFail();
            $address->forceFill(['is_default' => true])->save();

            return $address->fresh()->load(['deliveryArea.region']);
        });
    }
}

