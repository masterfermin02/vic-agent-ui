<?php

namespace Tests\TestSupport;

use Illuminate\Support\Facades\Schema;

trait WithVicidialDatabase
{
    public function setUpVicidialDatabase(): void
    {
        Schema::connection('vicidial')->create('vicidial_campaigns', function ($table): void {
            $table->string('campaign_id', 20)->primary();
            $table->string('campaign_name')->default('');
            $table->char('active', 1)->default('Y');
            $table->string('dial_method', 20)->default('RATIO');
        });

        Schema::connection('vicidial')->create('vicidial_campaign_statuses', function ($table): void {
            $table->id();
            $table->string('campaign_id', 20)->default('');
            $table->string('status', 6)->default('');
            $table->string('status_name', 30)->default('');
            $table->char('selectable', 1)->default('Y');
            $table->char('sale', 1)->default('N');
            $table->char('dnc', 1)->default('N');
        });
    }
}
