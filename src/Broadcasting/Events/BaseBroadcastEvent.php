<?php

declare(strict_types=1);

namespace Brain\Broadcasting\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $id,
        public string $name,
        public array $messageData,
        public array $meta = []
    ) {}

    abstract protected function getBroadcastType(): string;

    abstract protected function getBroadcastEvent(): string;

    /**
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('brain'),
            new Channel("brain.{$this->getBroadcastType()}"),
            new Channel("brain.{$this->getBroadcastType()}.{$this->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->getBroadcastType(),
            'event' => $this->getBroadcastEvent(),
            'message' => $this->messageData,
            'meta' => $this->meta,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return "brain.{$this->getBroadcastType()}.{$this->getBroadcastEvent()}";
    }
}
