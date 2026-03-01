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

it('redirects guests away from campaigns page', function () {
    $this->get('/agent/campaigns')->assertRedirect('/login');
});

it('shows campaign selection page to authenticated users', function () {
    $user = User::factory()->vicidialCredentials()->create();

    $this->actingAs($user)->get('/agent/campaigns')->assertSuccessful();
});

it('redirects to workspace if session already active', function () {
    $session = AgentSession::factory()->create();
    $user = $session->user;

    $this->actingAs($user)->get('/agent/campaigns')->assertRedirect('/agent/workspace');
});

it('logs agent into campaign and creates session', function () {
    Event::fake();

    $user = User::factory()->vicidialCredentials()->create();

    mock(VicidialAgentService::class)
        ->shouldReceive('login')
        ->once()
        ->andReturn([
            'server_ip' => '127.0.0.1',
            'conf_exten' => 8001,
            'session_name' => 'test_session',
            'agent_log_id' => 1,
            'user_group' => 'AGENTS',
        ]);

    $this->actingAs($user)
        ->post('/agent/session', ['campaign_id' => 'TEST'])
        ->assertRedirect('/agent/workspace');

    expect($user->agentSession)->not->toBeNull();
    expect($user->fresh()->agentSession->campaign_id)->toBe('TEST');

    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->userId === $user->id && $event->status === 'paused');
});

it('updates existing session when logging into a new campaign', function () {
    Event::fake();

    $session = AgentSession::factory()->create(['campaign_id' => 'OLD']);
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('login')
        ->once()
        ->andReturn([
            'server_ip' => '127.0.0.1',
            'conf_exten' => 8001,
            'session_name' => 'test_session',
            'agent_log_id' => 1,
            'user_group' => 'AGENTS',
        ]);

    $this->actingAs($user)
        ->post('/agent/session', ['campaign_id' => 'TEST'])
        ->assertRedirect('/agent/workspace');

    expect(AgentSession::where('user_id', $user->id)->count())->toBe(1);
    expect($user->fresh()->agentSession->campaign_id)->toBe('TEST');
});

it('logs agent out and deletes session', function () {
    Event::fake();

    $session = AgentSession::factory()->create();
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('logout')
        ->once();

    $this->actingAs($user)
        ->delete('/agent/session')
        ->assertRedirect('/agent/campaigns');

    expect(AgentSession::where('user_id', $user->id)->exists())->toBeFalse();

    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->status === 'logged_out');
});

it('updates agent status to paused', function () {
    Event::fake();

    $session = AgentSession::factory()->create();
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('setPaused')
        ->once();

    $this->actingAs($user)
        ->put('/agent/status', ['status' => 'paused'])
        ->assertRedirect('/agent/workspace');

    expect($session->fresh()->status)->toBe('paused');

    Event::assertDispatched(AgentStatusChanged::class, fn ($event) => $event->status === 'paused');
});

it('updates agent status to ready', function () {
    Event::fake();

    $session = AgentSession::factory()->create(['status' => 'paused']);
    $user = $session->user;

    mock(VicidialAgentService::class)
        ->shouldReceive('setReady')
        ->once()
        ->andReturn(2);

    $this->actingAs($user)
        ->put('/agent/status', ['status' => 'ready'])
        ->assertRedirect('/agent/workspace');

    expect($session->fresh()->status)->toBe('ready');
});
