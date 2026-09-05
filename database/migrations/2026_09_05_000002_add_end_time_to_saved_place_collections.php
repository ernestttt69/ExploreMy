<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_place_collections', function (Blueprint $table): void {
            $table->time('end_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_place_collections', function (Blueprint $table): void {
            $table->dropColumn('end_time');
        });
    }
};
