<?php

namespace Zuams;

use Zuams\Events\DefaultEventFactory;
use Zuams\Events\EventFactoryInterface;
use Zuams\Stores\TransactionsStore;
use Zuams\Events\EventBean;
use Zuams\Events\Error;
use Zuams\Events\Transaction;
use Zuams\Events\Metadata;
use Zuams\Helper\Timer;
use Zuams\Helper\Config;
use Zuams\Middleware\Connector;
use Zuams\Exception\Transaction\DuplicateTransactionNameException;
use Zuams\Exception\Transaction\UnknownTransactionException;

/**
 *
 * APM Agent
 *
 * @link https://www.elastic.co/guide/en/apm/server/master/transaction-api.html
 *
 */
class Agent
{

    /**
     * Agent Version
     *
     * @var string
     */
    const VERSION = 'v0.0.1';

    /**
     * Agent Name
     *
     * @var string
     */
    const NAME = 'elastic-apm-php-agent';

    /**
     * Config Store
     *
     * @var \Zuams\Helper\Config
     */
    private $config;

    /**
     * Transactions Store
     *
     * @var \Zuams\Stores\TransactionsStore
     */
    private $transactionsStore;

    /**
     * Apm Timer
     *
     * @var \Zuams\Helper\Timer
     */
    private $timer;

    /**
     * Common/Shared Contexts for Errors and Transactions
     *
     * @var array
     */
    private $sharedContext = [
      'user'   => [],
      'custom' => [],
      'tags'   => []
    ];

    /**
     * @var EventFactoryInterface
     */
    private $eventFactory;

    /**
     * @var Connector
     */
    private $connector;

    /**
     * Setup the APM Agent
     *
     * @param array                 $config
     * @param array                 $sharedContext Set shared contexts such as user and tags
     * @param EventFactoryInterface $eventFactory  Alternative factory to use when creating event objects
     *
     * @return void
     */
    public function __construct(array $config, array $sharedContext = [], EventFactoryInterface $eventFactory = null, TransactionsStore $transactionsStore = null)
    {
        // Init Agent Config
        $this->config = new Config($config);

        // Use the custom event factory or create a default one
        if (!$eventFactory) {
            $eventFactory = new DefaultEventFactory();
        }
        $this->eventFactory = $eventFactory;

        // Init the Shared Context
        if (isset($sharedContext['user'])) {
            $this->sharedContext['user'] = $sharedContext['user'];
        } else {
            $this->sharedContext['user'] = [];
        }
        if (isset($sharedContext['custom'])) {
            $this->sharedContext['custom'] = $sharedContext['custom'];
        } else {
            $this->sharedContext['custom'] = [];
        }
        if (isset($sharedContext['tags'])) {
            $this->sharedContext['tags'] = $sharedContext['tags'];
        } else {
            $this->sharedContext['tags'] = [];
        }

        // Let's misuse the context to pass the environment variable and cookies
        // config to the EventBeans and the getContext method
        // @see https://github.com/philkra/elastic-apm-php-agent/issues/27
        // @see https://github.com/philkra/elastic-apm-php-agent/issues/30
        $this->sharedContext['env'] = $this->config->get('env', []);
        $this->sharedContext['cookies'] = $this->config->get('cookies', []);

        // Initialize Event Stores
        if (!$transactionsStore) {
            $transactionsStore = new TransactionsStore();
        }
        $this->transactionsStore = $transactionsStore;
        // Init the Transport "Layer"
        $this->connector = new Connector($this->config);
        $this->connector->putEvent(new Metadata([], $this->config));

        // Start Global Agent Timer
        $this->timer = new Timer();
        $this->timer->start();
    }

    /**
     * Event Factory
     *
     * @return EventFactoryInterface
     */
    public function factory()
    {
        return $this->eventFactory;
    }

    /**
     * Query the Info endpoint of the APM Server
     *
     * @link https://www.elastic.co/guide/en/apm/server/7.3/server-info.html
     *
     * @return Response
     */
    public function info()
    {
        return $this->connector->getInfo();
    }

    /**
     * Start the Transaction capturing
     *
     * @throws \Zuams\Exception\Transaction\DuplicateTransactionNameException
     *
     * @param string $name
     * @param array  $context
     *
     * @return Transaction
     */
    public function startTransaction($name, $context = [], $start = null)
    {
        // Create and Store Transaction
        $this->transactionsStore->register(
            $this->factory()->newTransaction($name, array_replace_recursive($this->sharedContext, $context), $start)
        );

        // Start the Transaction
        $transaction = $this->transactionsStore->fetch($name);

        if (null === $start) {
            $transaction->start();
        }

        return $transaction;
    }

    /**
     * Stop the Transaction
     *
     * @throws \Zuams\Exception\Transaction\UnknownTransactionException
     *
     * @param string $name
     * @param array $meta, Def: []
     *
     * @return void
     */
    public function stopTransaction(string $name, array $meta = [])
    {
        $this->getTransaction($name)->setBacktraceLimit($this->config->get('backtraceLimit', 0));
        $this->getTransaction($name)->stop();
        $this->getTransaction($name)->setMeta($meta);
    }

    /**
     * Get a Transaction
     *
     * @throws \Zuams\Exception\Transaction\UnknownTransactionException
     *
     * @param string $name
     *
     * @return Transaction
     */
    public function getTransaction(string $name)
    {
        $transaction = $this->transactionsStore->fetch($name);
        if ($transaction === null) {
            throw new UnknownTransactionException($name);
        }

        return $transaction;
    }

    /**
     * Register a Thrown Exception, Error, etc.
     *
     * @link http://php.net/manual/en/class.throwable.php
     *
     * @param \Throwable  $thrown
     * @param array       $context, Def: []
     * @param Transaction $parent, Def: null
     *
     * @return void
     */
    public function captureThrowable(\Throwable $thrown, array $context = [], $parent = null)
    {
        $this->putEvent($this->factory()->newError($thrown, array_replace_recursive($this->sharedContext, $context), $parent));
    }

    /**
     * Put an Event to the Events Pool
     */
    public function putEvent(EventBean $event)
    {
        $this->connector->putEvent($event);
    }

    /**
     * Get the Agent Config
     *
     * @return \Zuams\Helper\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Send Data to APM Service
     *
     * @link https://github.com/philkra/elastic-apm-laravel/issues/22
     * @link https://github.com/philkra/elastic-apm-laravel/issues/26
     *
     * @return bool
     */
    public function send()
    {
        // Is the Agent enabled ?
        if ($this->config->get('active') === false) {
            $this->transactionsStore->reset();
            return true;
        }

        // Put the preceding Metadata
        // TODO -- add context ?
        if($this->connector->isPayloadSet() === false) {
            $this->putEvent(new Metadata([], $this->config));
        }

        // Start Payload commitment
        foreach($this->transactionsStore->theList() as $event) {
            $this->connector->putEvent($event);
        }
        $this->transactionsStore->reset();
        return $this->connector->commit();
    }

    /**
     * Flush the Queue Payload
     *
     * @link https://www.php.net/manual/en/language.oop5.decon.php#object.destruct
     */
    function __destruct() {
        $this->send();
    }

}
