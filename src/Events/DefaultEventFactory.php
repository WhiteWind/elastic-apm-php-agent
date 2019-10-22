<?php

namespace Zuams\Events;

final class DefaultEventFactory implements EventFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function newError(\Throwable $throwable, array $contexts, Transaction $parent = null)
    {
        return new Error($throwable, $contexts, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function newTransaction(string $name, array $contexts, float $start = null)
    {
        return new Transaction($name, $contexts, $start);
    }

    /**
     * {@inheritdoc}
     */
    public function newSpan(string $name, EventBean $parent)
    {
        return new Span($name, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function newMetricset(array $set, array $tags = [])
    {
        return new Metricset($set, $tags);
    }

}
