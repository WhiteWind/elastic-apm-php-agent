<?php

namespace Zuams\Events;

/**
 *
 * EventBean for occurring events such as Exceptions or Transactions
 *
 */
class EventBean
{
    /**
     * Bit Size of ID's
     */
    const
        EVENT_ID_BITS  = 64,
        TRACE_ID_BITS = 128;

    /**
     * Event Id
     *
     * @var string
     */
    private $id;

    /**
     * Id of the whole trace forest and is used to uniquely identify a distributed trace through a system
     * @link https://www.w3.org/TR/trace-context/#trace-id
     *
     * @var string
     */
    private $traceId;


    /**
     * Id of parent transaction
     *
     * @var string
     */
    private $transactionId = null;

    /**
     * Id of parent span or parent transaction
     *
     * @link https://www.w3.org/TR/trace-context/#parent-id
     *
     * @var string
     */
    private $parentId = null;

    /**
     * Error occurred on Timestamp
     *
     * @var float
     */
    private $timestamp;

    /**
     * Event Metadata
     *
     * @var array
     */
    private $meta = [
        'result' => 200,
        'type'   => 'generic'
    ];

    /**
     * Extended Contexts such as Custom and/or User
     *
     * @var array
     */
    private $contexts = [
        'request'  => [],
        'user'     => [],
        'custom'   => [],
        'env'      => [],
        'tags'     => [],
        'response' => [
            'finished'     => true,
            'headers_sent' => true,
            'status_code'  => 200,
        ],
    ];

    /**
     * Init the Event with the Timestamp and UUID
     *
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/3
     *
     * @param array $contexts
     * @param ?Transaction $parent
     */
    public function __construct(array $contexts, Transaction $parent = null)
    {
        // Generate Random Event Id
        $this->id = self::generateRandomBitsInHex(self::EVENT_ID_BITS);

        // Merge Initial Context
        $this->contexts = array_merge($this->contexts, $contexts);

        // Get current Unix timestamp with seconds
        $this->timestamp = intval(microtime(true) * 1000000);

        // Set Parent Transaction
        if ($parent !== null) {
            $this->setParent($parent);
        }
    }

    /**
     * Get the Event Id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the Trace Id
     *
     * @return string $traceId
     */
    public function getTraceId()
    {
        return $this->traceId;
    }

    /**
     * Set the Trace Id
     *
     * @param string $traceId
     */
    final public function setTraceId( $traceId)
    {
        $this->traceId = $traceId;
    }

    /**
     * Set the Parent Id
     *
     * @param string $parentId
     */
    final public function setParentId( $parentId)
    {
        $this->parentId = $parentId;
    }

    /**
     * Get the Parent Id
     *
     * @return string
     */
    final public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * Get the parent Transaction Id
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Get the Offset between the Parent's timestamp and this Event's
     *
     * @return int
     */
    final public function getParentTimestampOffset()
    {
        return $this->parentTimestampOffset;
    }

    /**
     * Get the Event's Timestamp
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set the Parent Id and Trace Id based on the Parent Event
     *
     * @link https://www.elastic.co/guide/en/apm/server/current/transaction-api.html
     *
     * @param EventBean $parent
     */
    public function setParent(EventBean $parent)
    {
        $this->transactionId = $parent->getTransactionId();
        $this->setParentId($parent->getId());
        $this->setTraceId($parent->getTraceId());
    }

    /**
     * Set the Transaction Meta data
     *
     * @param array $meta
     *
     * @return void
     */
    final public function setMeta(array $meta)
    {
        $this->meta = array_merge($this->meta, $meta);
    }

    /**
     * Set Meta data of User Context
     *
     * @param array $userContext
     */
    final public function setUserContext(array $userContext)
    {
        $this->contexts['user'] = array_merge($this->contexts['user'], $userContext);
    }

    /**
     * Set custom Meta data for the Transaction in Context
     *
     * @param array $customContext
     */
    final public function setCustomContext(array $customContext)
    {
        $this->contexts['custom'] = array_merge($this->contexts['custom'], $customContext);
    }

    /**
     * Set Transaction Response
     *
     * @param array $response
     */
    final public function setResponse(array $response)
    {
        $this->contexts['response'] = array_merge($this->contexts['response'], $response);
    }

    /**
     * Set Tags for this Transaction
     *
     * @param array $tags
     */
    final public function setTags(array $tags)
    {
        $this->contexts['tags'] = array_merge($this->contexts['tags'], $tags);
    }

