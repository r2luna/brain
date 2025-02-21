<?php

namespace R2luna\Brain;

use Illuminate\Support\Facades\Facade;

/**
 * @see \R2luna\Brain\Skeleton\SkeletonClass
 */
class BrainFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'brain';
    }
}
