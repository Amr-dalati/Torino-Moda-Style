<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Checkout\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Expire pending payments past expires_at and release stock reservations';

    public function handle(PaymentService $payments): int
    {
        $expired = 0;
        $skipped = 0;

        Payment::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($payments, &$expired, &$skipped) {
                foreach ($rows as $payment) {
                    try {
                        $payments->markPaymentExpired($payment->merchant_reference);
                        $expired++;
                    } catch (ValidationException) {
                        $skipped++;
                    }
                }
            });

        $this->info("Expired {$expired} pending payment(s). Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
