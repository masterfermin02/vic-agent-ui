<?php

namespace App\Repositories;

use App\Contracts\LeadRepository;
use App\Data\DispositionRecord;
use App\Data\LeadDTO;
use Faker\Factory;

class AsteriskLeadRepository implements LeadRepository
{
    public function findByLeadId(int $leadId): LeadDTO
    {
        $faker = Factory::create();
        $faker->seed($leadId);

        $statuses = ['NEW', 'CBHOLD', 'SALE', 'NI', 'NA', 'DNC', 'DNCL'];

        $previousDispositions = [];
        for ($i = 0; $i < 3; $i++) {
            $previousDispositions[] = new DispositionRecord(
                calledAt: $faker->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d H:i:s'),
                status: $faker->randomElement($statuses),
                agentId: 'agent'.$faker->numerify('##'),
                durationSeconds: $faker->numberBetween(30, 600),
                notes: '',
            );
        }

        return new LeadDTO(
            id: $leadId,
            firstName: $faker->firstName(),
            lastName: $faker->lastName(),
            phone: $faker->numerify('##########'),
            phoneCode: '1',
            status: $faker->randomElement($statuses),
            email: $faker->safeEmail(),
            address: $faker->streetAddress().', '.$faker->city().', '.$faker->stateAbbr().' '.$faker->postcode(),
            notes: '',
            calledCount: $faker->numberBetween(0, 10),
            previousDispositions: $previousDispositions,
            customFields: [
                'vendor_lead_code' => $faker->bothify('VLC-#####??'),
                'source_id' => $faker->bothify('SRC-???##'),
            ],
        );
    }
}
