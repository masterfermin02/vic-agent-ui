<?php

use App\Contracts\LeadRepository;
use App\Data\LeadDTO;
use App\Models\AgentSession;
use Illuminate\Support\Facades\DB;
use Tests\TestSupport\WithVicidialDatabase;

use function Pest\Laravel\mock;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class, WithVicidialDatabase::class);

beforeEach(function (): void {
    $this->setUpVicidialDatabase();
});

it('passes null script when campaign has no script', function () {
    DB::connection('vicidial')->table('vicidial_campaigns')->insert([
        'campaign_id' => 'NOCAMP',
        'campaign_name' => 'No Script Campaign',
        'active' => 'Y',
        'dial_method' => 'MANUAL',
        'campaign_script' => '',
    ]);

    $session = AgentSession::factory()->create([
        'campaign_id' => 'NOCAMP',
        'current_lead_id' => null,
    ]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('script', null)
        );
});

it('passes script data when campaign has a script', function () {
    DB::connection('vicidial')->table('vicidial_scripts')->insert([
        'script_id' => 'MYSCRIPT',
        'script_name' => 'Welcome Script',
        'script_body' => '<p>Hello there!</p>',
        'active' => 'Y',
    ]);

    DB::connection('vicidial')->table('vicidial_campaigns')->insert([
        'campaign_id' => 'SCRCAMP',
        'campaign_name' => 'Scripted Campaign',
        'active' => 'Y',
        'dial_method' => 'MANUAL',
        'campaign_script' => 'MYSCRIPT',
    ]);

    $session = AgentSession::factory()->create([
        'campaign_id' => 'SCRCAMP',
        'current_lead_id' => null,
    ]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('script.name', 'Welcome Script')
            ->where('script.body', '<p>Hello there!</p>')
        );
});

it('substitutes lead variables in script body', function () {
    DB::connection('vicidial')->table('vicidial_scripts')->insert([
        'script_id' => 'VARSCRIPT',
        'script_name' => 'Variable Script',
        'script_body' => 'Hello {--lead_first_name--} {--lead_last_name--}, your phone is {--lead_phone_number--} and email is {--lead_email--}. Campaign: {--campaign_id--}',
        'active' => 'Y',
    ]);

    DB::connection('vicidial')->table('vicidial_campaigns')->insert([
        'campaign_id' => 'VARCAMP',
        'campaign_name' => 'Variable Campaign',
        'active' => 'Y',
        'dial_method' => 'MANUAL',
        'campaign_script' => 'VARSCRIPT',
    ]);

    $leadId = 77;

    $fakeLeadDTO = new LeadDTO(
        id: $leadId,
        firstName: 'Alice',
        lastName: 'Smith',
        phone: '5559876543',
        phoneCode: '1',
        status: 'NEW',
        email: 'alice@example.com',
        address: '',
        notes: '',
        calledCount: 0,
        previousDispositions: [],
        customFields: [],
    );

    mock(LeadRepository::class)
        ->shouldReceive('findByLeadId')
        ->once()
        ->with($leadId)
        ->andReturn($fakeLeadDTO);

    $session = AgentSession::factory()->create([
        'campaign_id' => 'VARCAMP',
        'current_lead_id' => (string) $leadId,
    ]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('script.body', 'Hello Alice Smith, your phone is 5559876543 and email is alice@example.com. Campaign: VARCAMP')
        );
});

it('passes null script when script is inactive', function () {
    DB::connection('vicidial')->table('vicidial_scripts')->insert([
        'script_id' => 'INACTIVE',
        'script_name' => 'Inactive Script',
        'script_body' => '<p>You should not see this.</p>',
        'active' => 'N',
    ]);

    DB::connection('vicidial')->table('vicidial_campaigns')->insert([
        'campaign_id' => 'INACTCAMP',
        'campaign_name' => 'Inactive Script Campaign',
        'active' => 'Y',
        'dial_method' => 'MANUAL',
        'campaign_script' => 'INACTIVE',
    ]);

    $session = AgentSession::factory()->create([
        'campaign_id' => 'INACTCAMP',
        'current_lead_id' => null,
    ]);

    $this->actingAs($session->user)
        ->get('/agent/workspace')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agent/Workspace')
            ->where('script', null)
        );
});
