<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('price_cents')->nullable()->after('plan_id');
            $table->string('billing_interval')->nullable()->after('price_cents');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['price_cents', 'billing_interval']);
        });
    }
};
