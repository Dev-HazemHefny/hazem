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
        Schema::create('revenue_recognition_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('amount_cents');
            $table->string('status')->default('pending');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('recognition_idempotency_key');
            $table->timestamps();

            $table->unique(['tenant_id', 'recognition_idempotency_key']);
            $table->index(['tenant_id', 'status', 'period_end']);
        });

        $this->enableTenantRls('revenue_recognition_schedules');
    }

    public function down(): void
    {
        $this->disableTenantRls('revenue_recognition_schedules');

        Schema::dropIfExists('revenue_recognition_schedules');
    }
};