    /**
     * Set Transaction Request
     *
     * @param array $request
     */
    final public function setRequest(array $request)
    {
        $this->contexts['request'] = array_merge($this->contexts['request'], $request);
    }

    /**
     * Generate request data
     *
     * @return array
     */
    final public function generateRequest()
    {
        $headers = getallheaders();
        $http_or_https = isset($_SERVER['HTTPS']) ? 'https' : 'http';

        // Build Context Stub
        $SERVER_PROTOCOL = '';
        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $SERVER_PROTOCOL = $_SERVER['SERVER_PROTOCOL'];
        }
        $remote_address = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $remote_address = $_SERVER['REMOTE_ADDR'];
        }
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) === true) {
            $remote_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return [
            'http_version' => substr($SERVER_PROTOCOL, strpos($SERVER_PROTOCOL, '/')),
            'method'       => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'cli',
            'socket'       => [
                'remote_address' => $remote_address,
                'encrypted'      => isset($_SERVER['HTTPS'])
            ],
            'url'          => [
                'protocol' => $http_or_https,
                'hostname' => !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                'port'     => !empty($_SERVER['SERVER_PORT']) ? intval($_SERVER['SERVER_PORT']) : 0,
                'pathname' => !empty($_SERVER['DOCUMENT_URI']) ? substr($_SERVER['DOCUMENT_URI'], 0, 1024) : '',
                'search'   => !empty($_SERVER['QUERY_STRING']) ? substr($_SERVER['QUERY_STRING'], 0, 1024) : '',
                'full' => !empty($_SERVER['HTTP_HOST']) ? substr($http_or_https . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 0, 1024) : '',
            ],
            'headers' => [
                'user-agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'cookie'     => '',
            ],
            'env' => (object)$this->getEnv(),
            'cookies' => (object)$this->getCookies(),
        ];
    }

    /**
     * Generate random bits in hexadecimal representation
     *
     * @param int $bits
     * @return string
     * @throws \Exception
     */
    final protected function generateRandomBitsInHex($bits)
    {
        $str = '';
        for ($i = 0; $i < $bits/8; $i++)
            $str .= chr(mt_rand(0, 255));
        return bin2hex($str);
    }

    /**
     * Get Type defined in Meta
     *
     * @return string
     */
    final protected function getMetaType()
    {
        return $this->meta['type'];
    }

    /**
     * Get the Result of the Event from the Meta store
     *
     * @return string
     */
    final protected function getMetaResult()
    {
        return (string)$this->meta['result'];
    }

    /**
     * Get the Environment Variables
     *
     * @link http://php.net/manual/en/reserved.variables.server.php
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/27
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/54
     *
     * @return array
     */
    final protected function getEnv()
    {
        $envMask = $this->contexts['env'];
        $env = empty($envMask)
            ? $_SERVER
            : array_intersect_key($_SERVER, array_flip($envMask));

        return $env;
    }

    /**
     * Get the cookies
     *
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/30
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/54
     *
     * @return array
     */
    final protected function getCookies()
    {
        $cookieMask = isset($this->contexts['cookies']) ? $this->contexts['cookies'] : [];
        return empty($cookieMask)
            ? $_COOKIE
            : array_intersect_key($_COOKIE, array_flip($cookieMask));
    }

    /**
     * Get the cookie header
     *
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/30
     *
     * @return string
     */
    final protected function getCookieHeader($cookieHeader)
    {
        $cookieMask = isset($this->contexts['cookies']) ? $this->contexts['cookies'] : [];
        // Returns an empty string if cookies are masked.
        return empty($cookieMask) ? $cookieHeader : '';
    }

    /**
     * Get the Events Context
     *
     * @link https://www.elastic.co/guide/en/apm/server/current/transaction-api.html#transaction-context-schema
     *
     * @return array
     */
    final protected function getContext()
    {
        $context = [
            'request' => empty($this->contexts['request']) ? $this->generateRequest() : $this->contexts['request'],
            'response' => $this->contexts['response'],
        ];

        // Add User Context
        if (empty($this->contexts['user']) === false) {
            $context['user'] = $this->contexts['user'];
        }

        // Add Custom Context
        if (empty($this->contexts['custom']) === false) {
            $context['custom'] = $this->contexts['custom'];
        }

        // Add Tags Context
        if (empty($this->contexts['tags']) === false) {
            $context['tags'] = $this->contexts['tags'];
        }

        return $context;
    }
}
