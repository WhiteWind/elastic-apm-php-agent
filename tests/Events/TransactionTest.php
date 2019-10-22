<?php
namespace Zuams\Tests\Events;

use \Zuams\Events\Transaction;
use Zuams\Tests\TestCase;

/**
 * Test Case for @see \Zuams\Events\Transaction
 */
final class TransactionTest extends TestCase {

    /**
     * @covers \Zuams\Events\EventBean::getId
     * @covers \Zuams\Events\EventBean::getTraceId
     * @covers \Zuams\Events\Transaction::getTransactionName
     * @covers \Zuams\Events\Transaction::setTransactionName
     */
    public function testParentConstructor() {
        $name = 'testerus-grandes';
        $transaction = new Transaction($name, []);

        $this->assertEquals($name, $transaction->getTransactionName());
        $this->assertNotNull($transaction->getId());
        $this->assertNotNull($transaction->getTraceId());
        $this->assertNotNull($transaction->getTimestamp());

        $now = round(microtime(true) * 1000000);
        $this->assertGreaterThanOrEqual($transaction->getTimestamp(), $now);
    }

    /**
     * @depends testParentConstructor
     *
     * @covers \Zuams\Events\EventBean::setParent
     * @covers \Zuams\Events\EventBean::getTraceId
     * @covers \Zuams\Events\EventBean::ensureGetTraceId
     */
    public function testParentReference() {
        $parent = new Transaction('parent', []);
        $child  = new Transaction('child', []);
        $child->setParent($parent);

        $arr = json_decode(json_encode($child), true);

        $this->assertEquals($arr['transaction']['id'], $child->getId());
        $this->assertEquals($arr['transaction']['parent_id'], $parent->getId());
        $this->assertEquals($arr['transaction']['trace_id'], $parent->getTraceId());
        $this->assertEquals($child->getTraceId(), $parent->getTraceId());
    }

}
