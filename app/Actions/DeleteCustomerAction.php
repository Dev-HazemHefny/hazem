<?php

namespace App\Actions;

use App\Models\Customer;
use App\Services\Billing\CustomerService;

class DeleteCustomerAction
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function execute(string $customerId): Customer
    {
        return $this->customerService->softDelete($customerId);
    }
}
