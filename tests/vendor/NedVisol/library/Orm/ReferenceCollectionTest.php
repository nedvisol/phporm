<?php
use NedVisol\Orm\TestModel2;

use NedVisol\Orm\OptimisticLockException;

use NedVisol\Orm\Transaction;

use NedVisol\StorageAdapter\Hbase\HbaseClient;
use NedVisol\Orm\InvalidOperationException;
use NedVisol\Orm\LazyLoadReference;
use NedVisol\Orm\BaseModel;


class ReferenceCollectionTest extends PHPUnit_Framework_TestCase
{


	private $props;
	private $propsBasic;
	private $adapter;

	public function setUp() {
		$param = array('host'=> HBASE_THRIFT_HOST ,'port'=> HBASE_THRIFT_PORT);		
		$this->adapter = new HbaseClient($param);
	}

	public function testAddObjs() {
		$objMaster = new TestModel2($this->adapter);
		$objMaster->field1 = 'master';
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'referenced';
		$obj->save();
		$objMaster->refArray->add($obj);
		$objMaster->refArray->add($obj);
		$objMaster->refArray->add($obj);
		$this->assertTrue($objMaster->refArray->counter > 0);
	}

	public function testAddRetrieveObjs() {
		$objMaster = new TestModel2($this->adapter);
		$objMaster->field1 = 'master';
		$objs = array();
		for($i=0;$i < 5; $i++) {
			$objs[$i] = new TestModel2($this->adapter);
			$objs[$i]->field1 = 'reference-'.$i;
			$objs[$i]->save();
		}
		for($i=0;$i < 5; $i++) {
			$objMaster->refArray->add($objs[$i]);
		}
		$objMaster->save();
		$id = $objMaster->id;
		$this->assertEquals(5, $objMaster->refArray->counter);

		$testObj = new TestModel2($this->adapter);
		$testObj->getById($id);
			
		$this->assertEquals(5, $testObj->refArray->counter);
		$i = 0;
		$refArray = $testObj->refArray;
		foreach($refArray as $obj) {
			$value = $obj->field1;
			$this->assertEquals('reference-'.$i, $value);
			$i++;
		}
	}

	public function testAddToExisting() {
		$objMaster = new TestModel2($this->adapter);
		$objMaster->field1 = 'master';
		$objs = array();
		for($i=0;$i < 5; $i++) {
			$objs[$i] = new TestModel2($this->adapter);
			$objs[$i]->field1 = 'reference-'.$i;
			$objs[$i]->save();
		}
		for($i=0;$i < 5; $i++) {
			$objMaster->refArray->add($objs[$i]);
		}
		$objMaster->save();
		$id = $objMaster->id;
		$this->assertEquals(5, $objMaster->refArray->counter);

		$testObj = new TestModel2($this->adapter);
		$testObj->getById($id);
			
		$this->assertEquals(5, $testObj->refArray->counter);
		$i = 0;
		$refArray = $testObj->refArray;
		foreach($refArray as $obj) {
			$value = $obj->field1;
			$this->assertEquals('reference-'.$i, $value);
			$i++;
		}

		//add another 5 objects
		for($i=5;$i < 10; $i++) {
			$objs[$i] = new TestModel2($this->adapter);
			$objs[$i]->field1 = 'reference-'.$i;
			$objs[$i]->save();
		}
		for($i=5;$i < 10; $i++) {
			$testObj->refArray->add($objs[$i]);
		}

		//retrieve, we should have 10
		$testObj2 = new TestModel2($this->adapter);
		$testObj2->getById($id);
			
		$this->assertEquals(10, $testObj2->refArray->counter);
		$i = 0;
		$refArray = $testObj2->refArray;
		foreach($refArray as $obj) {
			$value = $obj->field1;
			$this->assertEquals('reference-'.$i, $value);
			$i++;
		}
	}

	public function testRemoveIdx() {
		$objMaster = new TestModel2($this->adapter);
		$objMaster->field1 = 'master';
		$objs = array();
		for($i=0;$i < 5; $i++) {
			$objs[$i] = new TestModel2($this->adapter);
			$objs[$i]->field1 = 'reference-'.$i;
			$objs[$i]->save();
		}
		for($i=0;$i < 5; $i++) {
			$objMaster->refArray->add($objs[$i]);
		}
		$objMaster->save();
		$id = $objMaster->id;
		$this->assertEquals(5, $objMaster->refArray->counter);

		$testObj = new TestModel2($this->adapter);
		$testObj->getById($id);
			
		$this->assertEquals(5, $testObj->refArray->count());

		$testObj->refArray->remove(3); //remove the 3rd idx

		$testObj = new TestModel2($this->adapter); //retrieve again
		$testObj->getById($id);
		$this->assertEquals(4, $testObj->refArray->count()); //should be 4 now

		$i = 0;
		$refArray = $testObj->refArray;
		foreach($refArray as $obj) {
			$value = $obj->field1;
			$this->assertEquals('reference-'.$i, $value);
			$i++;
			if ($i == 3) {
				$i = 4; //skip 3rd index
			}
		}
	}

}