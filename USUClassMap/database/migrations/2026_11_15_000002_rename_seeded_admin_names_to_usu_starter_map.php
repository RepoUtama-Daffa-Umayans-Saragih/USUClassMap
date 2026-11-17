<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'superadmin@usu.ac.id')
            ->where('name', 'Super Admin USUClassMap')
            ->update(['name' => 'Super Admin USUStarterMap']);

        DB::table('users')
            ->whereIn('email', ['adminususclassmapi', 'admin@usu.ac.id'])
            ->where('name', 'Admin USUClassMap')
            ->update(['name' => 'Admin USUStarterMap']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'superadmin@usu.ac.id')
            ->where('name', 'Super Admin USUStarterMap')
            ->update(['name' => 'Super Admin USUClassMap']);

        DB::table('users')
            ->whereIn('email', ['adminususclassmapi', 'admin@usu.ac.id'])
            ->where('name', 'Admin USUStarterMap')
            ->update(['name' => 'Admin USUClassMap']);
    }
};
