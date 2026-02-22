<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VicidialDisposition extends Model
{
    protected $connection = 'vicidial';

    protected $table = 'vicidial_campaign_statuses';

    public $timestamps = false;

    /** @param Builder<VicidialDisposition> $query */
    public function scopeForCampaign(Builder $query, string $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    /** @param Builder<VicidialDisposition> $query */
    public function scopeSelectable(Builder $query): void
    {
        $query->where('selectable', 'Y');
    }
}
