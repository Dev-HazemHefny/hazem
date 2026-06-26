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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestampTz('next_billing_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'next_billing_at']);
        });

        $this->enableTenantRls('subscriptions');
    }

    public function down(): void
    {
        $this->disableTenantRls('subscriptions');

        Schema::dropIfExists('subscriptions');
    }
};
