<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preference_categories', function (Blueprint $table) {
            $table->increments('preference_id');
            $table->string('category_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preference_categories');
    }
};
