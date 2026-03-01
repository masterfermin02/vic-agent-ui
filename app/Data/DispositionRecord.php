<?php

namespace App\Data;

readonly class DispositionRecord
{
    public function __construct(
        public string $calledAt,
        public string $status,
        public string $agentId,
        public int $durationSeconds,
        public string $notes,
    ) {}
}
