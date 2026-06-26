<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DemoCustomersSeeder extends Seeder
{
    /** @var array<string, string> */
    public static array $customers = [];

    public function run(): void
    {
        $tenantId = DemoTenantSeeder::$tenantId;

        if (! $tenantId) {
            return;
        }

        TenantContext::runAs($tenantId, function () {
            $names = ['Alice', 'Bob', 'Carol', 'Dave', 'Eve', 'Frank'];

            foreach ($names as $name) {
                $customer = Customer::create([
                    'name' => $name,
                    'email' => strtolower($name).'@demo.acme.com',
                    'status' => 'active',
                ]);

                self::$customers[$name] = $customer->id;
            }
        });
    }
}
