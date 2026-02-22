<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentCallStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $callStatus,
        public readonly string $callerIdNum = '',
        public readonly string $callerIdName = '',
        public readonly string $channel = '',
        public readonly string $leadId = '',
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AgentCallStatusUpdated';
    }
}
