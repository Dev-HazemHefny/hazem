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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number');
            $table->string('status')->default('draft');
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->bigInteger('amount_paid_cents')->default(0);
            $table->bigInteger('amount_due_cents')->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->string('billing_idempotency_key');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'billing_idempotency_key']);
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
        });

        $this->enableTenantRls('invoices');
    }

    public function down(): void
    {
        $this->disableTenantRls('invoices');

        Schema::dropIfExists('invoices');
    }
};
