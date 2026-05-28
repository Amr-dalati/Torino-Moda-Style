<?php

namespace App\Filament\Resources\PaymentWebhookResource\Pages;

use App\Filament\Resources\PaymentWebhookResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentWebhooks extends ListRecords
{
    protected static string $resource = PaymentWebhookResource::class;
}

