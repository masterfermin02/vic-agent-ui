<?php

use App\Events\AgentStatusChanged;
use App\Models\AgentSession;
use App\Models\User;
use App\Services\VicidialApiService;
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

    mock(VicidialApiService::class)
        ->shouldReceive('sendDisposition')
        ->once()
        ->andReturn('disposition--OK');

    $this->actingAs($user)
        ->post('/agent/call/disposition', ['status' => 'DC'])
        ->assertRedirect('/agent/workspace');

    $refreshed = $session->fresh();
    expect($refreshed->status)->toBe('ready');
    expect($refreshed->current_lead_id)->toBeNull();

    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->status === 'ready');
});

it('initiates manual dial', function () {
    $session = AgentSession::factory()->create();
    $user = $session->user;

    mock(VicidialApiService::class)
        ->shouldReceive('manualDial')
        ->once()
        ->andReturn('dial--OK');

    $this->actingAs($user)
        ->post('/agent/call/dial', ['phone' => '5551234567'])
        ->assertRedirect('/agent/workspace');
});

it('validates phone number is required for manual dial', function () {
    $session = AgentSession::factory()->create();

    $this->actingAs($session->user)
        ->post('/agent/call/dial', ['phone' => ''])
        ->assertSessionHasErrors('phone');
});
