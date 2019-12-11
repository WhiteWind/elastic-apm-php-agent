<?php

namespace Zuams\Exception\Transaction;

/**
 * Trying to fetch an unregistered Transaction
 */
class UnknownTransactionException extends \Exception
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('The transaction "%s" is not registered.', $message), $code, $previous);
    }
}
