<?php

use App\Data\DispositionRecord;
use App\Data\LeadDTO;
use App\Repositories\AsteriskLeadRepository;

it('returns a valid LeadDTO for a given lead id', function () {
    $repo = new AsteriskLeadRepository;
    $lead = $repo->findByLeadId(100);

    expect($lead)->toBeInstanceOf(LeadDTO::class);
    expect($lead->id)->toBe(100);
    expect($lead->firstName)->not->toBeEmpty();
    expect($lead->lastName)->not->toBeEmpty();
    expect($lead->phone)->not->toBeEmpty();
    expect($lead->phoneCode)->toBe('1');
    expect($lead->email)->not->toBeEmpty();
    expect($lead->previousDispositions)->toHaveCount(3);
    expect($lead->previousDispositions[0])->toBeInstanceOf(DispositionRecord::class);
    expect($lead->customFields)->toHaveKeys(['vendor_lead_code', 'source_id']);
});

it('returns consistent data for the same lead id', function () {
    $repo = new AsteriskLeadRepository;

    $first = $repo->findByLeadId(42);
    $second = $repo->findByLeadId(42);

    expect($first->firstName)->toBe($second->firstName);
    expect($first->lastName)->toBe($second->lastName);
    expect($first->phone)->toBe($second->phone);
    expect($first->status)->toBe($second->status);
});

it('returns different data for different lead ids', function () {
    $repo = new AsteriskLeadRepository;

    $leadA = $repo->findByLeadId(1);
    $leadB = $repo->findByLeadId(2);

    // Different seeds should produce different names (not guaranteed for all pairs but extremely likely)
    expect($leadA->firstName.$leadA->lastName)->not->toBe($leadB->firstName.$leadB->lastName);
});

it('disposition records have valid structure', function () {
    $repo = new AsteriskLeadRepository;
    $lead = $repo->findByLeadId(7);

    foreach ($lead->previousDispositions as $dispo) {
        expect($dispo)->toBeInstanceOf(DispositionRecord::class);
        expect($dispo->calledAt)->not->toBeEmpty();
        expect($dispo->status)->not->toBeEmpty();
        expect($dispo->agentId)->not->toBeEmpty();
        expect($dispo->durationSeconds)->toBeGreaterThanOrEqual(0);
    }
});
