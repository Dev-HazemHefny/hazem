<?php

namespace App\Actions;

use App\Services\Tenancy\AuthService;
use App\Services\Tenancy\TenantRegistrationService;
use App\Support\TenantContext;

class RegisterTenantAction
{
    public function __construct(
        private readonly TenantRegistrationService $registrationService,
        private readonly AuthService $authService,
    ) {}

    /**
     * @param  array{company_name: string, admin_name: string, email: string, password: string, timezone?: string|null}  $data
     * @return array{tenant: \App\Models\Tenant, user: \App\Models\User, token: string}
     */
    public function execute(array $data): array
    {
        try {
            $result = $this->registrationService->register($data);
            $token = $this->authService->issueToken($result['user']);

            return [...$result, 'token' => $token];
        } finally {
            TenantContext::set(null);
        }
    }
}
