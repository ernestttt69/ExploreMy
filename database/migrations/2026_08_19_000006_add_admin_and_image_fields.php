<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::table('attractions', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'created_at', 'updated_at']);
        });
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
