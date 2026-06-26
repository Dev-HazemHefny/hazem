<?php

namespace App\Services\Billing;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CustomerHasActiveSubscriptionsException;
use App\Exceptions\CustomerHasOpenInvoicesException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Customer
    {
        return Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => CustomerStatus::Active,
            'billing_address' => $data['billing_address'] ?? null,
        ]);
    }

    public function find(string $id): Customer
    {
        return Customer::findOrFail($id);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
        ], fn ($v) => $v !== null));

        return $customer->fresh();
    }

    public function softDelete(string $customerId): Customer
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            $hasActiveSubscriptions = Subscription::where('customer_id', $customer->id)
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->exists();

            if ($hasActiveSubscriptions) {
                throw new CustomerHasActiveSubscriptionsException();
            }

            $hasOpenInvoices = Invoice::where('customer_id', $customer->id)
                ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
                ->exists();

            if ($hasOpenInvoices) {
                throw new CustomerHasOpenInvoicesException();
            }

            $customer->update(['status' => CustomerStatus::Inactive]);
            $customer->delete();

            return $customer;
        });
    }
}
