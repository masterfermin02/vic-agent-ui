<?php

namespace App\Http\Controllers\Agent;

use App\Contracts\LeadRepository;
use App\Data\AgentPerformanceDTO;
use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ManualDialRequest;
use App\Http\Requests\Agent\SaveDispositionRequest;
use App\Models\VicidialDisposition;
use App\Services\VicidialAgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $lead = $session->current_lead_id
            ? rescue(fn () => app(LeadRepository::class)->findByLeadId((int) $session->current_lead_id))
            : null;

        $performance = rescue(fn () => $this->loadPerformance($request->user()->vicidial_user, $session));

        return Inertia::render('agent/Workspace', [
            'session' => $session,
            'dispositions' => $dispositions,
            'lead' => $lead,
            'performance' => $performance,
        ]);
    }

    private function loadPerformance(string $vicidialUser, \App\Models\AgentSession $session): AgentPerformanceDTO
    {
        $vdb = DB::connection('vicidial');
        $today = today()->toDateString();

        $callsToday = (int) $vdb->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->value('calls_today');

        $talkStats = $vdb->table('vicidial_log')
            ->where('user', $vicidialUser)
            ->whereDate('call_date', $today)
            ->selectRaw('COALESCE(SUM(length_in_sec), 0) as total_talk, COALESCE(AVG(length_in_sec), 0) as avg_talk')
            ->first();

        $saleStatuses = VicidialDisposition::forCampaign($session->campaign_id)
            ->where('sale', 'Y')
            ->pluck('status');

        $conversions = $saleStatuses->isNotEmpty()
            ? $vdb->table('vicidial_log')
                ->where('user', $vicidialUser)
                ->whereDate('call_date', $today)
                ->whereIn('status', $saleStatuses)
                ->count()
            : 0;

        return new AgentPerformanceDTO(
            callsToday: $callsToday,
            totalTalkSeconds: (int) ($talkStats->total_talk ?? 0),
            avgDurationSeconds: (int) round($talkStats->avg_talk ?? 0),
            conversionRate: $callsToday > 0 ? round($conversions / $callsToday * 100, 1) : 0.0,
        );
    }

    public function disposition(SaveDispositionRequest $request, VicidialAgentService $service): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $service->sendDisposition(
            session: $session,
            vicidialUser: $user->vicidial_user,
            status: $request->validated('status'),
        );

        $session->update([
            'status' => 'paused',
            'asterisk_channel' => null,
            'current_lead_id' => null,
            'current_phone' => null,
            'current_lead_name' => null,
            'call_started_at' => null,
        ]);

        AgentStatusChanged::dispatch($user->id, 'paused', $session->campaign_id);

        return to_route('agent.workspace');
    }

    public function hangup(Request $request, VicidialAgentService $service): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $service->hangupCall($session, $user->vicidial_user);

        $session->update(['status' => 'wrapup']);

        AgentStatusChanged::dispatch($user->id, 'wrapup', $session->campaign_id);

        return to_route('agent.workspace');
    }

    public function dial(ManualDialRequest $request, VicidialAgentService $service): RedirectResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return to_route('agent.campaigns');
        }

        $result = $service->manualDial(
            session: $session,
            vicidialUser: $user->vicidial_user,
            phoneNumber: $request->validated('phone'),
            phoneCode: $request->validated('phone_code') ?? '1',
            leadId: $request->validated('lead_id'),
        );

        $session->update([
            'status' => 'incall',
            'asterisk_channel' => $result['caller_id'],
            'current_lead_id' => $result['lead_id'],
            'current_phone' => $request->validated('phone'),
            'call_started_at' => now(),
        ]);

        AgentStatusChanged::dispatch($user->id, 'incall', $session->campaign_id);

        return to_route('agent.workspace');
    }
}
