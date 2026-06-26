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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('completed');
            $table->timestampTz('paid_at')->nullable();
            $table->string('client_idempotency_key');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_idempotency_key']);
        });

        $this->enableTenantRls('payments');
    }

    public function down(): void
    {
        $this->disableTenantRls('payments');

        Schema::dropIfExists('payments');
    }
};
