<?php
namespace NedVisol\Orm;

use NedVisol\Orm\BaseModel;
use NedVisol\StorageAdapter\IStorage;

class TestModel1 extends BaseModel {
	public function __construct(IStorage $adapter) {
		$props = array (
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
		parent::__construct($adapter, $props, 'testModel1');
	}
}
