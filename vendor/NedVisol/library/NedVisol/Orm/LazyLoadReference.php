<?php
namespace NedVisol\Orm;

use NedVisol\Orm\BaseModel;

class LazyLoadReference extends BaseModel {
	
	private static $lazyLoadLookupOrders = array('NedVisol\Orm\Custom', 'NedVisol\Orm');
	
	public function __construct($id, $name = NULL, $adapter = NULL, $parentObject = NULL) {
		$this->id = $id;
		$this->name = $name;
		$this->adapter = $adapter;
		$this->parentObject = $parentObject;
	}
	
	public function loadObject() {
		$found = false;
		$modelName = ucfirst($this->name);
		$value = null;
		foreach(self::$lazyLoadLookupOrders as $lookupOrder) {
			$className = "$lookupOrder\\$modelName";
			if (class_exists($className)) {
				$object = new $className($this->adapter);
				$object->setParentObject($this->parentObject);
				$obj = $object->getById($this->id);
				if ($obj === false) {
					$value = null;
				} else {
					$value = $object;
				}
				$found = true;
				break;
			}
		}
		if (!$found) {
			throw new InvalidOperationException("Unable to instantiate model [$modelName]");
		}
		return $value;
	}
}