<?php

namespace App\Http\Controllers;

use App\Services\Payments\ThawaniReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Browser return targets after Thawani hosted checkout.
 *
 * Does not update payment state — redirects to the mobile app for status polling.
 */
class ThawaniReturnController extends Controller
{
    public function __construct(
        protected ThawaniReturnService $returns,
    ) {}

    public function success(Request $request): RedirectResponse|View
    {
        return $this->handleReturn(
            request: $request,
            redirectUrl: fn (?int $orderId) => $this->returns->mobileSuccessRedirectUrl($orderId),
            fallbackTitle: 'Payment completed',
            logContext: 'success',
        );
    }

    public function cancel(Request $request): RedirectResponse|View
    {
        return $this->handleReturn(
            request: $request,
            redirectUrl: fn (?int $orderId) => $this->returns->mobileCancelRedirectUrl($orderId),
            fallbackTitle: 'Payment cancelled',
            logContext: 'cancel',
        );
    }

    /**
     * @param  callable(?int): (?string)  $redirectUrl
     */
    protected function handleReturn(
        Request $request,
        callable $redirectUrl,
        string $fallbackTitle,
        string $logContext,
    ): RedirectResponse|View {
        if ($request->filled('redirect') || $request->filled('return_url') || $request->filled('next')) {
            Log::warning('Thawani return rejected open-redirect attempt', [
                'context' => $logContext,
                'ip' => $request->ip(),
            ]);
        }

        $orderId = $this->returns->resolveOrderId($request);
        $mobileUrl = $redirectUrl($orderId);

        if ($mobileUrl === null) {
            Log::warning('Thawani return using web fallback', [
                'context' => $logContext,
                'order_resolved' => $orderId !== null,
            ]);

            return view('payments.thawani-return', [
                'title' => $fallbackTitle,
            ]);
        }

        if ($orderId === null) {
            Log::warning('Thawani return could not resolve order', [
                'context' => $logContext,
                'has_session_id' => $request->has('session_id'),
                'has_client_reference_id' => $request->has('client_reference_id'),
            ]);
        }

        return redirect()->away($mobileUrl);
    }
}
