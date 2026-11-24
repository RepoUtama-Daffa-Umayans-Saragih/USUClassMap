<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar tabel katalog yang diperkaya kolomnya.
     *
     * @var array<int, string>
     */
    protected array $tables = [
        'fotocopy_services',
        'photocopy_items',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'item_type')) {
                    $table->string('item_type')->default('jasa')->after('category');
                }
                if (!Schema::hasColumn($tableName, 'sub_category')) {
                    $table->string('sub_category')->nullable()->after('item_type');
                }
                if (!Schema::hasColumn($tableName, 'brand')) {
                    $table->string('brand')->nullable()->after('sub_category');
                }
                if (!Schema::hasColumn($tableName, 'pack_qty')) {
                    $table->unsignedInteger('pack_qty')->default(1)->after('brand');
                }
                if (!Schema::hasColumn($tableName, 'image_path')) {
                    $table->string('image_path')->nullable()->after('description');
                }
                if (!Schema::hasColumn($tableName, 'metadata')) {
                    $table->json('metadata')->nullable()->after('image_path');
                }
                if (!Schema::hasColumn($tableName, 'stock')) {
                    $table->integer('stock')->nullable()->after('metadata');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'item_type',
            'sub_category',
            'brand',
            'pack_qty',
            'image_path',
            'metadata',
            'stock',
        ];

        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $existing = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn($tableName, $column)
            ));

            if (!empty($existing)) {
                Schema::table($tableName, function (Blueprint $table) use ($existing) {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
