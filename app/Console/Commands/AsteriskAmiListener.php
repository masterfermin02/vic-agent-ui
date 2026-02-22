<?php

namespace App\Console\Commands;

use App\Events\AgentCallStatusUpdated;
use App\Events\AgentStatusChanged;
use App\Models\AgentSession;
use App\Services\AsteriskAmiService;
use Illuminate\Console\Command;

class AsteriskAmiListener extends Command
{
    protected $signature = 'ami:listen';

    protected $description = 'Listen to Asterisk AMI events and broadcast real-time call updates';

    private bool $shouldStop = false;

    public function handle(AsteriskAmiService $ami): int
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $this->info('AMI listener starting...');

        while (! $this->shouldStop) {
            try {
                $ami->connect();
                $ami->login();
                $this->info('Connected to Asterisk AMI.');

                $ami->listen(function (array $event): void {
                    if (function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }

                    $this->handleAmiEvent($event);
                });
            } catch (\Throwable $e) {
                $this->error('AMI error: '.$e->getMessage());

                try {
                    $ami->disconnect();
                } catch (\Throwable) {
                    // Ignore disconnect errors
                }

                if (! $this->shouldStop) {
                    $this->info('Reconnecting in 5 seconds...');
                    sleep(5);
                }
            }
        }

        $this->info('AMI listener stopped.');

        return Command::SUCCESS;
    }

    /** @param array<string, string> $event */
    private function handleAmiEvent(array $event): void
    {
        $eventName = $event['Event'] ?? '';

        match (true) {
            $eventName === 'Dial' && ($event['SubEvent'] ?? '') === 'Begin' => $this->handleDialBegin($event),
            in_array($eventName, ['Bridge', 'AgentConnect']) => $this->handleAnswered($event),
            $eventName === 'Hangup' => $this->handleHangup($event),
            default => null,
        };
    }

    /** @param array<string, string> $event */
    private function handleDialBegin(array $event): void
    {
        $channel = $event['Channel'] ?? '';
        $session = AgentSession::where('asterisk_channel', $channel)->first();

        if (! $session) {
            return;
        }

        AgentCallStatusUpdated::dispatch(
            $session->user_id,
            'ringing',
            $event['CallerIDNum'] ?? '',
            $event['CallerIDName'] ?? '',
            $channel,
            $event['DestUniqueID'] ?? '',
        );
    }

    /** @param array<string, string> $event */
    private function handleAnswered(array $event): void
    {
        $channel = $event['Channel'] ?? ($event['Channel1'] ?? '');
        $session = AgentSession::where('asterisk_channel', $channel)->first();

        if (! $session) {
            return;
        }

        $session->update([
            'status' => 'incall',
            'call_started_at' => now(),
        ]);

        AgentCallStatusUpdated::dispatch(
            $session->user_id,
            'answered',
            $event['CallerIDNum'] ?? '',
            $event['CallerIDName'] ?? '',
            $channel,
        );

        AgentStatusChanged::dispatch($session->user_id, 'incall', $session->campaign_id);
    }

    /** @param array<string, string> $event */
    private function handleHangup(array $event): void
    {
        $channel = $event['Channel'] ?? '';
        $session = AgentSession::where('asterisk_channel', $channel)->first();

        if (! $session) {
            return;
        }

        $session->update(['status' => 'wrapup']);

        AgentCallStatusUpdated::dispatch(
            $session->user_id,
            'hangup',
            channel: $channel,
        );

        AgentStatusChanged::dispatch($session->user_id, 'wrapup', $session->campaign_id);
    }
}
