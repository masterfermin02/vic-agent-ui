<?php

namespace App\Http\Controllers\Agent;

use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ManualDialRequest;
use App\Http\Requests\Agent\SaveDispositionRequest;
use App\Models\VicidialDisposition;
use App\Services\VicidialApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallController extends Controller
{
    public function workspace(Request $request): Response|RedirectResponse
    {
        $session = $request->user()->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $dispositions = VicidialDisposition::forCampaign($session->campaign_id)
            ->selectable()
            ->get();

        return Inertia::render('agent/Workspace', [
            'session' => $session,
            'dispositions' => $dispositions,
        ]);
    }

    public function disposition(SaveDispositionRequest $request, VicidialApiService $api): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $api->sendDisposition(
            $user->vicidial_user,
            $user->vicidial_pass,
            $session->campaign_id,
            $session->current_lead_id ?? '',
            $request->validated('status'),
        );

        $session->update([
            'status' => 'ready',
            'current_lead_id' => null,
            'current_phone' => null,
            'current_lead_name' => null,
            'call_started_at' => null,
        ]);

        AgentStatusChanged::dispatch($user->id, 'ready', $session->campaign_id);

        return to_route('agent.workspace');
    }

    public function dial(ManualDialRequest $request, VicidialApiService $api): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $api->manualDial(
            $user->vicidial_user,
            $user->vicidial_pass,
            $session->campaign_id,
            $request->validated('phone'),
        );

        return to_route('agent.workspace');
    }
}
