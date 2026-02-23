<?php

namespace App\Http\Controllers\Agent;

use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\LoginToCampaignRequest;
use App\Http\Requests\Agent\UpdateAgentStatusRequest;
use App\Models\VicidialCampaign;
use App\Services\VicidialAgentService;
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

    public function store(LoginToCampaignRequest $request, VicidialAgentService $agentService): RedirectResponse
    {
        $user = $request->user();
        $campaignId = $request->validated('campaign_id');

        $campaign = VicidialCampaign::active()->find($campaignId);

        $sessionData = $agentService->login(
            $user->vicidial_user,
            $user->vicidial_phone_login,
            $campaignId,
        );

        $user->agentSession()->create([
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign?->campaign_name,
            'server_ip' => $sessionData['server_ip'],
            'conf_exten' => $sessionData['conf_exten'],
            'session_name' => $sessionData['session_name'],
            'agent_log_id' => $sessionData['agent_log_id'],
            'user_group' => $sessionData['user_group'],
            'status' => 'paused',
        ]);

        AgentStatusChanged::dispatch($user->id, 'paused', $campaignId);

        return to_route('agent.workspace');
    }

    public function destroy(Request $request, VicidialAgentService $agentService): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if ($session) {
            $agentService->logout($session, $user->vicidial_user);
            AgentStatusChanged::dispatch($user->id, 'logged_out', $session->campaign_id);
            $session->delete();
        }

        return to_route('agent.campaigns');
    }

    public function updateStatus(UpdateAgentStatusRequest $request, VicidialAgentService $agentService): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $status = $request->validated('status');
        $pauseCode = $request->validated('pause_code') ?? '';

        if ($status === 'paused') {
            $agentService->setPaused($session, $user->vicidial_user, $pauseCode);
        } else {
            $agentService->setReady($session, $user->vicidial_user);
        }

        $session->update(['status' => $status]);

        AgentStatusChanged::dispatch($user->id, $status, $session->campaign_id);

        return to_route('agent.workspace');
    }
}
