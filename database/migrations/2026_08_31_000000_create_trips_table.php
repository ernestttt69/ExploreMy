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
        if (! Schema::hasTable('trips')) {
            Schema::create('trips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title', 120);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedInteger('days')->nullable();
                $table->decimal('co2_kg', 9, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('trips', 'destination')) {
            Schema::table('trips', fn (Blueprint $table) => $table->string('destination', 160)->nullable()->after('title'));
        }

        if (! Schema::hasColumn('trips', 'description')) {
            Schema::table('trips', fn (Blueprint $table) => $table->text('description')->nullable()->after('destination'));
        }

        if (! Schema::hasColumn('trips', 'map_center')) {
            Schema::table('trips', fn (Blueprint $table) => $table->json('map_center')->nullable()->after('end_date'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Trips existed before this module in the current application, so rollback
        // deliberately preserves trip records instead of risking their deletion.
    }
};
