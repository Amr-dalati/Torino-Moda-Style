<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\MockPaymentSuccessRequest;
use App\Services\Checkout\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MockPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
    ) {}

    public function success(MockPaymentSuccessRequest $request): JsonResponse
    {
        $order = $this->payments->markMockSuccess(
            $request->string('merchant_reference')->toString(),
            $request->validated(),
        );

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
        ], 'Payment marked as paid');
    }
}

