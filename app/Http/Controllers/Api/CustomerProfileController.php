<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\UpdateCustomerProfileRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Customers\CustomerProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function __construct(
        protected CustomerProfileService $profiles,
    ) {}

    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $updated = $this->profiles->update($customer, $request->validated());

        return ApiResponse::success(new CustomerResource($updated), 'Profile updated');
    }

    protected function requireCustomer(Request $request): ?Customer
    {
        $tokenable = $request->user();

        return $tokenable instanceof Customer ? $tokenable : null;
    }
}

