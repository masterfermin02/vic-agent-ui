<?php

use App\Events\AgentStatusChanged;
use App\Models\AgentSession;
use App\Models\User;
use App\Services\VicidialAgentService;
use Illuminate\Support\Facades\Event;
use Tests\TestSupport\WithVicidialDatabase;

use function Pest\Laravel\mock;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class, WithVicidialDatabase::class);

beforeEach(function (): void {
    $this->setUpVicidialDatabase();
});

it('redirects guests away from workspace', function () {
    $this->get('/agent/workspace')->assertRedirect('/login');
});

it('redirects to campaigns if no active session', function () {
    $user = User::factory()->vicidialCredentials()->create();

    $this->actingAs($user)->get('/agent/workspace')->assertRedirect('/agent/campaigns');
});

it('shows workspace to agent with active session', function () {
    $session = AgentSession::factory()->create();

    $this->actingAs($session->user)->get('/agent/workspace')->assertSuccessful();
});

it('saves disposition and resets session to ready', function () {
    Event::fake();

    $session = AgentSession::factory()->wrapup()->create([
        'current_lead_id' => '12345',
    ]);
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('sendDisposition')
        ->once();

    $this->actingAs($user)
        ->post('/agent/call/disposition', ['status' => 'DC'])
        ->assertRedirect('/agent/workspace');

    $refreshed = $session->fresh();
    expect($refreshed->status)->toBe('paused');
    expect($refreshed->current_lead_id)->toBeNull();

    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->status === 'paused');
});

it('initiates manual dial', function () {
    Event::fake();

    $session = AgentSession::factory()->create();
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('manualDial')
        ->once()
        ->andReturn(['caller_id' => 'MTEST0001', 'lead_id' => 100]);

    $this->actingAs($user)
        ->post('/agent/call/dial', ['phone' => '5551234567'])
        ->assertRedirect('/agent/workspace');

    expect($session->fresh()->status)->toBe('incall');
    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->status === 'incall');
});

it('validates phone number is required for manual dial', function () {
    $session = AgentSession::factory()->create();

    $this->actingAs($session->user)
        ->post('/agent/call/dial', ['phone' => ''])
        ->assertSessionHasErrors('phone');
});

it('rings softphone for active session', function () {
    $session = AgentSession::factory()->create(['status' => 'paused']);
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('ringAgentPhone')
        ->once()
        ->withArgs(fn ($incomingSession, $phoneLogin) => $incomingSession->id === $session->id && $phoneLogin === $user->vicidial_phone_login);

    $this->actingAs($user)
        ->post('/agent/call/ring-softphone')
        ->assertNoContent();
});
