<?php
namespace NedVisol\Orm;

use NedVisol\Orm\BaseModel;

class User extends BaseModel {
	
	public function __construct(IStorage $adapter = null, $propertiesDefinition = array()) {
		parent::__construct($adapter, $propertiesDefinition);
		
		$propertiesDefinition = array(
			'username' => array(),
			'password' => array(),
		);
	}
}