<?php
namespace NedVisol\Orm;

use NedVisol\Orm\BaseModel;
use NedVisol\StorageAdapter\IStorage;

class TestModel2 extends BaseModel {
	public function __construct(IStorage $adapter) {
		$props = array (
				'field1' => array (
						'dataType' => BaseModel::DATATYPE_SINGLE,
						'required' => false
				),
				'field2' => array (
						'dataType' => BaseModel::DATATYPE_SINGLE,
						'required' => false
				),
				'refArray' => array (
						'dataType' => BaseModel::DATATYPE_REFARRAY,
						'required' => false
				),
				);
		parent::__construct($adapter, $props, 'testModel2');
	}
}