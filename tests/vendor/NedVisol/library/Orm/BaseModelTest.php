<?php
use NedVisol\Orm\OptimisticLockException;

use NedVisol\Orm\Transaction;

use NedVisol\Orm\TestModel1;
use NedVisol\Orm\TestModel2;

use NedVisol\StorageAdapter\Hbase\HbaseClient;
use NedVisol\Orm\InvalidOperationException;
use NedVisol\Orm\LazyLoadReference;
use NedVisol\Orm\BaseModel;

include_once __DIR__ . '/TestModel/TestModel1.php';
include_once __DIR__ . '/TestModel/TestModel2.php';

class BaseModelTest extends PHPUnit_Framework_TestCase
{
	


	private $props;
	private $propsBasic;
	private $adapter;

	public function setUp() {
		$this->props = array (
				'field1' => array (
						'dataType' => BaseModel::DATATYPE_SINGLE,
						'required' => true
				),
				'field2' => array (
						'dataType' => BaseModel::DATATYPE_ARRAY,
						'required' => true
				),
				'field3' => array (
						'dataType' => BaseModel::DATATYPE_REF,
						'required' => true
				),
				'field4' => array (
						'dataType' => BaseModel::DATATYPE_REF,
						'required' => false,
						'readonly' => true
				),
				'hiddenfield' => array (
						'dataType' => BaseModel::DATATYPE_REF,
						'hidden' => true,
				),

		);
		$this->propsBasic = array (
				'field1' => array (
						'dataType' => BaseModel::DATATYPE_SINGLE,
						'required' => false
				));

		$param = array('host'=> HBASE_THRIFT_HOST ,'port'=> HBASE_THRIFT_PORT);
		$this->adapter = new HbaseClient($param);

	}

	public function testGenerateUid() {
		$uid1 = BaseModel::GenerateUid();
		$uid2 = BaseModel::GenerateUid();

		$this->assertNotNull($uid1);
		$this->assertNotEquals($uid1, $uid2);
	}

