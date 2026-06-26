<?php

namespace Tests\Feature\Accounting;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingCycleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class PaymentGuardTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_overpayment_is_rejected(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $invoiceId = null;

        TenantContext::runAs($tenantId, function () use (&$invoiceId) {
            $invoiceId = $this->createBilledInvoice(10000)->id;
        });

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount_cents' => 15000,
            'client_idempotency_key' => 'overpay-1',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OVERPAYMENT');
    }

    public function test_payment_idempotency_returns_same_result(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $invoiceId = null;

        TenantContext::runAs($tenantId, function () use (&$invoiceId) {
            $invoiceId = $this->createBilledInvoice(10000)->id;
        });

        $payload = [
            'amount_cents' => 5000,
            'client_idempotency_key' => 'idem-pay-1',
        ];

        $first = $this->postJson("/api/v1/invoices/{$invoiceId}/payments", $payload, $this->authHeaders());
        $second = $this->postJson("/api/v1/invoices/{$invoiceId}/payments", $payload, $this->authHeaders());

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );
    }

    public function test_partial_payments_do_not_exceed_amount_due(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $invoiceId = null;

        TenantContext::runAs($tenantId, function () use (&$invoiceId) {
            $invoiceId = $this->createBilledInvoice(10000)->id;
        });

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount_cents' => 6000,
            'client_idempotency_key' => 'partial-1',
        ], $this->authHeaders())->assertCreated();

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount_cents' => 5000,
            'client_idempotency_key' => 'partial-2',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OVERPAYMENT');
    }

    private function createBilledInvoice(int $priceCents): Invoice
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Bronze',
            'price_cents' => $priceCents,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'status' => 'active',
        ]);

        $customer = Customer::create(['name' => 'Carol', 'email' => 'carol@test.com', 'status' => 'active']);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => '2025-01-01',
            'auto_renew' => true,
            'current_period_start' => '2025-01-01',
            'current_period_end' => '2025-01-31',
            'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
        ]);

        app(BillingCycleService::class)->billSubscription($subscription);

        return Invoice::firstOrFail();
    }
}
