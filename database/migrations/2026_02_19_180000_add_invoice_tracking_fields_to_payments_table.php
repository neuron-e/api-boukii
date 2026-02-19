<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payrexx_invoice_id')) {
                $table->string('payrexx_invoice_id')->nullable()->after('payrexx_reference');
            }
            if (!Schema::hasColumn('payments', 'invoice_status')) {
                $table->string('invoice_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payments', 'invoice_due_at')) {
                $table->timestamp('invoice_due_at')->nullable()->after('invoice_status');
            }
            if (!Schema::hasColumn('payments', 'invoice_url')) {
                $table->text('invoice_url')->nullable()->after('invoice_due_at');
            }
            if (!Schema::hasColumn('payments', 'invoice_pdf_url')) {
                $table->text('invoice_pdf_url')->nullable()->after('invoice_url');
            }
            if (!Schema::hasColumn('payments', 'invoice_meta')) {
                $table->json('invoice_meta')->nullable()->after('invoice_pdf_url');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['booking_id', 'invoice_status'], 'idx_payments_booking_invoice_status');
            $table->index(['payrexx_reference'], 'idx_payments_payrexx_reference');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'invoice_meta')) {
                $table->dropColumn('invoice_meta');
            }
            if (Schema::hasColumn('payments', 'invoice_pdf_url')) {
                $table->dropColumn('invoice_pdf_url');
            }
            if (Schema::hasColumn('payments', 'invoice_url')) {
                $table->dropColumn('invoice_url');
            }
            if (Schema::hasColumn('payments', 'invoice_due_at')) {
                $table->dropColumn('invoice_due_at');
            }
            if (Schema::hasColumn('payments', 'invoice_status')) {
                $table->dropColumn('invoice_status');
            }
            if (Schema::hasColumn('payments', 'payrexx_invoice_id')) {
                $table->dropColumn('payrexx_invoice_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_booking_invoice_status');
            $table->dropIndex('idx_payments_payrexx_reference');
        });
    }
};