	public function testGetterSetter() {

		$obj = new BaseModel(null, $this->props);
		$obj->field1 = 'foo';
		$this->assertEquals('foo', $obj->field1);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testSetter_failed() {
		$obj = new BaseModel(null, $this->props);
		$obj->foo = 'bar';
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testSetter_cannotSetRefArrayProp() {
		$obj = new TestModel2($this->adapter);
		$obj->refArray = 'foo';
	}

	public function testGetRefArray() {
		$obj = new TestModel2($this->adapter);
		$refArrayId = $obj->refArray->id;
		$this->assertNotNull($refArrayId);
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testSetter_failedRO() {
		$obj = new BaseModel(null, $this->props);
		$obj->field4 = 'bar';
	}

	public function testGetterSetter_failedHidden() {
		$obj = new BaseModel(null, $this->props);
		$exceptionsCount = 0;
		try {
			$obj->hiddenfield = '123';
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}
		try {
			$value = $obj->hiddenfield;
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}

		$this->assertEquals(2, $exceptionsCount, 'Not all exceptions were thrown');
	}

	public function testGetter_seeUnsavedData() {
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->getById($id);
		$obj->field1 = 'bar';
		$this->assertEquals('bar', $obj->field1);
	}

	public function testSetter_failedROInternalFields() {
		$obj = new BaseModel(null, $this->props);
		$exceptionsCount = 0;
		try {
			$obj->createdBy = '123';
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}
		try {
			$obj->createdDate = '123';
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}

		try {
			$obj->lastUpdatedBy = '123';
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}

		try {
			$obj->lastUpdatedDate = '123';
		} catch (InvalidOperationException $exception) {
			$exceptionsCount++;
		}

		$this->assertEquals(4, $exceptionsCount, 'Not all exceptions were thrown');


	}


	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testGetter_failed() {
		$obj = new BaseModel(null, $this->props);
		$bad = $obj->foo;
	}

	public function testSave() {
		$obj = new BaseModel($this->adapter, $this->props, 'testModel1');
		$obj2 = new BaseModel($this->adapter, $this->propsBasic, 'testModel2');
		$this->assertTrue($obj2->save(), 'unable to save obj2');

		$obj->field1 = 'foo';
		$obj->field2 = array('abc','def','ghi');
		$obj->field3 = $obj2;
		$ret = $obj->save();
		$this->assertTrue($ret, 'unable to save obj1');
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testSave_failedMissingValue() {
		$obj = new BaseModel($this->adapter, $this->props, 'testModel1');
		$obj2 = new BaseModel($this->adapter, $this->propsBasic, 'testModel2');
		$obj2->save();
		$obj->field2 = array('abc','def','ghi');
		$obj->field3 = $obj2;
		$obj->save();
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testSave_failedDataTypeNotArray() {
		$obj = new BaseModel($this->adapter, $this->props, 'testModel1');
		$obj2 = new BaseModel($this->adapter, $this->propsBasic, 'testModel2');
		$obj2->save();
		$obj->field1 = 'foo';
		$obj->field2 = 'bar';
		$obj->field3 = $obj2;
		$obj->save();
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testSave_failedDataTypeNotRef() {
		$obj = new BaseModel($this->adapter, $this->props);
		$lazyRef = new LazyLoadReference('foo-id');
		$obj->field1 = 'foo';
		$obj->field2 = array('abc','def','ghi');
		$obj->field3 = 'bar';
		$obj->save();
	}

	/**
	 * @expectedException NedVisol\Orm\InvalidOperationException
	 */
	public function testPriviledgedSet_failed() {
		$obj = new BaseModel($this->adapter, $this->props);
		$obj->privilegedSet('hiddenfield','test');
	}

	public function testGetById() {
		//save first
		$obj = new TestModel1($this->adapter);
		$obj2 = new TestModel2($this->adapter);
		$this->assertTrue($obj2->save(), 'unable to save obj2');

		$obj->field1 = 'foo';
		$obj->field2 = array('abc','def','ghi');
		$obj->field3 = $obj2;
		$ret = $obj->save();
		$this->assertTrue($ret, 'unable to save obj1');

		//retrieve
		$objNew = new TestModel1($this->adapter);
		$ret = $objNew->getById($obj->id);
		if ($ret === false) {
			$this->fail('unable to retrieve object, getById returns false');
		}

		$this->assertEquals($objNew->id, $obj->id);
		$this->assertEquals($objNew->field1, $obj->field1);
		$this->assertEquals($objNew->field2, $obj->field2);
		$this->assertEquals($objNew->field3->id, $obj->field3->id);
		$this->assertEquals(BaseModel::DATASTATE_RETRIEVED, $objNew->dataState);
	}

	/**
	 * 2 transaction, commit in the same order they start, txn2 data should persist
	 */
	public function testTransactionAllCommit_order1() {
		$txn1 = new Transaction();

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;


		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn1->getById($id2);
		$obj_txn1->field1 = 'bar2';
		$obj_txn1->save();

		sleep(0.01); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn2->joinTransaction($txn2);
		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
		$obj_txn2->save();

		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		$txn1->commit();
		$txn2->commit();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}

	/**
	 * 2 transaction, txn#2 commits first, txn2 data should persist
	 */
	public function testTransactionAllCommit_reversedOrder() {
		$txn1 = new Transaction();

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;


		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn1->getById($id2);
		$obj_txn1->field1 = 'bar2';
		$obj_txn1->save();

		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn2->joinTransaction($txn2);
		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
		$obj_txn2->save();

		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		$txn2->commit();
		$txn1->commit();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}

	/**
	 * 1 transaction, update and abort, original data should persist
	 */
	public function testTransactionAllCommit_abortedOne() {
		$txn1 = new Transaction();

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;


		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$txn1->abort();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo', $objTest->field1);
	}

	/**
	 * 2 transactions, txn1 aborted, txn2 data persist
	 */
	public function testTransactionAllCommit_abortedTwo() {
		$txn1 = new Transaction();

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;


		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn1->getById($id2);
		$obj_txn1->field1 = 'bar2';
		$obj_txn1->save();

		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn2->joinTransaction($txn2);
		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
		$obj_txn2->save();

		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		$txn1->abort();
		$txn2->commit();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}

	/**
	 * 2 concurrent updates, txn1 commited, txn2 aborted
	 */
	public function testTransactionAllCommit_aborted3() {
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;

		$txn1 = new Transaction();
		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn1->getById($id2);
		$obj_txn1->field1 = 'bar1';
		$obj_txn1->save();

		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn2->joinTransaction($txn2);
		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
		$obj_txn2->save();

		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		$txn1->commit();
		$txn2->abort();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo1', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar1', $objTest->field1);
	}

	/**
	 * 2 transaction, concurrent update in the correct order
	 */
	public function testTransactionAllCommit_concurrent1() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;

		$txn1 = new Transaction();
		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn2->joinTransaction($txn2);

		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();

		$obj_txn1->getById($id2);
		$obj_txn1->field1 = 'bar2';
		$obj_txn1->save();

		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
		$obj_txn2->save();

		$txn1->commit();
		$txn2->commit();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}

	/**
	 * 2 transaction, concurrent update - failed dirty read
	 */
	public function testTransactionAllCommit_concurrent2() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;

		$txn1 = new Transaction();
		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn2->joinTransaction($txn2);

		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();

		try {
			$obj_txn1->getById($id1); //should throw exception
			$this->fail('Dirty read did not throw exception');
		} catch (OptimisticLockException $ex) {

		}
	}

	/**
	 * 2 transaction, concurrent update - failed late write
	 */
	public function testTransactionAllCommit_concurrent3() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;

		$txn1 = new Transaction();
		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn2->joinTransaction($txn2);

		$obj_txn1->getById($id1);
		$obj_txn2->getById($id1);

		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
		$obj_txn1->field1 = 'foo1';

		try {
			$obj_txn1->save();
			$this->fail('Late write did not throw exception');
		} catch (OptimisticLockException $ex) {

		}
	}


	/**
	 *  Atomic - 2 transaction, concurrent update, aborted transaction shouldn't save any data
	 */
	public function testTransactionAllCommit_atomic1() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;

		$txn1 = new Transaction();
		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn2->joinTransaction($txn2);

		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();

		$obj_txn1->getById($id2);
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';

		$obj_txn2->save();
		$obj_txn1->field1 = 'bar2';

		try {
			$obj_txn1->save();
			$this->fail('Late write did not throw exception');
		} catch (OptimisticLockException $ex) {

		}

		$txn2->commit();

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);

		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}

