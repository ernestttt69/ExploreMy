<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attractions', 'category')) {
            Schema::table('attractions', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        // Restores the schema only, not any previously deleted values.
        if (! Schema::hasColumn('attractions', 'category')) {
            Schema::table('attractions', function (Blueprint $table) {
                $table->string('category', 255)->nullable();
            });
        }
    }
};
