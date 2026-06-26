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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->json('billing_address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->enableTenantRls('customers');
    }

    public function down(): void
    {
        $this->disableTenantRls('customers');

        Schema::dropIfExists('customers');
    }
};
