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
                    'handled'    => false
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
        $stacktrace = [
            [
                'filename' => basename($this->throwable->getFile()),
                'abs_path' => $this->throwable->getFile(),
                'lineno' => $this->throwable->getLine()
            ]
        ];
        $i = 0;

        foreach ($this->throwable->getTrace() as $trace) {
            $item = [
              'function' => '(closure)'
            ];

            if (isset($trace['function']) === true)
                $stacktrace[$i]['function'] = $trace['function'].'()';

            if (isset($trace['class']) === true) {
                $stacktrace[$i]['classname'] = $trace['class'];
                $stacktrace[$i]['module'] = $trace['class'];
                $stacktrace[$i]['function'] = $trace['class'] . (isset($trace['type'])?$trace['type']:'::') . $stacktrace[$i]['function'];
            }

            if (isset($trace['line']) === true) {
                $item['lineno'] = $trace['line'];
            }

            if (isset($trace['file']) === true) {
                $item += [
                    'filename' => basename($trace['file']),
                    'abs_path' => $trace['file']
                ];
            }

            if (!isset($item['lineno'])) {
                $item['lineno'] = 0;
            }

            if (!isset($item['filename'])) {
                $item['filename'] = '(anonymous)';
            }

            $i++;
            array_push($stacktrace, $item);
        }

        return $stacktrace;
    }

}
