<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('attraction_image', function (Blueprint $table): void {
            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('attraction_image', function (Blueprint $table): void {
            $table->dropForeign(['attraction_id']);
        });
    }
};
