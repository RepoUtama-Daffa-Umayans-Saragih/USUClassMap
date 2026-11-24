<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fotocopy_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('fotocopy_orders', 'customer_whatsapp')) {
                $table->string('customer_whatsapp', 30)->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'invoice_number')) {
                $table->string('invoice_number', 40)->nullable()->unique()->after('order_code');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'invoice_pdf_path')) {
                $table->string('invoice_pdf_path', 255)->nullable()->after('payment_payload');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'pickup_code')) {
                $table->string('pickup_code', 16)->nullable()->index()->after('invoice_number');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'pickup_estimate')) {
                $table->string('pickup_estimate', 100)->nullable()->after('order_status');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'wa_dispatch_status')) {
                $table->string('wa_dispatch_status', 20)->default('not_ready')->after('pickup_estimate');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'wa_dispatched_at')) {
                $table->timestamp('wa_dispatched_at')->nullable()->after('wa_dispatch_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fotocopy_orders', function (Blueprint $table) {
            $columns = [
                'customer_whatsapp',
                'invoice_number',
                'invoice_pdf_path',
                'pickup_code',
                'pickup_estimate',
                'wa_dispatch_status',
                'wa_dispatched_at',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('fotocopy_orders', $col)) {
                    if ($col === 'invoice_number') {
                        $table->dropUnique(['invoice_number']);
                    }
                    if ($col === 'pickup_code') {
                        $table->dropIndex(['pickup_code']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
