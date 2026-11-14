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
        Schema::create('fotocopy_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location_name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('opening_hours')->default('07:30 - 20:30');
            $table->string('operating_days')->default('Senin - Sabtu');
            $table->string('whatsapp')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->text('description')->nullable();
            $table->decimal('rating', 2, 1)->default(4.8);
            $table->boolean('is_open')->default(true);
            $table->unsignedInteger('starting_price')->default(200);
            $table->timestamps();
        });

        Schema::create('fotocopy_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('fotocopy_stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Print'); // Print, Fotocopy, Jilid, ATK
            $table->unsignedInteger('price')->default(200);
            $table->string('unit')->default('lembar'); // lembar, buku, pcs, paket
            $table->string('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('fotocopy_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('store_id')->constrained('fotocopy_stores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method')->default('QRIS'); // QRIS, Tunai, Transfer
            $table->string('payment_status')->default('pending'); // pending, paid
            $table->string('order_status')->default('Menunggu'); // Menunggu, Diproses, Selesai, Dibatalkan
            $table->unsignedInteger('total_price')->default(0);
            $table->timestamps();
        });

        Schema::create('fotocopy_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('fotocopy_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('fotocopy_services')->nullOnDelete();
            $table->string('service_name');
            $table->string('category')->default('Print');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('subtotal')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fotocopy_order_items');
        Schema::dropIfExists('fotocopy_orders');
        Schema::dropIfExists('fotocopy_services');
        Schema::dropIfExists('fotocopy_stores');
    }
};
