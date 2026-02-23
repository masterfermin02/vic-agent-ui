<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table): void {
            $table->string('server_ip', 15)->nullable()->after('campaign_name');
            $table->string('conf_exten', 20)->nullable()->after('server_ip');
            $table->string('session_name', 40)->nullable()->after('conf_exten');
            $table->unsignedBigInteger('agent_log_id')->nullable()->after('session_name');
            $table->string('user_group', 20)->nullable()->after('agent_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table): void {
            $table->dropColumn(['server_ip', 'conf_exten', 'session_name', 'agent_log_id', 'user_group']);
        });
    }
};
