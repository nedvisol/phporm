<?php
use NedVisol\UI\BaseForm;

class TestForm1 extends BaseForm {
	public function __construct() {
		$definition = array(
				'name' => 'testForm1',
				'title' => 'Test Form 1',
				'models' => array(
						't2' => 'TestModel2'
				),
				'fields' => array(
					array('name' => 'ffield1', 'label'=>'Form Field 1', 'modelProp'=>'t2.field1'),
					array('name' => 'ffield2', 'label'=>'Form Field 2', 'modelProp'=>'t2.field2'),
				),
				'ops' => array(
					array('name'=>'save', 'label'=>'Save'),
					array('name'=>'delete', 'label'=>'Delete'),
				),
			);
		parent::__construct($definition);
	}
}