<?php

declare(strict_types=1);

namespace Brain\Broadcasting\Events;

final class TaskFinished extends BaseBroadcastEvent
{
    protected function getBroadcastType(): string
    {
        return 'task';
    }

    protected function getBroadcastEvent(): string
    {
        return 'finished';
    }
}
