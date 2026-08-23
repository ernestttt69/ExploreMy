<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('states')) {
            return;
        }

        Schema::create('states', function (Blueprint $table) {
            $table->increments('state_id');
            $table->string('state_name', 100)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
