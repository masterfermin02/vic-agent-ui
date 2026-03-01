<?php

namespace App\Services;

use App\Models\AgentSession;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VicidialAgentService
{
    private function db(): ConnectionInterface
    {
        return DB::connection('vicidial');
    }

    /**
     * Log an agent into a campaign by writing directly to the VICIdial database
     * and triggering an Asterisk Originate to ring the agent's SIP phone (Zoiper).
     *
     * @return array{server_ip: string, conf_exten: int, session_name: string, agent_log_id: int, user_group: string}
     */
    public function login(string $vicidialUser, string $phoneLogin, string $campaignId): array
    {
        $db = $this->db();
        $now = now();
        $epoch = $now->unix();

        // Fetch agent user details (user_group, user_level).
        $vicUser = $db->table('vicidial_users')
            ->where('user', $vicidialUser)
            ->where('active', 'Y')
            ->first();

        if (! $vicUser) {
            throw new RuntimeException("VICIdial user '{$vicidialUser}' not found or inactive.");
        }

        $userGroup = $vicUser->user_group ?? 'AGENTS';
        $userLevel = $vicUser->user_level ?? 1;

        // Fetch phone record.
        $phone = $db->table('phones')
            ->where('login', $phoneLogin)
            ->where('active', 'Y')
            ->first();

        if (! $phone) {
            throw new RuntimeException("Phone login '{$phoneLogin}' not found or inactive.");
        }

        // Build the full SIP channel string used by Asterisk (e.g. "SIP/1001").
        $extension = $phone->extension;
        $protocol = $phone->protocol ?? 'SIP';
        $sipChannel = "{$protocol}/{$extension}";
        $extContext = $phone->ext_context ?: 'default';

        // Fetch active Asterisk server.
        $server = $db->table('servers')
            ->where('active', 'Y')
            ->where('active_asterisk_server', 'Y')
            ->first();

        if (! $server) {
            throw new RuntimeException('No active Asterisk server found.');
        }

        $serverIp = $server->server_ip;

        // Clean up any stale sessions for this user.
        $db->table('vicidial_list')
            ->whereIn('status', ['QUEUE', 'INCALL'])
            ->where('user', $vicidialUser)
            ->update(['status' => 'ERI', 'user' => '']);

        $db->table('vicidial_hopper')
            ->whereIn('status', ['QUEUE', 'INCALL', 'DONE'])
            ->where('user', $vicidialUser)
            ->delete();

        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->delete();

        $db->table('vicidial_live_inbound_agents')
            ->where('user', $vicidialUser)
            ->delete();

        // Reserve an available conference room on this server.
        // VICIdial stores the full SIP channel string in conferences.extension.
        $db->table('vicidial_conferences')
            ->where('server_ip', $serverIp)
            ->where(fn ($q) => $q->whereNull('extension')->orWhere('extension', ''))
            ->limit(1)
            ->update(['extension' => $sipChannel]);

        $confExten = $db->table('vicidial_conferences')
            ->where('server_ip', $serverIp)
            ->where('extension', $sipChannel)
            ->value('conf_exten');

        if (! $confExten) {
            throw new RuntimeException('No available conference rooms on the Asterisk server.');
        }

        // Generate unique session name.
        $sessionName = $epoch.'_'.$extension.'_'.rand(1000, 9999);

        // Register web client session.
        $db->table('web_client_sessions')->insert([
            'extension' => $extension,
            'server_ip' => $serverIp,
            'program' => 'vicidial',
            'start_time' => $now->toDateTimeString(),
            'session_name' => $sessionName,
        ]);

        // Store session data.
        $db->table('vicidial_session_data')->insert([
            'session_name' => $sessionName,
            'user' => $vicidialUser,
            'campaign_id' => $campaignId,
            'server_ip' => $serverIp,
            'conf_exten' => (string) $confExten,
            'extension' => $extension,
            'login_time' => $now->toDateTimeString(),
        ]);

        // Ensure a campaign agent record exists.
        $agentCampaign = $db->table('vicidial_campaign_agents')
            ->where('user', $vicidialUser)
            ->where('campaign_id', $campaignId)
            ->first();

        $campaignWeight = $agentCampaign?->campaign_weight ?? 0;
        $campaignGrade = $agentCampaign?->campaign_grade ?? 1;

        if (! $agentCampaign) {
            $db->table('vicidial_campaign_agents')->insert([
                'user' => $vicidialUser,
                'campaign_id' => $campaignId,
                'campaign_rank' => 0,
                'campaign_weight' => 0,
                'calls_today' => 0,
                'campaign_grade' => 1,
            ]);
        }

        // Register the agent as live (starts in PAUSED with pause_code=LOGIN).
        // extension stored as full SIP channel string to match VICIdial convention.
        $db->table('vicidial_live_agents')->insert([
            'user' => $vicidialUser,
            'server_ip' => $serverIp,
            'conf_exten' => (string) $confExten,
            'extension' => $sipChannel,
            'status' => 'PAUSED',
            'lead_id' => 0,
            'campaign_id' => $campaignId,
            'uniqueid' => '',
            'callerid' => '',
            'channel' => '',
            'random_id' => rand(10000000, 19999999),
            'last_call_time' => $now->toDateTimeString(),
            'last_call_finish' => $now->toDateTimeString(),
            'user_level' => $userLevel,
            'campaign_weight' => $campaignWeight,
            'calls_today' => 0,
            'last_state_change' => $now->toDateTimeString(),
            'outbound_autodial' => 'N',
            'manager_ingroup_set' => 'N',
            'on_hook_ring_time' => $phone->phone_ring_timeout ?? 60,
            'on_hook_agent' => $phone->on_hook_agent ?? 'N',
            'campaign_grade' => $campaignGrade,
            'pause_code' => 'LOGIN',
            'last_inbound_call_time' => $now->toDateTimeString(),
            'last_inbound_call_finish' => $now->toDateTimeString(),
            'last_inbound_call_time_filtered' => $now->toDateTimeString(),
            'last_inbound_call_finish_filtered' => $now->toDateTimeString(),
        ]);

        // Create the initial agent log entry (LOGIN pause event).
        $agentLogId = $db->table('vicidial_agent_log')->insertGetId([
            'user' => $vicidialUser,
            'server_ip' => $serverIp,
            'event_time' => $now->toDateTimeString(),
            'campaign_id' => $campaignId,
            'pause_epoch' => $epoch,
            'pause_sec' => 0,
            'wait_epoch' => $epoch,
            'wait_sec' => 0,
            'user_group' => $userGroup,
            'sub_status' => 'LOGIN',
            'pause_type' => 'AGENT',
        ]);

        // Stamp the campaign with the latest login time.
        $db->table('vicidial_campaigns')
            ->where('campaign_id', $campaignId)
            ->update(['campaign_logindate' => $now->toDateTimeString()]);

        // Originate an Asterisk call to ring the agent's SIP phone (e.g. Zoiper).
        // Asterisk reads from vicidial_manager and dials $sipChannel, connecting
        // it to conf_exten in the dialplan so the agent joins the conference bridge.
        $this->originateAgentLoginCall(
            serverIp: $serverIp,
            sipChannel: $sipChannel,
            confExten: $confExten,
            extContext: $extContext,
            epoch: $epoch,
        );

        return [
            'server_ip' => $serverIp,
            'conf_exten' => $confExten,
            'session_name' => $sessionName,
            'agent_log_id' => $agentLogId,
            'user_group' => $userGroup,
        ];
    }

    /**
     * Insert an Originate command into vicidial_manager so Asterisk rings
     * the agent's SIP phone and bridges it into their conference room.
     *
     * Mirrors vicidial.php line 4978:
     *   INSERT INTO vicidial_manager ... Originate ... Channel: SIP/1001
     *   Context: default  Exten: <conf_exten>  Priority: 1
     */
    private function originateAgentLoginCall(
        string $serverIp,
        string $sipChannel,
        int $confExten,
        string $extContext,
        int $epoch,
    ): void {
        $callerId = 'S'.$epoch.'_'.$confExten;

        $this->db()->table('vicidial_manager')->insert([
            'uniqueid' => '',
            'entry_date' => now()->toDateTimeString(),
            'status' => 'NEW',
            'response' => 'N',
            'server_ip' => $serverIp,
            'channel' => '',
            'action' => 'Originate',
            'callerid' => $callerId,
            'cmd_line_b' => "Channel: {$sipChannel}",
            'cmd_line_c' => "Context: {$extContext}",
            'cmd_line_d' => "Exten: {$confExten}",
            'cmd_line_e' => 'Priority: 1',
            'cmd_line_f' => "Callerid: \"{$callerId}\" <{$callerId}>",
            'cmd_line_g' => '',
            'cmd_line_h' => '',
            'cmd_line_i' => '',
            'cmd_line_j' => '',
            'cmd_line_k' => '',
        ]);
    }

    /**
     * Transition the agent from PAUSED → READY.
     * Closes the current pause log entry and opens a new wait entry.
     *
     * @return int The new agent_log_id.
     */
    public function setReady(AgentSession $session, string $vicidialUser): int
    {
        $db = $this->db();
        $now = now();
        $epoch = $now->unix();

        // Calculate seconds spent in pause state.
        $currentLog = $db->table('vicidial_agent_log')
            ->where('agent_log_id', $session->agent_log_id)
            ->first();

        $pauseSec = $currentLog ? max(0, $epoch - (int) $currentLog->pause_epoch) : 0;

        // Move live agent to READY, clear any stale call fields.
        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->whereNotIn('status', ['QUEUE', 'INCALL'])
            ->update([
                'status' => 'READY',
                'uniqueid' => 0,
                'callerid' => '',
                'channel' => '',
                'random_id' => rand(10000000, 19999999),
                'pause_code' => '',
                'last_state_change' => $now->toDateTimeString(),
            ]);

        // Close the previous pause log entry.
        $db->table('vicidial_agent_log')
            ->where('agent_log_id', $session->agent_log_id)
            ->update(['pause_sec' => $pauseSec, 'wait_epoch' => $epoch]);

        // Open a new log entry for the READY (wait) period.
        $newAgentLogId = $db->table('vicidial_agent_log')->insertGetId([
            'user' => $vicidialUser,
            'server_ip' => $session->server_ip,
            'event_time' => $now->toDateTimeString(),
            'campaign_id' => $session->campaign_id,
            'pause_epoch' => $epoch,
            'pause_sec' => 0,
            'wait_epoch' => $epoch,
            'wait_sec' => 0,
            'user_group' => $session->user_group,
            'pause_type' => 'AGENT',
        ]);

        $session->update(['agent_log_id' => $newAgentLogId]);

        return $newAgentLogId;
    }

    /**
     * Transition the agent from READY → PAUSED.
     * Closes the current wait log entry and opens a new pause entry.
     */
    public function setPaused(AgentSession $session, string $vicidialUser, string $pauseCode = ''): void
    {
        $db = $this->db();
        $now = now();
        $epoch = $now->unix();

        // Calculate seconds spent in ready/wait state.
        $currentLog = $db->table('vicidial_agent_log')
            ->where('agent_log_id', $session->agent_log_id)
            ->first();

        $waitSec = $currentLog ? max(0, $epoch - (int) $currentLog->wait_epoch) : 0;

        // Move live agent to PAUSED.
        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->whereNotIn('status', ['QUEUE', 'INCALL'])
            ->update([
                'status' => 'PAUSED',
                'random_id' => rand(10000000, 19999999),
                'pause_code' => $pauseCode,
                'last_state_change' => $now->toDateTimeString(),
            ]);

        // Close the previous wait log entry.
        $db->table('vicidial_agent_log')
            ->where('agent_log_id', $session->agent_log_id)
            ->update(['wait_sec' => $waitSec]);

        // Open a new log entry for the PAUSED period.
        $newAgentLogId = $db->table('vicidial_agent_log')->insertGetId([
            'user' => $vicidialUser,
            'server_ip' => $session->server_ip,
            'event_time' => $now->toDateTimeString(),
            'campaign_id' => $session->campaign_id,
            'pause_epoch' => $epoch,
            'pause_sec' => 0,
            'wait_epoch' => $epoch,
            'wait_sec' => 0,
            'user_group' => $session->user_group,
            'sub_status' => $pauseCode ?: null,
            'pause_type' => 'AGENT',
        ]);

        $session->update(['agent_log_id' => $newAgentLogId]);
    }

    /**
     * Place a manual outbound call for an agent by writing directly to the VICIdial database.
     *
     * Mirrors vdc_db_query.php manDiaLonly:
     *   INSERT vicidial_manager  → Originate Channel: Local/{conf_exten}@{ext_context}/n
     *                                        Exten: {dial_prefix}{phone_code}{phone_number}
     *   INSERT vicidial_auto_calls (status=XFER, call_type=OUT)
     *   INSERT vicidial_dial_log
     *   UPDATE vicidial_live_agents → status=INCALL
     *   UPDATE vicidial_agent_log   → close wait/pause period
     *
     * @return array{caller_id: string, lead_id: int}
     */
    public function manualDial(
        AgentSession $session,
        string $vicidialUser,
        string $phoneNumber,
        string $phoneCode = '1',
        ?int $leadId = null,
    ): array {
        if (! $session->server_ip || ! $session->conf_exten) {
            throw new RuntimeException('Agent session is missing server connection data.');
        }

        $db = $this->db();
        $now = now();
        $epoch = $now->unix();
        $nowStr = $now->toDateTimeString();

        // Resolve the Asterisk dialplan context from the agent's phone record.
        $vicUser = $db->table('vicidial_users')->where('user', $vicidialUser)->first();
        $extContext = 'default';

        if ($vicUser?->phone_login) {
            $extContext = $db->table('phones')
                ->where('login', $vicUser->phone_login)
                ->value('ext_context') ?: 'default';
        }

        // Load campaign dial settings.
        $campaign = $db->table('vicidial_campaigns')
            ->where('campaign_id', $session->campaign_id)
            ->first(['dial_prefix', 'dial_timeout', 'manual_dial_list_id', 'omit_phone_code']);

        $rawPrefix = $campaign?->dial_prefix ?? '';
        // 'x' means disabled in VICIdial; fall back to empty (no prefix needed).
        $dialPrefix = (strlen($rawPrefix) > 0 && ! str_contains(strtolower($rawPrefix), 'x'))
            ? $rawPrefix
            : '';
        $dialTimeoutMs = max(10, (int) ($campaign?->dial_timeout ?? 60)) * 1000;
        $manualListId = (int) ($campaign?->manual_dial_list_id ?: 998);
        $omitPhoneCode = ($campaign?->omit_phone_code ?? 'N') === 'Y';

        // Look up an existing lead or create one in the manual list.
        if (! $leadId) {
            $leadId = (int) $db->table('vicidial_list')
                ->where('phone_number', $phoneNumber)
                ->where('list_id', $manualListId)
                ->value('lead_id');

            if (! $leadId) {
                $leadId = (int) $db->table('vicidial_list')->insertGetId([
                    'entry_date' => $nowStr,
                    'status' => 'NEW',
                    'list_id' => $manualListId,
                    'entry_list_id' => $manualListId,
                    'phone_code' => $phoneCode,
                    'phone_number' => $phoneNumber,
                    'called_since_last_reset' => 'N',
                    'called_count' => 0,
                    'gmt_offset_now' => 0,
                    'rank' => 0,
                    'owner' => '',
                ]);
            }
        }

        // Build VICIdial-style caller ID: M{mmddHHiiss}{10-digit-padded-lead-id}
        $callerId = 'M'.$now->format('mdHis').str_pad((string) $leadId, 10, '0', STR_PAD_LEFT);

        // Build the dial string sent as the Exten (outbound number to call).
        $dialString = $omitPhoneCode
            ? $dialPrefix.$phoneNumber
            : $dialPrefix.$phoneCode.$phoneNumber;

        // Local channel routes Asterisk through the agent's conference bridge.
        $localChannel = "Local/{$session->conf_exten}@{$extContext}/n";

        // Insert Originate command; Asterisk polls vicidial_manager and places the call.
        // Channel = conference bridge (agent side), Exten = customer phone number.
        $db->table('vicidial_manager')->insert([
            'uniqueid' => '',
            'entry_date' => $nowStr,
            'status' => 'NEW',
            'response' => 'N',
            'server_ip' => $session->server_ip,
            'channel' => '',
            'action' => 'Originate',
            'callerid' => $callerId,
            'cmd_line_b' => "Exten: {$dialString}",
            'cmd_line_c' => "Context: {$extContext}",
            'cmd_line_d' => "Channel: {$localChannel}",
            'cmd_line_e' => 'Priority: 1',
            'cmd_line_f' => "Callerid: {$callerId}",
            'cmd_line_g' => "Timeout: {$dialTimeoutMs}",
            'cmd_line_h' => '',
            'cmd_line_i' => '',
            'cmd_line_j' => '',
            'cmd_line_k' => '',
        ]);

        // Log the dial attempt.
        $db->table('vicidial_dial_log')->insert([
            'caller_code' => $callerId,
            'lead_id' => $leadId,
            'server_ip' => $session->server_ip,
            'call_date' => $nowStr,
            'extension' => $dialString,
            'channel' => $localChannel,
            'timeout' => $dialTimeoutMs,
            'outbound_cid' => $callerId,
            'context' => $extContext,
        ]);

        // Register the call in vicidial_auto_calls so VICIdial can track it.
        $db->table('vicidial_auto_calls')->insert([
            'server_ip' => $session->server_ip,
            'campaign_id' => $session->campaign_id,
            'status' => 'XFER',
            'lead_id' => $leadId,
            'callerid' => $callerId,
            'phone_code' => $phoneCode,
            'phone_number' => $phoneNumber,
            'call_time' => $nowStr,
            'call_type' => 'OUT',
        ]);

        // Increment calls_today and set agent status to INCALL.
        $callsToday = (int) $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->value('calls_today') + 1;

        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->update([
                'status' => 'INCALL',
                'last_call_time' => $nowStr,
                'callerid' => $callerId,
                'lead_id' => $leadId,
                'comments' => 'MANUAL',
                'calls_today' => $callsToday,
                'external_hangup' => 0,
                'external_status' => '',
                'external_pause' => '',
                'external_dial' => '',
                'last_state_change' => $nowStr,
                'pause_code' => '',
            ]);

        $db->table('vicidial_campaign_agents')
            ->where('user', $vicidialUser)
            ->where('campaign_id', $session->campaign_id)
            ->update(['calls_today' => $callsToday]);

        // Close the current wait/pause period in the agent log and stamp the call start time.
        if ($session->agent_log_id) {
            $currentLog = $db->table('vicidial_agent_log')
                ->where('agent_log_id', $session->agent_log_id)
                ->first(['pause_epoch', 'wait_epoch']);

            if ($currentLog) {
                $db->table('vicidial_agent_log')
                    ->where('agent_log_id', $session->agent_log_id)
                    ->update([
                        'pause_sec' => max(0, $epoch - (int) $currentLog->pause_epoch),
                        'wait_epoch' => $epoch,
                        'lead_id' => $leadId,
                    ]);
            }
        }

        return ['caller_id' => $callerId, 'lead_id' => $leadId];
    }

    /**
     * Hang up the customer call while the agent remains logged in.
     *
     * Mirrors manager_send.php ACTION=Hangup:
     *   1. If Asterisk has tracked the customer channel in vicidial_auto_calls,
     *      insert a Hangup command targeting it.
     *   2. Always insert a kickall Originate (Local/5555{conf_exten}) so the
     *      conference bridge is cleared even when the direct channel is unknown.
     */
    public function hangupCall(AgentSession $session, string $vicidialUser): void
    {
        if (! $session->server_ip || ! $session->conf_exten) {
            return;
        }

        $db = $this->db();
        $now = now()->toDateTimeString();
        $callerId = 'MDHU'.$session->conf_exten.'_'.time();

        // Reset external_hangup flag (matches manager_send.php Hangup handler).
        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->update(['external_hangup' => 0]);

        // Hang up only the customer channel — the agent remains in the conference bridge.
        $autoCall = $db->table('vicidial_auto_calls')
            ->where('callerid', $session->asterisk_channel)
            ->where('server_ip', $session->server_ip)
            ->first(['channel']);

        if ($autoCall?->channel) {
            $db->table('vicidial_manager')->insert([
                'uniqueid' => '',
                'entry_date' => $now,
                'status' => 'NEW',
                'response' => 'N',
                'server_ip' => $session->server_ip,
                'channel' => '',
                'action' => 'Hangup',
                'callerid' => $callerId,
                'cmd_line_b' => "Channel: {$autoCall->channel}",
                'cmd_line_c' => '',
                'cmd_line_d' => '',
                'cmd_line_e' => '',
                'cmd_line_f' => '',
                'cmd_line_g' => '',
                'cmd_line_h' => '',
                'cmd_line_i' => '',
                'cmd_line_j' => '',
                'cmd_line_k' => '',
            ]);
        }
    }

    /**
     * Submit a call disposition after the call ends.
     *
     * Mirrors vdc_db_query.php updateDISPO for outbound manual calls:
     *   - Updates vicidial_list lead status
     *   - Inserts or updates vicidial_log
     *   - Removes the vicidial_auto_calls entry
     *   - Closes the current vicidial_agent_log entry with dispo timing
     *   - Opens a new vicidial_agent_log entry for the post-dispo PAUSED state
     */
    public function sendDisposition(AgentSession $session, string $vicidialUser, string $status): void
    {
        $db = $this->db();
        $now = now();
        $epoch = $now->unix();
        $nowStr = $now->toDateTimeString();
        $leadId = (int) $session->current_lead_id;

        // 1. Set live agent to PAUSED and clear call fields.
        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->update([
                'status' => 'PAUSED',
                'callerid' => '',
                'lead_id' => 0,
                'channel' => '',
                'comments' => '',
                'external_hangup' => 0,
                'external_status' => '',
                'last_state_change' => $nowStr,
                'pause_code' => '',
            ]);

        // 2. Stamp the lead with the disposition in vicidial_list.
        if ($leadId) {
            $db->table('vicidial_list')
                ->where('lead_id', $leadId)
                ->update(['status' => $status, 'user' => $vicidialUser]);
        }

        // 3. Insert or update vicidial_log for this outbound manual call.
        if ($leadId && $session->asterisk_channel) {
            $lead = $db->table('vicidial_list')
                ->where('lead_id', $leadId)
                ->first(['list_id', 'phone_number', 'phone_code', 'called_count']);

            $fourHoursAgo = now()->subHours(4)->toDateTimeString();

            $existingLog = $db->table('vicidial_log')
                ->where('lead_id', $leadId)
                ->where('call_date', '>', $fourHoursAgo)
                ->orderByDesc('uniqueid')
                ->first(['uniqueid']);

            if ($existingLog) {
                $db->table('vicidial_log')
                    ->where('uniqueid', $existingLog->uniqueid)
                    ->update(['status' => $status, 'user' => $vicidialUser]);
            } else {
                // Build a fake uniqueid matching VICIdial convention: epoch.padded_lead_id
                $fakeUniqueId = $epoch.'.'.str_pad((string) $leadId, 9, '0', STR_PAD_LEFT);

                $db->table('vicidial_log')->insertOrIgnore([
                    'uniqueid' => $fakeUniqueId,
                    'lead_id' => $leadId,
                    'list_id' => $lead?->list_id ?? 0,
                    'campaign_id' => $session->campaign_id,
                    'call_date' => $nowStr,
                    'start_epoch' => $epoch,
                    'end_epoch' => $epoch,
                    'length_in_sec' => 0,
                    'status' => $status,
                    'phone_code' => $lead?->phone_code ?? '1',
                    'phone_number' => $lead?->phone_number ?? '',
                    'user' => $vicidialUser,
                    'comments' => 'MANUAL',
                    'processed' => 'N',
                    'user_group' => $session->user_group ?? '',
                    'term_reason' => 'AGENT',
                    'alt_dial' => 'MAIN',
                    'called_count' => $lead?->called_count ?? 0,
                ]);
            }

            // 4. Remove the auto_calls entry for this call.
            $db->table('vicidial_auto_calls')
                ->where('callerid', $session->asterisk_channel)
                ->delete();
        }

        // 5. Close the current agent_log entry with dispo timing data.
        if ($session->agent_log_id) {
            $currentLog = $db->table('vicidial_agent_log')
                ->where('agent_log_id', $session->agent_log_id)
                ->first(['talk_epoch', 'wait_epoch', 'dispo_epoch', 'dispo_sec']);

            if ($currentLog) {
                $updates = ['status' => $status];

                $talkEpoch = (int) $currentLog->talk_epoch;
                $dispoEpoch = (int) $currentLog->dispo_epoch;

                // If the call was never bridged (e.g. no answer), set talk_epoch now.
                if ($talkEpoch < 1000) {
                    $talkEpoch = $epoch;
                    $updates['talk_epoch'] = $talkEpoch;
                    $updates['wait_sec'] = max(0, $epoch - (int) $currentLog->wait_epoch);
                }

                // If dispo_epoch was never set, use talk_epoch.
                if ($dispoEpoch < 1000) {
                    $dispoEpoch = $talkEpoch;
                    $updates['dispo_epoch'] = $dispoEpoch;
                }

                $updates['dispo_sec'] = max(0, $epoch - $dispoEpoch) + (int) $currentLog->dispo_sec;

                $db->table('vicidial_agent_log')
                    ->where('agent_log_id', $session->agent_log_id)
                    ->update($updates);
            }
        }

        // 6. Open a new agent_log entry for the post-dispo PAUSED period.
        $newAgentLogId = $db->table('vicidial_agent_log')->insertGetId([
            'user' => $vicidialUser,
            'server_ip' => $session->server_ip,
            'event_time' => $nowStr,
            'campaign_id' => $session->campaign_id,
            'pause_epoch' => $epoch,
            'pause_sec' => 0,
            'wait_epoch' => $epoch,
            'wait_sec' => 0,
            'user_group' => $session->user_group ?? '',
            'pause_type' => 'AGENT',
            'lead_id' => $leadId ?: null,
        ]);

        // 7. Stamp the live agent with the new log ID.
        $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->update(['agent_log_id' => $newAgentLogId]);

        $session->update(['agent_log_id' => $newAgentLogId]);
    }

    /**
     * Log the agent out, releasing all VICIdial resources and hanging up their Zoiper call.
     */
    public function logout(AgentSession $session, string $vicidialUser): void
    {
        $db = $this->db();
        $now = now();
        $epoch = $now->unix();

        // Close the final log entry, recording time in last state.
        if ($session->agent_log_id) {
            $currentLog = $db->table('vicidial_agent_log')
                ->where('agent_log_id', $session->agent_log_id)
                ->first();

            if ($currentLog) {
                $updates = [];

                if (! $currentLog->wait_sec) {
                    $updates['wait_sec'] = max(0, $epoch - (int) $currentLog->wait_epoch);
                }

                if (! $currentLog->pause_sec) {
                    $updates['pause_sec'] = max(0, $epoch - (int) $currentLog->pause_epoch);
                }

                if ($updates) {
                    $db->table('vicidial_agent_log')
                        ->where('agent_log_id', $session->agent_log_id)
                        ->update($updates);
                }
            }
        }

        // Write the logout event.
        $db->table('vicidial_user_log')->insert([
            'user' => $vicidialUser,
            'event' => 'LOGOUT',
            'campaign_id' => $session->campaign_id,
            'event_date' => $now->toDateTimeString(),
            'event_epoch' => $epoch,
            'user_group' => $session->user_group,
            'session_id' => $session->session_name ?? '',
            'server_ip' => $session->server_ip,
        ]);

        // Hang up the agent's Zoiper/SIP call before cleaning up records.
        $this->hangupAgentCall($session, $vicidialUser);

        // Release the reserved conference room.
        if ($session->conf_exten && $session->server_ip) {
            $db->table('vicidial_conferences')
                ->where('server_ip', $session->server_ip)
                ->where('conf_exten', $session->conf_exten)
                ->update(['extension' => '']);
        }

        // Remove the web client session.
        if ($session->session_name && $session->server_ip) {
            $db->table('web_client_sessions')
                ->where('server_ip', $session->server_ip)
                ->where('session_name', $session->session_name)
                ->delete();
        }

        // Remove the live agent records.
        $db->table('vicidial_live_agents')
            ->where('server_ip', $session->server_ip)
            ->where('user', $vicidialUser)
            ->delete();

        $db->table('vicidial_live_inbound_agents')
            ->where('user', $vicidialUser)
            ->delete();
    }

    /**
     * Hang up the agent's SIP/Zoiper call on logout via vicidial_manager.
     *
     * Two commands are queued for Asterisk:
     *  1. Hangup — targets the specific SIP channel (if Asterisk has already
     *     assigned one, stored in vicidial_live_agents.channel after the call
     *     was answered).
     *  2. Originate to Local/5555{conf_exten} — the VICIdial "kickall" that
     *     evicts the agent from the conference bridge regardless of channel name.
     *     Mirrors vicidial.php login_kickall logic.
     */
    private function hangupAgentCall(AgentSession $session, string $vicidialUser): void
    {
        if (! $session->server_ip || ! $session->conf_exten) {
            return;
        }

        $db = $this->db();
        $now = now()->toDateTimeString();
        $callerId = 'ULVD'.$session->conf_exten.'_'.time();

        // Resolve the dialplan context from the phone record.
        $vicUser = $db->table('vicidial_users')->where('user', $vicidialUser)->first();
        $extContext = 'default';

        if ($vicUser?->phone_login) {
            $extContext = $db->table('phones')
                ->where('login', $vicUser->phone_login)
                ->value('ext_context') ?: 'default';
        }

        // 1. Hangup the specific SIP channel if Asterisk assigned one.
        $liveAgent = $db->table('vicidial_live_agents')
            ->where('user', $vicidialUser)
            ->where('server_ip', $session->server_ip)
            ->first();

        if ($liveAgent?->channel) {
            $db->table('vicidial_manager')->insert([
                'uniqueid' => '',
                'entry_date' => $now,
                'status' => 'NEW',
                'response' => 'N',
                'server_ip' => $session->server_ip,
                'channel' => '',
                'action' => 'Hangup',
                'callerid' => $callerId,
                'cmd_line_b' => "Channel: {$liveAgent->channel}",
                'cmd_line_c' => '',
                'cmd_line_d' => '',
                'cmd_line_e' => '',
                'cmd_line_f' => '',
                'cmd_line_g' => '',
                'cmd_line_h' => '',
                'cmd_line_i' => '',
                'cmd_line_j' => '',
                'cmd_line_k' => '',
            ]);
        }

        // 2. Originate to Local/5555{conf_exten} — kicks agent from conference bridge.
        // This is the VICIdial kickall mechanism: the 5555+conf_exten dialplan extension
        // clears all participants from that conference room.
        $kickChannel = "Local/5555{$session->conf_exten}@{$extContext}";

        $db->table('vicidial_manager')->insert([
            'uniqueid' => '',
            'entry_date' => $now,
            'status' => 'NEW',
            'response' => 'N',
            'server_ip' => $session->server_ip,
            'channel' => '',
            'action' => 'Originate',
            'callerid' => $callerId,
            'cmd_line_b' => "Channel: {$kickChannel}",
            'cmd_line_c' => "Context: {$extContext}",
            'cmd_line_d' => 'Exten: 8300',
            'cmd_line_e' => 'Priority: 1',
            'cmd_line_f' => "Callerid: {$callerId}",
            'cmd_line_g' => '',
            'cmd_line_h' => '',
            'cmd_line_i' => $vicidialUser,
            'cmd_line_j' => $vicUser?->phone_login ?? '',
            'cmd_line_k' => '',
        ]);
    }
}
