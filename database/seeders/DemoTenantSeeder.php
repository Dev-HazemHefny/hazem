<?php

namespace Database\Seeders;

use App\Actions\RegisterTenantAction;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public const DEMO_TENANT_EMAIL = 'demo@acme.com';

    public const DEMO_TENANT_PASSWORD = 'DemoPass123!';

    public static ?string $tenantId = null;

    public function run(RegisterTenantAction $registerTenantAction): void
    {
        $result = $registerTenantAction->execute([
            'company_name' => 'Acme Corp',
            'admin_name' => 'Demo Admin',
            'email' => self::DEMO_TENANT_EMAIL,
            'password' => self::DEMO_TENANT_PASSWORD,
            'timezone' => 'America/New_York',
            'currency' => 'USD',
        ]);

        self::$tenantId = $result['tenant']->id;
    }
}
