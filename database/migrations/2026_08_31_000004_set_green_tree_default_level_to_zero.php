<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
