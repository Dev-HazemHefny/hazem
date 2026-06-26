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
        Schema::create('tenant_sequences', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_next_number')->default(1);
        });

        $this->enableTenantRls('tenant_sequences');
    }

    public function down(): void
    {
        $this->disableTenantRls('tenant_sequences');

        Schema::dropIfExists('tenant_sequences');
    }
};
