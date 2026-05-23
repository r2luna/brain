<?php

declare(strict_types=1);

namespace Brain\Broadcasting\Events;

final class ProcessFinished extends BaseBroadcastEvent
{
    protected function getBroadcastType(): string
    {
        return 'process';
    }

    protected function getBroadcastEvent(): string
    {
        return 'finished';
    }
}
