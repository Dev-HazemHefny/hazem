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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status')->default('posted');
            $table->uuid('reverses_entry_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'entry_date']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        $this->enableTenantRls('journal_entries');
    }

    public function down(): void
    {
        $this->disableTenantRls('journal_entries');

        Schema::dropIfExists('journal_entries');
    }
};
