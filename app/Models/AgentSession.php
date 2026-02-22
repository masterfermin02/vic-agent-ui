<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSession extends Model
{
    /** @use HasFactory<\Database\Factories\AgentSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'campaign_name',
        'status',
        'asterisk_channel',
        'current_lead_id',
        'current_phone',
        'current_lead_name',
        'call_started_at',
    ];

    protected function casts(): array
    {
        return [
            'call_started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
