<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $perPage = max(1, min(50, (int) $request->integer('per_page', 20)));

        $paginator = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['items', 'payments'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($paginator, OrderResource::collection(collect($paginator->items())));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['items', 'payments'])
            ->whereKey($id)
            ->firstOrFail();

        return ApiResponse::success(new OrderResource($order));
    }

    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['payments' => fn ($q) => $q->orderByDesc('id')])
            ->whereKey($id)
            ->firstOrFail();

        $latestPayment = $order->payments->first();

        return ApiResponse::success([
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'latest_payment' => $latestPayment ? [
                'id' => $latestPayment->id,
                'status' => $latestPayment->status,
                'merchant_reference' => $latestPayment->merchant_reference,
            ] : null,
        ]);
    }
}

