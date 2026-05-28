<?php

namespace App\Services\Delivery;

use App\Models\DeliveryArea;
use App\Models\DeliveryRegion;

class DeliveryQueryService
{
    /**
     * @return list<DeliveryRegion>
     */
    public function regions(): array
    {
        return DeliveryRegion::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<DeliveryArea>
     */
    public function areas(?int $regionId = null): array
    {
        return DeliveryArea::query()
            ->when($regionId, fn ($q) => $q->where('delivery_region_id', $regionId))
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->all();
    }
}

