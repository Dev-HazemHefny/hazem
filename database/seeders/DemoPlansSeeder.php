<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DemoPlansSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DemoTenantSeeder::$tenantId;

        if (! $tenantId) {
            return;
        }

        TenantContext::runAs($tenantId, function () {
            SubscriptionPlan::create([
                'name' => 'Bronze',
                'description' => 'Bronze plan - $100/month',
                'price_cents' => 10000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);

            SubscriptionPlan::create([
                'name' => 'Gold',
                'description' => 'Gold plan - $500/month',
                'price_cents' => 50000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);

            SubscriptionPlan::create([
                'name' => 'Enterprise',
                'description' => 'Enterprise plan - $1200/year',
                'price_cents' => 120000,
                'currency' => 'USD',
                'billing_interval' => 'yearly',
                'status' => 'active',
            ]);
        });
    }
}
