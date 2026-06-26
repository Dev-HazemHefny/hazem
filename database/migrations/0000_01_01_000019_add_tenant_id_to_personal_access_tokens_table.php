<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'token']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
UPDATE personal_access_tokens pat
SET tenant_id = u.tenant_id
FROM users u
WHERE pat.tokenable_type LIKE '%User'
  AND pat.tokenable_id::text = u.id::text
  AND pat.tenant_id IS NULL
SQL);
        } else {
            DB::table('personal_access_tokens')
                ->orderBy('id')
                ->lazy()
                ->each(function ($token) {
                    $user = DB::table('users')->where('id', $token->tokenable_id)->first();
                    if ($user) {
                        DB::table('personal_access_tokens')
                            ->where('id', $token->id)
                            ->update(['tenant_id' => $user->tenant_id]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
