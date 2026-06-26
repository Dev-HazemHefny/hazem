<?php

use App\Database\Migrations\Concerns\EnablesRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use EnablesRowLevelSecurity;

    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'account_id']);
        });

        $this->enableTenantRls('journal_lines');

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE journal_lines
    ADD CONSTRAINT jl_one_side_nonzero CHECK (
        (debit_cents > 0 AND credit_cents = 0) OR
        (credit_cents > 0 AND debit_cents = 0)
    )
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION assert_journal_entry_balanced()
RETURNS TRIGGER AS $$
DECLARE
    total_dr bigint;
    total_cr bigint;
BEGIN
    SELECT COALESCE(SUM(debit_cents), 0), COALESCE(SUM(credit_cents), 0)
    INTO total_dr, total_cr
    FROM journal_lines
    WHERE journal_entry_id = NEW.journal_entry_id;

    IF total_dr != total_cr THEN
        RAISE EXCEPTION 'journal entry % unbalanced: dr=% cr=%', NEW.journal_entry_id, total_dr, total_cr;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER journal_entry_balance_check
    AFTER INSERT OR UPDATE ON journal_lines
    DEFERRABLE INITIALLY DEFERRED
    FOR EACH ROW
    EXECUTE FUNCTION assert_journal_entry_balanced();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS journal_entry_balance_check ON journal_lines');
            DB::unprepared('DROP FUNCTION IF EXISTS assert_journal_entry_balanced()');
        }

        $this->disableTenantRls('journal_lines');

        Schema::dropIfExists('journal_lines');
    }
};
