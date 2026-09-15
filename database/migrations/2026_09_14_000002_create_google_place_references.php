<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('google_place_references', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('google_place_references'); }
};
