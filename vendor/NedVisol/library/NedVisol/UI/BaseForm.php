<?php 
namespace NedVisol\UI;

class BaseForm {

	const MODE_SINGLE = 1;
	const MODE_ARRAY = 2;

	/**
	 * Definition of the Form
	 * @var array Definition in the following format:
	 * ( 'name' => Form name,
	 *   'title' => Form title,
	 * 	 'models' => (
	 * 		model alias => model full name
	 * 	 )
	 *   'fields' => [
	 *       ( 'name' => field name,
	 *         'label' => user-facing label,
	 *         'validation' => ...,
	 *         'modelProp' => model alias.property name (optional)
	 *       ),...
	 *   ],
	 *   'ops' => [
	 *    ( 'name' => operation name,
	 *    	'label' => user-facing label,
	 *      'validation' => ....,
	 *     )
	 *   ]
	 */
	private $definition;

	/**
	 * @var array Form data
	 */
	private $data;

	/*
	 * @var string single|array
	*/
	private $formMode;

	public function __construct($definition) {
		$this->data = array();
		$this->definition = $definition;
		$this->formMode = self::MODE_SINGLE;
	}

	public function render() {
		$retObj = new \ArrayObject($this->definition);
		$ret = $retObj->getArrayCopy();

		$ret['data'] = $this->formatData();
		return $ret;
	}

	/**
	 * depends on supplied $data, add model to form data or replace if it is an array
	 * @param string $alias
	 * @param mixed $data single object or an array of object
	 * @throws \InvalidArgumentException
	 */
	public function setData($alias, $data) {
		//verify that alias exists
		if (!isset($this->definition['models'][$alias])) {
			throw new \InvalidArgumentException("Undefined model alias $alias");
		}

		if (is_array($data)) {
			$this->formMode = self::MODE_ARRAY;
			$this->data = $data;
		} else {
			$this->data[$alias] = $data;
		}
	}

	/**
	 * @return array of selected fields
	 */
	private function formatData() {
		$ret = array();
		//first see if this is single or array of objects
		if ($this->formMode == self::MODE_ARRAY) {
			//allow one type of model only
			foreach($this->data as $obj) {
				$ret[] = $this->selectFields($obj);
			}
		} else {
			$ret = $this->selectFields($this->data);
		}
		return $ret;
	}

	
	/**
	 * 
	 * @param unknown_type $data
	 * @return array Array of field values
	 */
	private function selectFields($data) {
		$ret = array();

		if (is_object($data)) {
			//only look for modelProp
			foreach($this->definition['fields'] as $field) {
				if (isset($field['modelProp'])) {
					$modelField = explode('.',$field['modelProp']);
					//ignore alias
					$ret[$field['name']] = $data->$modelField[1];
				}
			}
		} else {
			//alias array
			foreach($this->definition['fields'] as $field) {
				if (!isset($field['modelProp'])) {
					//raw value
					if ((!is_array($data)) && (isset($data[$field['name']]))) {
						$ret[$field['name']] = $data[$field['name']];
					} else {
						//pick from model
						$modelField = explode('.',$field['modelProp']);
						$obj = $data[$modelField[0]];
						$ret[$field['name']] = $obj->$modelField[1];
					}
				}
			}
		}
		return $ret;
	}
}

?>