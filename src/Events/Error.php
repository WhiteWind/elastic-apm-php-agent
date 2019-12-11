<?php

namespace Zuams\Events;

/**
 *
 * Event Bean for Error wrapping
 *
 * @link https://www.elastic.co/guide/en/apm/server/6.2/errors.html
 *
 */
class Error extends EventBean implements \JsonSerializable
{
    /**
     * Error | Exception
     *
     * @link http://php.net/manual/en/class.throwable.php
     *
     * @var \Exception
     */
    private $throwable;

    /**
     * @param \Exception $throwable
     * @param array $contexts
     */
    public function __construct(\Exception $throwable, array $contexts, Transaction $transaction = null)
    {
        parent::__construct($contexts, $transaction);
        $this->throwable = $throwable;
    }

    /**
     * Serialize Error Event
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'error' => [
                'id'             => $this->getId(),
                'transaction_id' => $this->getParentId(),
                'parent_id'      => $this->getParentId(),
                'trace_id'       => $this->getTraceId(),
                'timestamp'      => $this->getTimestamp(),
                'context'        => $this->getContext(),
                'culprit'        => sprintf('%s:%d', $this->throwable->getFile(), $this->throwable->getLine()),
                'exception'      => [
                    'message'    => $this->throwable->getMessage(),
                    'type'       => get_class($this->throwable),
                    'code'       => $this->throwable->getCode(),
                    'stacktrace' => $this->mapStacktrace(),
                ],
            ]
        ];
    }

    /**
     * Map the Stacktrace to Schema
     *
     * @return array
     */
    final private function mapStacktrace()
    {
        $stacktrace = [];

        foreach ($this->throwable->getTrace() as $trace) {
            $item = [
              'function' => '(closure)'
            ];

            if (isset($trace['line']) === true) {
                $item['lineno'] = $trace['line'];
            }

            if (isset($trace['file']) === true) {
                $item += [
                    'filename' => basename($trace['file']),
                    'abs_path' => $trace['file']
                ];
            }

            if (isset($trace['class']) === true) {
                $item['module'] = $trace['class'];
            }
            if (isset($trace['type']) === true) {
                $item['type'] = $trace['type'];
            }

            if (!isset($item['lineno'])) {
                $item['lineno'] = 0;
            }

            if (!isset($item['filename'])) {
                $item['filename'] = '(anonymous)';
            }

            array_push($stacktrace, $item);
        }

        return $stacktrace;
    }

}
