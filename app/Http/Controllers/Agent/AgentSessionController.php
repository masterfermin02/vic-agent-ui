<?php

namespace App\Http\Controllers\Agent;

use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\LoginToCampaignRequest;
use App\Http\Requests\Agent\UpdateAgentStatusRequest;
use App\Models\VicidialCampaign;
use App\Services\VicidialApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentSessionController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->agentSession()->exists()) {
            return to_route('agent.workspace');
        }

        $campaigns = VicidialCampaign::active()->get();

        return Inertia::render('agent/CampaignSelect', [
            'campaigns' => $campaigns,
        ]);
    }

    public function store(LoginToCampaignRequest $request, VicidialApiService $api): RedirectResponse
    {
        $user = $request->user();
        $campaignId = $request->validated('campaign_id');

        $campaign = VicidialCampaign::active()->find($campaignId);

        $api->agentLogin(
            $user->vicidial_user,
            $user->vicidial_pass,
            $user->vicidial_phone_login,
            $user->vicidial_phone_pass,
            $campaignId,
        );

        $user->agentSession()->create([
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign?->campaign_name,
            'status' => 'ready',
        ]);

        AgentStatusChanged::dispatch($user->id, 'ready', $campaignId);

        return to_route('agent.workspace');
    }

    public function destroy(Request $request, VicidialApiService $api): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if ($session) {
            $api->agentLogout($user->vicidial_user, $user->vicidial_pass, $session->campaign_id);
            AgentStatusChanged::dispatch($user->id, 'logged_out', $session->campaign_id);
            $session->delete();
        }

        return to_route('agent.campaigns');
    }

    public function updateStatus(UpdateAgentStatusRequest $request, VicidialApiService $api): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $status = $request->validated('status');
        $pauseCode = $request->validated('pause_code') ?? '';

        if ($status === 'paused') {
            $api->setPauseCode($user->vicidial_user, $user->vicidial_pass, $session->campaign_id, $pauseCode);
        } else {
            $api->setPauseCode($user->vicidial_user, $user->vicidial_pass, $session->campaign_id);
        }

        $session->update(['status' => $status]);

        AgentStatusChanged::dispatch($user->id, $status, $session->campaign_id);

        return to_route('agent.workspace');
    }
}
