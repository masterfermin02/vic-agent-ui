<?php

namespace App\Data;

readonly class SipConfigDTO
{
    /**
     * @param  list<string>  $codecs
     */
    public function __construct(
        public string $extension,
        public string $sipAuthUser,
        public ?string $sipAltAuthUser,
        public string $sipPassword,
        public ?string $sipAltPassword,
        public string $sipServer,
        public string $wsUrl,
        public array $codecs,
        public bool $autoAnswer,
        public bool $mute,
        public bool $dialpad,
        public bool $debug,
    ) {}
}
