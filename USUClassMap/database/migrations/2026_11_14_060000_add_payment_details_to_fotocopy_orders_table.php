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
            if (!Schema::hasColumn('fotocopy_orders', 'payment_channel')) {
                $table->string('payment_channel', 50)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'payment_payload')) {
                $table->json('payment_payload')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('fotocopy_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fotocopy_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_channel',
                'payment_payload',
                'paid_at',
            ]);
        });
    }
};
