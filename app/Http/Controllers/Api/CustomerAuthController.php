<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAuth\LoginCustomerRequest;
use App\Http\Requests\CustomerAuth\RegisterCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Customers\CustomerAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected CustomerAuthService $auth,
    ) {}

    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            $request->validated(),
            $request->string('device_name', 'mobile')->toString(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'customer' => new CustomerResource($result['customer']),
        ], 'Registered successfully', 201);
    }

    public function login(LoginCustomerRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'mobile')->toString(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'customer' => new CustomerResource($result['customer']),
        ], 'Logged in successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success(new CustomerResource($customer));
    }

    public function logout(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        if (! $customer) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $this->auth->logout($customer);

        return ApiResponse::success(null, 'Logged out successfully');
    }

    protected function requireCustomer(Request $request): ?Customer
    {
        $tokenable = $request->user();

        return $tokenable instanceof Customer ? $tokenable : null;
    }
}

