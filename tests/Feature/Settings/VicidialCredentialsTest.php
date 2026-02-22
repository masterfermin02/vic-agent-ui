<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests trying to update vicidial credentials', function () {
    $this->patch('/settings/profile/vicidial', [])->assertRedirect('/login');
});

it('updates vicidial credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile/vicidial', [
            'vicidial_user' => 'agent1',
            'vicidial_pass' => 'secret',
            'vicidial_phone_login' => '101',
            'vicidial_phone_pass' => 'phonepass',
        ])
        ->assertRedirect('/settings/profile');

    $refreshed = $user->fresh();
    expect($refreshed->vicidial_user)->toBe('agent1');
    expect($refreshed->vicidial_phone_login)->toBe('101');
});

it('allows partial vicidial credential updates', function () {
    $user = User::factory()->vicidialCredentials()->create();

    $this->actingAs($user)
        ->patch('/settings/profile/vicidial', [
            'vicidial_user' => 'newagent',
        ])
        ->assertRedirect('/settings/profile');

    expect($user->fresh()->vicidial_user)->toBe('newagent');
});

it('does not expose vicidial passwords in shared auth data', function () {
    $user = User::factory()->vicidialCredentials()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertSuccessful();

    $page = json_decode(
        substr($response->content(), strpos($response->content(), 'data-page="') + 11, -2),
        true
    );

    $authUser = $page['props']['auth']['user'] ?? [];

    expect($authUser)->not->toHaveKey('vicidial_pass');
    expect($authUser)->not->toHaveKey('vicidial_phone_pass');
});
