<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSubscriptionsSeeder extends Seeder
{
    /** @var array<string, string> */
    public static array $subscriptions = [];

    public function run(): void
    {
        $tenantId = DemoTenantSeeder::$tenantId;

        if (! $tenantId) {
            return;
        }

        TenantContext::runAs($tenantId, function () {
            $plans = SubscriptionPlan::all()->keyBy('name');
            $startDate = Carbon::now('America/New_York')->startOfMonth()->subMonth()->toDateString();
            $periodEnd = Carbon::now('America/New_York')->startOfMonth()->subDay()->toDateString();
            $periodStart = Carbon::parse($periodEnd, 'America/New_York')->startOfMonth()->toDateString();

            $matrix = [
                ['Alice', 'Gold', 'active', false],
                ['Bob', 'Bronze', 'active', false],
                ['Carol', 'Gold', 'active', false],
                ['Dave', 'Enterprise', 'active', false],
                ['Eve', 'Bronze', 'cancelled', true],
                ['Frank', 'Gold', 'active', false],
            ];

            foreach ($matrix as [$customerName, $planName, $status, $cancelAtPeriodEnd]) {
                $subscription = Subscription::create([
                    'customer_id' => DemoCustomersSeeder::$customers[$customerName],
                    'plan_id' => $plans[$planName]->id,
                    'status' => $status,
                    'start_date' => $startDate,
                    'auto_renew' => $status !== 'cancelled',
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'next_billing_at' => Carbon::parse($periodEnd, 'America/New_York')->addDay(),
                    'cancel_at_period_end' => $cancelAtPeriodEnd,
                    'cancelled_at' => $cancelAtPeriodEnd ? now() : null,
                ]);

                self::$subscriptions[$customerName] = $subscription->id;
            }
        });
    }
}
