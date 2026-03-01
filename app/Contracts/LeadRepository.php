<?php

namespace App\Contracts;

use App\Data\LeadDTO;

interface LeadRepository
{
    public function findByLeadId(int $leadId): LeadDTO;
}
