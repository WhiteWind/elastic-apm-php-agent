<?php

namespace Zuams\Stores;

use Zuams\Events\Transaction;
use Zuams\Exception\Transaction\DuplicateTransactionNameException;

/**
 *
 * Store for the Transaction Events
 *
 */
class TransactionsStore extends Store
{
    /**
     * Register a Transaction
     *
     * @throws \Zuams\Exception\Transaction\DuplicateTransactionNameException
     *
     * @param \Zuams\Events\Transaction $transaction
     *
     * @return void
     */
    public function register(Transaction $transaction)
    {
        $name = $transaction->getTransactionName();

        // Do not override the
        if (isset($this->store[$name]) === true) {
            throw new DuplicateTransactionNameException($name);
        }

        // Push to Store
        $this->store[$name] = $transaction;
    }

    /**
     * Fetch a Transaction from the Store
     *
     * @param final string $name
     *
     * @return mixed: \Zuams\Events\Transaction | null
     */
    public function fetch(string $name)
    {
        return isset($this->store[$name]) ? $this->store[$name] : null;
    }

    /**
     * Serialize the Transactions Events Store
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array_values($this->store);
    }
}
