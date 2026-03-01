<?php

namespace App\Data;

readonly class LeadDTO
{
    /**
     * @param  DispositionRecord[]  $previousDispositions
     * @param  array<string, string>  $customFields
     */
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $phoneCode,
        public string $status,
        public string $email,
        public string $address,
        public string $notes,
        public int $calledCount,
        public array $previousDispositions,
        public array $customFields,
    ) {}
}
