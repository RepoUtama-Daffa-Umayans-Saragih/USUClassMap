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
        Schema::create('whatsapp_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fotocopy_order_id')
                ->constrained('fotocopy_orders')
                ->cascadeOnDelete();
            $table->string('recipient_phone', 30)->index();
            $table->string('recipient_role', 20)->default('store');
            $table->string('message_type', 30)->index();
            $table->json('payload_json')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('driver', 30)->default('sandbox');
            $table->string('external_message_id', 120)->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_dispatch_logs');
    }
};