	/**
	 *  See uncommitted data in the same txn
	 */
	public function testTransactionAllCommit_uncommitted() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;

		$txn1 = new Transaction();

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);

		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj2_txn1 = new TestModel2($this->adapter);
		$obj2_txn1->joinTransaction($txn1);
		$obj2_txn1->getById($id1);
		$this->assertEquals('foo1', $obj2_txn1->field1);

	}

	/**
	 *  Updating data twice will persist the right data
	 */
	public function testTransactionAllCommit_updatingTwice() {

		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;

		$txn1 = new Transaction();

		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);

		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();

		$obj_txn1->field1 = 'foo2';
		$obj_txn1->save();

		$txn1->commit();

		$obj2 = new TestModel2($this->adapter);
		$obj2->getById($id1);
		$this->assertEquals('foo2', $obj2->field1);

	}
		

	public function testCascadingTransaction() {
		$obj1 = new TestModel1($this->adapter);
		$obj2 = new TestModel2($this->adapter);
		$obj2->field1 = 'bar';
		$obj2->save();

		$obj1->field1 = 'foo';
		$obj1->field2 = array(1223);
		$obj1->field3 = $obj2;
		$obj1->save();

		$id1 = $obj1->id;
		$id2 = $obj2->id;

		//now in transaction
		$txn1 = new Transaction();
		$obj1 = new TestModel1($this->adapter);
		$obj1->joinTransaction($txn1);
		$obj1->getById($id1);
		$obj2 = $obj1->field3;
		$obj2->field1 = 'bat';

		$obj2->save();
		$txn1->abort();

		//since we have not committed, obj2.field should still be "foo"

		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id2);
		$this->assertEquals('bar', $objTest->field1);
	}
	
	public function testDeleteWithoutTxn() {
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'test';
		$obj->save();
		
		$id = $obj->id;
		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id);
		$objTest->delete();
		
		$objTest = new TestModel2($this->adapter);
		$this->assertFalse($objTest->getById($id)); //gone!		
	}
	

	/**
	 *  Atomic - 2 transaction, concurrent update, aborted transaction shouldn't save any data
	 */
	public function testTransactionWDeleteAllCommit_atomic1() {
	
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'foo';
		$obj->save();
		$id1 = $obj->id;
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'bar';
		$obj->save();
		$id2 = $obj->id;
	
		$txn1 = new Transaction();
		usleep(100); //sleep 100ms to ensure txn2 is benind txn1
		$txn2 = new Transaction();
		$this->assertTrue($txn2->getTimestamp() > $txn1->getTimestamp(), 'Transaction 2 is not newer than transaction 1');
	
		//transaction 1
		$obj_txn1 = new TestModel2($this->adapter);
		$obj_txn2 = new TestModel2($this->adapter);
		$obj_txn1->joinTransaction($txn1);
		$obj_txn2->joinTransaction($txn2);
	
		$obj_txn1->getById($id1);
		$obj_txn1->field1 = 'foo1';
		$obj_txn1->save();
	
		$obj_txn2->getById($id1);
		$obj_txn2->field1 = 'foo2';
		$obj_txn2->save();
	
		$obj_txn1->getById($id2);
		$obj_txn2->getById($id2);
		$obj_txn2->field1 = 'bar2';
	
		$obj_txn2->save();
		$obj_txn1->field1 = 'bar2';
	
		try {
			$obj_txn1->delete();
			$this->fail('Late delete did not throw exception');
		} catch (OptimisticLockException $ex) {
	
		}
	
		$txn2->commit();
	
		$objTest = new TestModel2($this->adapter);
		$objTest->getById($id1);
		$this->assertEquals('foo2', $objTest->field1);
	
		$objTest->getById($id2);
		$this->assertEquals('bar2', $objTest->field1);
	}


}
?>