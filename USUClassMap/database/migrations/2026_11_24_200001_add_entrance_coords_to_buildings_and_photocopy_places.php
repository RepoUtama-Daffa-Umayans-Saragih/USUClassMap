<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('buildings')) {
            Schema::table('buildings', function (Blueprint $table) {
                if (!Schema::hasColumn('buildings', 'entrance_lat')) {
                    $table->decimal('entrance_lat', 10, 7)->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('buildings', 'entrance_lng')) {
                    $table->decimal('entrance_lng', 10, 7)->nullable()->after('entrance_lat');
                }
            });
        }

        if (Schema::hasTable('photocopy_places')) {
            Schema::table('photocopy_places', function (Blueprint $table) {
                if (!Schema::hasColumn('photocopy_places', 'entrance_lat')) {
                    $table->decimal('entrance_lat', 10, 7)->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('photocopy_places', 'entrance_lng')) {
                    $table->decimal('entrance_lng', 10, 7)->nullable()->after('entrance_lat');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('buildings')) {
            Schema::table('buildings', function (Blueprint $table) {
                $cols = array_values(array_filter(['entrance_lat', 'entrance_lng'], fn ($c) => Schema::hasColumn('buildings', $c)));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('photocopy_places')) {
            Schema::table('photocopy_places', function (Blueprint $table) {
                $cols = array_values(array_filter(['entrance_lat', 'entrance_lng'], fn ($c) => Schema::hasColumn('photocopy_places', $c)));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
