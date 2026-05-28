<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case SyncedToPhoenix = 'synced_to_phoenix';
    case Cancelled = 'cancelled';
}
