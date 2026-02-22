<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_id', 20);
            $table->string('campaign_name')->nullable();
            $table->enum('status', ['waiting', 'ready', 'incall', 'paused', 'wrapup', 'logged_out'])->default('waiting');
            $table->string('asterisk_channel')->nullable();
            $table->string('current_lead_id')->nullable();
            $table->string('current_phone')->nullable();
            $table->string('current_lead_name')->nullable();
            $table->timestamp('call_started_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
