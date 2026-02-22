<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('vicidial_user')->nullable()->after('remember_token');
            $table->string('vicidial_pass')->nullable()->after('vicidial_user');
            $table->string('vicidial_phone_login')->nullable()->after('vicidial_pass');
            $table->string('vicidial_phone_pass')->nullable()->after('vicidial_phone_login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['vicidial_user', 'vicidial_pass', 'vicidial_phone_login', 'vicidial_phone_pass']);
        });
    }
};
