<?php

use App\Database\Migrations\Concerns\EnablesRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use EnablesRowLevelSecurity;

    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        $this->enableTenantRls('accounts');
    }

    public function down(): void
    {
        $this->disableTenantRls('accounts');

        Schema::dropIfExists('accounts');
    }
};
