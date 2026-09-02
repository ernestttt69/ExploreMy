<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table is already created with this default. Avoid requiring
        // Doctrine DBAL merely to alter an SQLite column during local setup.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('green_trees', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('green_trees', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(1)->change();
        });
    }
};
