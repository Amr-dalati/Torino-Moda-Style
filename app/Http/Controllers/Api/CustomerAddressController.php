<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerAddressRequest;
use App\Http\Requests\Customers\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Services\Customers\CustomerAddressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function __construct(
        protected CustomerAddressService $addresses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $rows = $this->addresses->list($customer);

        return ApiResponse::success(CustomerAddressResource::collection(collect($rows)));
    }

    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $address = $this->addresses->create($customer, $request->validated());

        return ApiResponse::success(new CustomerAddressResource($address), 'Address created', 201);
    }

    public function update(UpdateCustomerAddressRequest $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $address = $this->addresses->update($customer, $id, $request->validated());

        return ApiResponse::success(new CustomerAddressResource($address), 'Address updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $this->addresses->delete($customer, $id);

        return ApiResponse::success(null, 'Address deleted');
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $address = $this->addresses->setDefault($customer, $id);

        return ApiResponse::success(new CustomerAddressResource($address), 'Default address set');
    }

    protected function requireCustomer(Request $request): ?Customer
    {
        $tokenable = $request->user();

        return $tokenable instanceof Customer ? $tokenable : null;
    }
}

