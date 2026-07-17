<?php

namespace App\Enums;

enum StockAdjustmentType: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Set = 'set';
}
