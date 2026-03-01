<?php

use App\Contracts\LeadRepository;
use App\Data\DispositionRecord;
use App\Data\LeadDTO;
use App\Models\AgentSession;
use Tests\TestSupport\WithVicidialDatabase;

use function Pest\Laravel\mock;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class, WithVicidialDatabase::class);

beforeEach(function (): void {
    $this->setUpVicidialDatabase();
});

it('passes null lead to workspace when no active lead', function () {
    $session = AgentSession::factory()->create(['current_lead_id' => null]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('lead', null)
        );
});

it('passes lead data to workspace when current_lead_id is set', function () {
    $leadId = 42;

    $fakeLeadDTO = new LeadDTO(
        id: $leadId,
        firstName: 'Jane',
        lastName: 'Doe',
        phone: '5551234567',
        phoneCode: '1',
        status: 'NEW',
        email: 'jane@example.com',
        address: '123 Main St, Springfield, IL 62701',
        notes: 'Test notes',
        calledCount: 2,
        previousDispositions: [
            new DispositionRecord(
                calledAt: '2025-01-01 10:00:00',
                status: 'NI',
                agentId: 'agent01',
                durationSeconds: 120,
                notes: '',
            ),
        ],
        customFields: ['vendor_lead_code' => 'VLC-001'],
    );

    mock(LeadRepository::class)
        ->shouldReceive('findByLeadId')
        ->once()
        ->with($leadId)
        ->andReturn($fakeLeadDTO);

    $session = AgentSession::factory()->create(['current_lead_id' => (string) $leadId]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('lead.id', $leadId)
            ->where('lead.firstName', 'Jane')
            ->where('lead.lastName', 'Doe')
        );
});

it('passes null lead when repository throws an exception', function () {
    $leadId = 999;

    mock(LeadRepository::class)
        ->shouldReceive('findByLeadId')
        ->once()
        ->with($leadId)
        ->andThrow(new RuntimeException('Lead not found'));

    $session = AgentSession::factory()->create(['current_lead_id' => (string) $leadId]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('lead', null)
        );
});
