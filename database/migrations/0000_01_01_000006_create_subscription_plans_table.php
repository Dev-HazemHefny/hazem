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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('price_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        $this->enableTenantRls('subscription_plans');
    }

    public function down(): void
    {
        $this->disableTenantRls('subscription_plans');

        Schema::dropIfExists('subscription_plans');
    }
};
