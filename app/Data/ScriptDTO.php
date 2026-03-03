<?php

namespace App\Data;

readonly class ScriptDTO
{
    public function __construct(
        public string $name,
        public string $body,
    ) {}
}
