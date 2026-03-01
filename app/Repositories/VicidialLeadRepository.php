<?php

namespace App\Repositories;

use App\Contracts\LeadRepository;
use App\Data\DispositionRecord;
use App\Data\LeadDTO;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VicidialLeadRepository implements LeadRepository
{
    public function findByLeadId(int $leadId): LeadDTO
    {
        $lead = DB::connection('vicidial')
            ->table('vicidial_list')
            ->where('lead_id', $leadId)
            ->first();

        if (! $lead) {
            throw new RuntimeException("Lead {$leadId} not found in vicidial_list.");
        }

        $logs = DB::connection('vicidial')
            ->table('vicidial_log')
            ->where('lead_id', $leadId)
            ->orderByDesc('call_date')
            ->limit(10)
            ->get();

        $previousDispositions = $logs->map(fn ($log) => new DispositionRecord(
            calledAt: (string) $log->call_date,
            status: (string) $log->status,
            agentId: (string) $log->user,
            durationSeconds: (int) ($log->length_in_sec ?? 0),
            notes: (string) ($log->comments ?? ''),
        ))->all();

        $customFields = [
            'vendor_lead_code' => (string) ($lead->vendor_lead_code ?? ''),
            'source_id' => (string) ($lead->source_id ?? ''),
        ];

        return new LeadDTO(
            id: $leadId,
            firstName: (string) ($lead->first_name ?? ''),
            lastName: (string) ($lead->last_name ?? ''),
            phone: (string) ($lead->phone_number ?? ''),
            phoneCode: (string) ($lead->phone_code ?? '1'),
            status: (string) ($lead->status ?? ''),
            email: (string) ($lead->email ?? ''),
            address: trim(implode(', ', array_filter([
                (string) ($lead->address1 ?? ''),
                (string) ($lead->city ?? ''),
                (string) ($lead->state ?? ''),
                (string) ($lead->postal_code ?? ''),
            ]))),
            notes: (string) ($lead->comments ?? ''),
            calledCount: (int) ($lead->called_count ?? 0),
            previousDispositions: $previousDispositions,
            customFields: $customFields,
        );
    }
}
