<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VicidialCampaign extends Model
{
    protected $connection = 'vicidial';

    protected $table = 'vicidial_campaigns';

    public $timestamps = false;

    protected $primaryKey = 'campaign_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @param Builder<VicidialCampaign> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', 'Y');
    }
}
