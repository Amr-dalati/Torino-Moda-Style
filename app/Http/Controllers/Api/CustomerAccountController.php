<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAccount\DeleteCustomerAccountRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerDeletionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
{
    public function __construct(
        protected CustomerDeletionService $deletion,
    ) {}

    public function destroy(DeleteCustomerAccountRequest $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validated = $request->validated();

        $this->deletion->deleteAccount(
            $customer,
            $validated['password'],
            $validated['deletion_reason'] ?? null,
        );

        return ApiResponse::success(null, 'Account deleted successfully');
    }

    protected function requireCustomer(Request $request): ?Customer
    {
        $tokenable = $request->user();

        return $tokenable instanceof Customer ? $tokenable : null;
    }
}
