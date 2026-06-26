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
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('amount_cents');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->enableTenantRls('invoice_line_items');
    }

    public function down(): void
    {
        $this->disableTenantRls('invoice_line_items');

        Schema::dropIfExists('invoice_line_items');
    }
};
