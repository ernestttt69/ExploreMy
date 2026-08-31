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
        Schema::create('itinerary_shares', function (Blueprint $table) {
            $table->bigIncrements('share_id');
            $table->unsignedBigInteger('trip_id')->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('permission', 10)->default('view');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_shares');
    }
};
