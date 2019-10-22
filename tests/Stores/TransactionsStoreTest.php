<?php
namespace Zuams\Tests\Stores;

use \Zuams\Stores\TransactionsStore;
use \Zuams\Events\Transaction;
use Zuams\Tests\TestCase;

/**
 * Test Case for @see \Zuams\Stores\TransactionsStore
 */
final class TransactionsStoreTest extends TestCase {

  /**
   * @covers \Zuams\Stores\TransactionsStore::register
   * @covers \Zuams\Stores\TransactionsStore::get
   */
  public function testTransactionRegistrationAndFetch() {
    $store = new TransactionsStore();
    $name  = 'test';
    $trx   = new Transaction( $name, [] );

    // Must be Empty
    $this->assertTrue( $store->isEmpty() );

    // Store the Transaction and fetch it then
    $store->register( $trx );
    $proof = $store->fetch( $name );

    // We should get the Same!
    $this->assertEquals( $trx, $proof );
    $this->assertNotNull( $proof );

    // Must not be Empty
    $this->assertFalse( $store->isEmpty() );
  }

  /**
   * @depends testTransactionRegistrationAndFetch
   *
   * @covers \Zuams\Stores\TransactionsStore::register
   */
  public function testDuplicateTransactionRegistration() {
    $store = new TransactionsStore();
    $name  = 'test';
    $trx   = new Transaction( $name, [] );

    $this->expectException( \Zuams\Exception\Transaction\DuplicateTransactionNameException::class );

    // Store the Transaction again to force an Exception
    $store->register( $trx );
    $store->register( $trx );
  }

  /**
   * @depends testTransactionRegistrationAndFetch
   *
   * @covers \Zuams\Stores\TransactionsStore::get
   */
  public function testFetchUnknownTransaction() {
    $store = new TransactionsStore();
    $this->assertNull( $store->fetch( 'unknown' ) );
  }

}
