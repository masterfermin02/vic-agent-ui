<?php

namespace App\Http\Controllers\Agent;

use App\Contracts\LeadRepository;
use App\Data\AgentPerformanceDTO;
use App\Data\SipConfigDTO;
use App\Events\AgentStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ManualDialRequest;
use App\Http\Requests\Agent\SaveDispositionRequest;
use App\Models\VicidialDisposition;
use App\Services\VicidialAgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

        $sip = rescue(fn () => $this->loadSipConfig(
            $request->user()->vicidial_phone_login,
            $request->user()->vicidial_phone_pass ?? '',
            $session->server_ip,
            $request->isSecure(),
        ));

        return Inertia::render('agent/Workspace', [
            'session' => $session,
            'dispositions' => $dispositions,
            'lead' => $lead,
            'performance' => $performance,
            'sip' => $sip,
        ]);
    }

    private function loadSipConfig(string $phoneLogin, string $phonePass, string $serverIp, bool $isSecurePage): SipConfigDTO
    {
        $vdb = DB::connection('vicidial');

        $phone = $vdb->table('phones')
            ->where('login', $phoneLogin)
            ->where('active', 'Y')
            ->first([
                'extension', 'pass', 'conf_secret', 'server_ip', 'use_external_server_ip',
                'codecs_list', 'webphone_auto_answer', 'webphone_mute',
                'webphone_dialpad', 'webphone_debug',
            ]);

        if (! $phone) {
            throw new \RuntimeException("Phone record not found for login '{$phoneLogin}'.");
        }

        $server = $vdb->table('servers')
            ->where('server_ip', $serverIp)
            ->first(['server_ip', 'web_socket_url', 'external_web_socket_url']);

        // Prefer external_web_socket_url when use_external_server_ip is set,
        // then fall back to web_socket_url, then construct a default from server_ip.
        $wsUrl = match (true) {
            $phone->use_external_server_ip === 'Y' && strlen((string) $server?->external_web_socket_url) > 5 => $server->external_web_socket_url,
            strlen((string) $server?->web_socket_url) > 5 => $server->web_socket_url,
            default => "wss://{$serverIp}:8089/ws",
        };
        $wsUrl = $this->normalizeWebSocketUrl($wsUrl, $serverIp, $isSecurePage);

        // Split and clean codecs (mirrors vicidial.php: remove hyphens, spaces, ampersands).
        $rawCodecs = (string) ($phone->codecs_list ?? '');
        $codecs = array_values(array_filter(
            preg_split('/[\s,]+/', preg_replace('/[-&]/', '', $rawCodecs) ?? '') ?? [],
        ));

        // Prefer the agent-entered phone password first, then VICIdial phones.pass,
        // then conf_secret as last-resort compatibility fallback.
        $userPhonePass = trim($phonePass);
        $phoneDbPass = trim((string) ($phone->pass ?? ''));
        $confSecret = trim((string) ($phone->conf_secret ?? ''));

        $sipPassword = match (true) {
            $userPhonePass !== '' => $userPhonePass,
            $phoneDbPass !== '' => $phoneDbPass,
            default => $confSecret,
        };

        $sipAltPassword = match (true) {
            $phoneDbPass !== '' && $phoneDbPass !== $sipPassword => $phoneDbPass,
            $confSecret !== '' && $confSecret !== $sipPassword => $confSecret,
            default => null,
        };

        $rawExtension = trim((string) $phone->extension);
        $extension = preg_replace('/^[A-Za-z0-9_+-]+\//', '', $rawExtension) ?: $rawExtension;

        $wsHost = parse_url($wsUrl, PHP_URL_HOST);
        $sipServerHost = is_string($wsHost) && $wsHost !== '' ? $wsHost : $serverIp;

        return new SipConfigDTO(
            extension: $extension,
            sipAuthUser: $extension,
            sipAltAuthUser: $phoneLogin !== $extension ? $phoneLogin : null,
            sipPassword: $sipPassword,
            sipAltPassword: $sipAltPassword,
            sipServer: $sipServerHost,
            wsUrl: $wsUrl,
            codecs: $codecs,
            autoAnswer: $phone->webphone_auto_answer === 'Y',
            mute: $phone->webphone_mute === 'Y',
            dialpad: $phone->webphone_dialpad === 'Y',
            debug: $phone->webphone_debug === 'Y',
        );
    }

    private function normalizeWebSocketUrl(string $wsUrl, string $serverIp, bool $isSecurePage): string
    {
        $parts = parse_url($wsUrl);
        if (! is_array($parts)) {
            return $wsUrl;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/ws');
        $port = (int) ($parts['port'] ?? ($scheme === 'wss' ? 8089 : 8088));

        if ($host === '') {
            return $wsUrl;
        }

        // Most VICIdial/Asterisk LAN installs use self-signed certs on 8089.
        // Browsers reject those for WSS; switch to WS on 8088 for private hosts.
        $isPrivateHost = $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === $serverIp
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        if (! $isSecurePage && $scheme === 'wss' && $isPrivateHost) {
            $wsPort = $port === 8089 ? 8088 : $port;

            return "ws://{$host}:{$wsPort}{$path}";
        }

        return $wsUrl;
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

    public function ringSoftphone(Request $request, VicidialAgentService $service): HttpResponse
    {
        $user = $request->user();
        $session = $user->agentSession;

        if (! $session) {
            return response()->noContent();
        }

        if (in_array($session->status, ['paused', 'ready', 'waiting'], true)) {
            $service->ringAgentPhone($session, $user->vicidial_phone_login);
        }

        return response()->noContent();
    }
}
