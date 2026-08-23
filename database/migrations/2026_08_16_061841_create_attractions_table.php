<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attractions')) {
            return;
        }

        Schema::create('attractions', function (Blueprint $table) {
            $table->increments('attraction_id');

            $table->string('place_id', 255)->unique();

            $table->unsignedInteger('state_id');

            $table->string('attraction_name', 100);
            $table->string('category', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 255);
            $table->text('operating_hours')->nullable();
            $table->string('entrance_fee', 100)->default('Price unavailable');
            $table->string('budget_level', 20)->default('Price unavailable');
            $table->string('nearby_transport', 100)->nullable();
            $table->decimal('rating', 2, 1)->nullable();

            $table->foreign('state_id')
                ->references('state_id')
                ->on('states')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attractions');
    }
};
