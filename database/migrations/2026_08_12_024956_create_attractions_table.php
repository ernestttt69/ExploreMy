<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attractions', function (Blueprint $table) {
            $table->id('attraction_id');
            $table->foreignId('state_id')->constrained('states', 'state_id')->onDelete('cascade');
            $table->string('attraction_name', 100);
            $table->string('category', 50);
            $table->string('description', 100);
            $table->string('location', 100);
            $table->string('operating_hours', 100)->nullable();
            $table->decimal('entrance_fee', 5, 2)->default(0.00);
            $table->string('budget_level', 100);
            $table->string('nearby_transport', 100);
            $table->decimal('rating', 2, 1)->default(0.0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attractions');
    }
};