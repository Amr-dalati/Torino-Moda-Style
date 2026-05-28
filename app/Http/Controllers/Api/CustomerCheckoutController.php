<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutQuoteRequest;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerCheckoutController extends Controller
{
    public function __construct(
        protected CheckoutQuoteService $quotes,
        protected CheckoutService $checkout,
    ) {}

    public function quote(CheckoutQuoteRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $quote = $this->quotes->quote($customer, (int) $request->integer('address_id'));

        return ApiResponse::success($quote);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $result = $this->checkout->checkout($customer, (int) $request->integer('address_id'));

        return ApiResponse::success([
            'order' => new OrderResource($result['order']),
            'payment' => new PaymentResource($result['payment']),
        ], 'Checkout started', 201);
    }
}

