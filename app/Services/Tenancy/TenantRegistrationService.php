<?php

namespace App\Services\Tenancy;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantSequence;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountsService;
use App\Support\PostgresTenantSession;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantRegistrationService
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * Register tenant, admin user, default COA, and invoice sequence in one atomic transaction.
     *
     * @param  array{company_name: string, admin_name: string, email: string, password: string, timezone?: string|null}  $data
     * @return array{tenant: Tenant, user: User}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $timezone = $data['timezone'] ?? 'UTC';

            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $this->generateUniqueSlug($data['company_name']),
                'status' => TenantStatus::Active,
                'settings' => [
                    'timezone' => $timezone,
                    'currency' => 'USD',
                    'fiscal_year_start' => '01-01',
                    'grace_period_days' => 7,
                ],
            ]);

            PostgresTenantSession::apply($tenant->id);
            TenantContext::set($tenant->id);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['admin_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);

            $this->chartOfAccountsService->seedDefaultAccounts($tenant->id);

            TenantSequence::create([
                'tenant_id' => $tenant->id,
                'invoice_next_number' => 1,
            ]);

            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'action' => 'tenant.registered',
                'entity_type' => Tenant::class,
                'entity_id' => $tenant->id,
                'payload' => [
                    'company_name' => $data['company_name'],
                    'admin_email' => $data['email'],
                ],
                'ip_address' => request()?->ip(),
            ]);

            return ['tenant' => $tenant, 'user' => $user];
        });
    }

    private function generateUniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName);
        $slug = $base;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
