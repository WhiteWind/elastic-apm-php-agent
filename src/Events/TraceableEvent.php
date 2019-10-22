<?php

namespace Zuams\Events;

use Zuams\Exception\InvalidTraceContextHeaderException;
use Zuams\Helper\DistributedTracing;

/**
 *
 * Traceable Event -- Distributed Tracing
 *
 */
class TraceableEvent extends EventBean
{

    /**
    * Create the Transaction
    *
    * @param string $name
    * @param array $contexts
    */
    public function __construct(array $contexts)
    {
        parent::__construct($contexts);
        $this->setTraceContext();
    }

    /**
     * Get the Distributed Tracing Value of this Event
     *
     * @return string
     */
    public function getDistributedTracing()
    {
        return (new DistributedTracing($this->getTraceId(), $this->getParentId()))->__toString();
    }

    /**
     * Set Trace context
     *
     * @throws \Exception
     */
    private function setTraceContext()
    {
        // Is one of the Traceparent Headers populated ?
        $header = (isset($_SERVER['HTTP_ELASTIC_APM_TRACEPARENT']) ? $_SERVER['HTTP_ELASTIC_APM_TRACEPARENT'] : (isset($_SERVER['HTTP_TRACEPARENT']) ? $_SERVER['HTTP_TRACEPARENT'] : null));
        if ($header !== null && DistributedTracing::isValidHeader($header) === true) {
            try {
                $traceParent = DistributedTracing::createFromHeader($header);

                $this->setTraceId($traceParent->getTraceId());
                $this->setParentId($traceParent->getParentId());
            }
            catch (InvalidTraceContextHeaderException $e) {
                $this->setTraceId(self::generateRandomBitsInHex(self::TRACE_ID_BITS));
            }
        }
        else {
            $this->setTraceId(self::generateRandomBitsInHex(self::TRACE_ID_BITS));
        }
    }

}
