<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('personalisation_consent')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['phone', 'date_of_birth', 'nationality', 'bio', 'preferred_language', 'email_notifications', 'personalisation_consent', 'last_login_at']);
        });
    }
};
