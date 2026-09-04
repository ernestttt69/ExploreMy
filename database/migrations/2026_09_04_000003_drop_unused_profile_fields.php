<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropColumn(['nationality', 'bio']);
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->string('nationality', 100)->nullable()->after('date_of_birth');
            $table->text('bio')->nullable()->after('nationality');
        });
    }
};
