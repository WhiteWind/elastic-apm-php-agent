<?php

namespace PhilKra\Exception\Timer;

/**
 * Trying to get the Duration of a running Timer
 */
class NotStoppedException extends \Exception
{
    public function __construct( $message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct('Can\'t get the duration of a running timer.', $code, $previous);
    }
}
