<?php

namespace App\Data;

readonly class AgentPerformanceDTO
{
    public function __construct(
        public int $callsToday,
        public int $totalTalkSeconds,
        public int $avgDurationSeconds,
        public float $conversionRate,
    ) {}
}
