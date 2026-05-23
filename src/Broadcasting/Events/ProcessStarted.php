<?php

declare(strict_types=1);

namespace Brain\Broadcasting\Events;

final class ProcessStarted extends BaseBroadcastEvent
{
    protected function getBroadcastType(): string
    {
        return 'process';
    }

    protected function getBroadcastEvent(): string
    {
        return 'started';
    }
}
