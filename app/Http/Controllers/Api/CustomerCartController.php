<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Customer;
use App\Services\Cart\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerCartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $cart = $this->cartService->getActiveCart($customer);

        return ApiResponse::success(new CartResource($cart));
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $cart = $this->cartService->addItem(
            $customer,
            (int) $request->integer('product_variant_id'),
            (int) $request->integer('quantity'),
        );

        return ApiResponse::success(new CartResource($cart), 'Item added');
    }

    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $cart = $this->cartService->updateItemQuantity(
            $customer,
            $id,
            (int) $request->integer('quantity'),
        );

        return ApiResponse::success(new CartResource($cart), 'Item updated');
    }

    public function removeItem(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $cart = $this->cartService->removeItem($customer, $id);

        return ApiResponse::success(new CartResource($cart), 'Item removed');
    }

    public function clear(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $cart = $this->cartService->clear($customer);

        return ApiResponse::success(new CartResource($cart), 'Cart cleared');
    }
}

