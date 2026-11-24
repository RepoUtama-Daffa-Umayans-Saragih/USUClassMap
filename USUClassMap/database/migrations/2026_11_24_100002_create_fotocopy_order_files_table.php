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
        Schema::create('fotocopy_order_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fotocopy_order_id')
                ->constrained('fotocopy_orders')
                ->cascadeOnDelete();
            $table->foreignId('fotocopy_order_item_id')
                ->nullable()
                ->constrained('fotocopy_order_items')
                ->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('storage_disk', 30)->default('local');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('page_count')->default(1);
            $table->unsignedInteger('copies')->default(1);
            $table->string('print_mode', 100)->default('Print Hitam Putih A4');
            $table->string('paper_size', 30)->default('A4');
            $table->string('finishing_option', 120)->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('subtotal_estimated')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_encrypted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fotocopy_order_files');
    }
};
