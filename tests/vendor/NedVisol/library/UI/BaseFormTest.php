<?php

use NedVisol\Orm\TestModel2;
use NedVisol\StorageAdapter\Hbase\HbaseClient;

include_once __DIR__ . '/TestForm/TestForm1.php';
include_once __DIR__ . '/../Orm/TestModel/TestModel2.php';

class BaseFormTest extends PHPUnit_Framework_TestCase {

	private $adapter;

	public function setUp() {
		$param = array('host'=> HBASE_THRIFT_HOST ,'port'=> HBASE_THRIFT_PORT);
		$this->adapter = new HbaseClient($param);
	}

	public function testRenderBlankForm() {
		$form = new \TestForm1();
		$ret = $form->render();
		$json = json_encode($ret);
		$this->assertContains('"data":[]', $json);
	}

	public function testRenderWithSingleData() {
		$form = new \TestForm1();
		$obj = new TestModel2($this->adapter);
		$obj->field1 = 'value1';
		$obj->field2 = 'value2';
		$form->setData('t2', $obj);
		$ret = $form->render();
		echo json_encode($ret);
		
	}
}