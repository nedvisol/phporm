<?php
namespace NedVisol\Orm;

use NedVisol\StorageAdapter\IStorage;

class BaseModel
{
	/******
	 * disable 'data'; drop 'data'; create 'data','_system_','referenceCollection','testModel1','testModel2','testModel3'
	*
	*/

	const DATASTATE_NEW = 1;
	const DATASTATE_RETRIEVED = 2;
	const DATASTATE_MODIFIED = 3;

	const DATATYPE_SINGLE = 1;
	const DATATYPE_ARRAY = 2;
	const DATATYPE_REF = 3;
	const DATATYPE_REFARRAY = 4;

	const STORAGE_TABLENAME = 'data';
	const STORAGE_TRANSACTIONLOGTABLE = 'log';
	const STORAGE_MODELCOL = '_model_';
	const STORAGE_VERCOL = '_version_';
	
	const UUID_SIZE = 1;
	
	const CLASS_DEF_NAMESPACE = 'NedVisol\Orm\Definition';

	


	/**
	 * @var string name of this model
	 */
	protected $name;

	/**
	 * @var string Row ID of this model
	 */
	protected $id;

	/**
	 * @var array Properties of this model. Array structure should be
	 * 	( <property name> => (
	 * 		'dataType' => DATATTPE_SINGLE|ARRAY|REF|REFARRAY,
	 * 		'reference' => <model class name, must inherits BaseModel>,
	 * 		'required' => true|false,
	 * 		'readonly' => true|false <optional - default=false>
	 * 		'hidden' => true|false <optional -default=false>; hidden properties cannot be read or set,
	 * 		'system' => true|false <optional - default=false>; system property
	 *  )
	 */
	protected $propertiesDefinition;

	/**
	 * @var int DATASTATE_NEW|DATASTATE_RETRIEVED|DATASTATE_MODIFIED
	 */
	protected $dataState;

	/**
	 * @var array storing values retrieved from storage. Array structure:
	 *  ( <property name> => <value> )
	 *  value can be string or object of class LazyLoadReference
	 */
	protected $retrievedValues;

	/**
	 * @var int retrieved version
	 */
	protected $retrievedVersion;

	/**
	 * @var array storing updated or new values
	 */
	protected $updatedValues;

	protected $currentUser;

	/**
	 * @var NedVisol\Orm\BaseModel Parent object, used for transaction tree
	 */
	public $parentObject;

	/**
	 * @var NedVisol\Orm\Transaction Transaciton object, null means this model is not participating in a txn
	 */
	private $transaction;

	public $adapter;
	
	private $config;

	public function __construct(IStorage $adapter = null, $propertiesDefinition = array(), $name = null) {
		$this->adapter = $adapter;
		$this->dataState = self::DATASTATE_NEW;		
		$this->setPropertiesDefinition($propertiesDefinition);
		$this->retrievedValues = array();
		$this->updatedValues = array();
		$this->name = $name;
	}

	public function getAdapter() {
		return $this->adapter;
	}
	
	public function setConfig($config) {
		$this->config = $config;
	}
	
	private function loadPropertiesDefinition($propertiesDefinition) {
		$defClass = self::CLASS_DEF_NAMESPACE . '\\'. $this->name;
		if (!class_exists($defClass)) {
			$this->generatePropertiesDefinition($propertiesDefinition);
		}
		return $defClass::definition;	
	}
	
	private function generatePropertiesDefinition($propertiesDefinition) {
		//read from annotations
		
		//add internal properties
		$propertiesDef = $this->addInternalProperties($propertiesDefinition);
		
	}

	private function addInternalProperties($propertiesDefinition) {
		//add standard properties;
		$propertiesDefinition['createdBy'] = array (
				'dataType' => self::DATATYPE_REF,
				'reference' => 'NedVisol\Orm\User',
				'system' => true,
				'readonly' => true
		);
		$propertiesDefinition['createdDate'] = array (
				'dataType' => self::DATATYPE_SINGLE,
				'system' => true,
				'readonly' => true
		);
		$propertiesDefinition['lastUpdatedBy'] = array (
				'dataType' => self::DATATYPE_REF,
				'reference' => 'NedVisol\Orm\User',
				'system' => true,
				'readonly' => true
		);
		$propertiesDefinition['lastUpdatedDate'] = array (
				'dataType' => self::DATATYPE_SINGLE,
				'system' => true,
				'readonly' => true
		);
		return $propertiesDefinition;
	}

	public function setCurrentUser($user) {
		if (!is_a($user,'NedVisol\Orm\User')) {
			throw new InvalidOperationException('Invalid value for user');
		}
	}

	private function getPropertyAttribute($name, $attr, $default) {
		if (isset($this->propertiesDefinition[$name][$attr])) {
			return $this->propertiesDefinition[$name][$attr];
		}
		return $default;
	}

	public function __set($name, $value) {
		if (!isset($this->propertiesDefinition[$name])) {
			throw new \InvalidArgumentException ("Setting unknown property [$name]");
		}
		if ($this->getPropertyAttribute($name, 'readonly', false)) {
			throw new InvalidOperationException("This property is readonly [$name]");
		}
		if ($this->getPropertyAttribute($name, 'hidden', false)) {
			throw new InvalidOperationException("This property is hidden [$name]");
		}
		if ($this->getPropertyAttribute($name, 'dataType', self::DATATYPE_REFARRAY) == self::DATATYPE_REFARRAY) {
			throw new InvalidOperationException("This property cannot be set [$name]");
		}

		$this->set($name, $value);
	}

	protected function set($name, $value) {
		$this->updatedValues[$name] = $value;
	}

	public function __get($name) {
		if ($name == 'dataState') {
			return $this->dataState;
		}
		if ($name == 'id') {
			return $this->id;
		}
		if ($name == 'name') {
			return $this->name;
		}
		if (!isset($this->propertiesDefinition[$name])) {
			throw new \InvalidArgumentException ("Getting unknown property [$name]");
		}
		if (isset($this->updatedValues[$name])) {
			return $this->updatedValues[$name];
		}
		if ($this->getPropertyAttribute($name, 'hidden', false)) {
			throw new InvalidOperationException("This property is hidden [$name]");
		}

		return $this->get($name);

	}

	protected function setParentObject($parentObj) {
		$this->parentObject = $parentObj;
	}

	protected function getTransaction() {
		return $this->transaction;
	}

	protected function get($name) {
		$value = null;
		if(isset($this->updatedValues[$name])) {
			$value = $this->updatedValues[$name];
		} else if(isset($this->retrievedValues[$name])) {
			$value = $this->retrievedValues[$name];
		}

		//check if this is ref array
		if ($this->getPropertyAttribute($name, 'dataType', self::DATATYPE_REFARRAY) == self::DATATYPE_REFARRAY) {
			if ($value == null) {
				//new ref array - create a new object
				$refArray = new ReferenceCollection($this->adapter);
				$refArray->setParentObject($this);
				$refArray->save();
				$value = $refArray;
				//stick it back into the values
				$this->updatedValues[$name] = $value;
				$this->retrievedValues[$name] = $value;
			} else {
				//existing ref array - bring it up, do nothing

			}
		}

		//check if this is lazy reference
		if(is_a($value,'NedVisol\Orm\LazyLoadReference')) {
			//@TODO update this - look up class and load
			$value = $value->loadObject();
			//replace lazy load ref
			$this->retrievedValues[$name] = $value;
		}
		return $value;
	}

	private function getCallerSecurityContext() {
		$debugTrace = debug_backtrace();
		if (!isset($debugTrace[1]['object'])) {
			throw new InvalidOperationException('Unable to verify caller security context (non-object)', InvalidOperationException::SECURITY_NONOBJ);
		}
		$callerObj = $debugTrace[2]['object'];
		if (method_exists($callerObj, 'getSecurityContext')) {
			return $callerObj->getSecurityContext();
		}
		return null;
	}

	public function __call($name, $args) {
		if (strpos($name, 'privileged') === 0) {
			$actualName = str_replace('privileged', '', $name);
			$actualName = lcfirst($actualName);

			//check the caller class
			$securityContext = $this->getCallerSecurityContext();

			//@TODO - implement security context verification
			if ($securityContext == 'level0') {
				if (!method_exists($this, $name)) {
					throw new InvalidOperationException("calling non-existing secure method [$name]", InvalidOperationException::SECURITY_NOMETHODSEC);
				}
				return $this->$name($args);
			} else {
				throw new InvalidOperationException("Unable to verify caller security context",InvalidOperationException::SECURITY_NOSECURE);
			}
		}

		throw new InvalidOperationException("calling non-existing method [$name]", InvalidOperationException::SECURITY_NOMETHOD);
	}

	public function save() {
		//validate all values
		$this->validateValues();

		$mergedValues = array_merge($this->retrievedValues, $this->updatedValues);
		$currentDate = new \DateTime();
		$mergedValues['lastUpdatedDate'] = $currentDate->getTimestamp();
		if ($this->currentUser != null) {
			$mergedValues['lastUpdatedBy'] = $this->currentUser;
		}
		if ($this->dataState == self::DATASTATE_NEW) {
			$mergedValues['createdDate'] = $currentDate->getTimestamp();
			if ($this->currentUser != null) {
				$mergedValues['createdBy'] = $this->currentUser;
			}

			//assign an ID
			$this->id = $this->GenerateUid();
			$this->retrievedVersion = 0;
		}
		$columns = array();

		foreach($this->propertiesDefinition as $name => $definition) {
			if (!isset($mergedValues[$name])) {
				continue;
			}
			$cq = $name;
			$cf = $this->getPropertyAttribute($name, 'system', false)?'_system_': $this->name;
			$value = isset($mergedValues[$name])?$mergedValues[$name]:null;

			//serialize data
			if ($value != null) {
				$dataType = $this->getPropertyAttribute($name, 'dataType', self::DATATYPE_SINGLE);
				switch($dataType) {
					case self::DATATYPE_REF:
					case self::DATATYPE_REFARRAY:
						//assming validateValues makes sure this is a model object
						$value = $value->name.'#'.$value->id;
						break;
					case self::DATATYPE_ARRAY:
						$value = serialize($value);
						break;
					case self::DATATYPE_SINGLE:
						$value = serialize($value); //serialize anyway
						break;
				}
			}
			$columns["$cf.$cq"] = $value!=null?$value:'';
		}

		$columns['_system_.'.self::STORAGE_MODELCOL] = $this->name;
		$columns['_system_.'.self::STORAGE_VERCOL] = $this->retrievedVersion + 1;

		$data = array(
				'id' => $this->id,
				'columns' => $columns
		);


		$previousVersion = $this->writeLock($data); //get write lock

		//check to see if matched retrieved version
		if ($previousVersion != null) {
			$previousRecordVersion = $previousVersion['columns']['_system_.'.self::STORAGE_VERCOL];
			if ($previousRecordVersion != $this->retrievedVersion) {
				//can't write this, abort the transaction
				$this->transaction->abort();
				throw new OptimisticLockException("The record was updated after it was read", OptimisticLockException::INVALID_RECORDVERSION);
			}
		}
		if ($this->transaction->getAutoCommit()) {
			$this->transaction->commit();
		}

		$this->dataState = self::DATASTATE_RETRIEVED;
		$this->retrievedVersion = $this->retrievedVersion+1;
		$this->retrievedValues = $mergedValues;
		return true;
	}

	private function populateValue($name, $value) {
		if (!isset($this->propertiesDefinition[$name])) {
			//something's wrong
			throw new InvalidOperationException("Undefined property retrieved from storage [$name]");
		}

		$dataType = $this->getPropertyAttribute($name, 'dataType', self::DATATYPE_SINGLE);
		switch($dataType) {
			case self::DATATYPE_SINGLE:
				$value = unserialize($value);
				break;
			case self::DATATYPE_ARRAY:
				$value = unserialize($value);
				break;
			case self::DATATYPE_REF:
			case self::DATATYPE_REFARRAY:
				$nameid = explode('#', $value);
				$model = $nameid[0];
				$id = $nameid[1];
				$lazyRef = new LazyLoadReference($id, $model, $this->adapter, $this);
				$value = $lazyRef;
				break;
			default:
		}
		$this->retrievedValues[$name] = $value;
	}

	private function populateValuesFromStorage($object, $result) {
		$this->readLock($result);
		$this->id = $result['id'];
		$columns = $result['columns'];
		foreach($columns as $name => $value) {
			$cfcq = explode('.', $name);
			$cf = $cfcq[0];
			$cq = $cfcq[1];

			if ($cf == '_system_') {
				if ($cq[0] != '_') {
					$this->populateValue($cq, $value);
				} else {
					switch ($cq) {
						case self::STORAGE_MODELCOL:
							$object->name = $value;
							break;
						case self::STORAGE_VERCOL:

							$object->retrievedVersion = intval($value);
							break;
						default:
							//ignore unknown column
							//throw new InvalidOperationException("Undefined system property retrieved from storage [$cq]");
					}
				}

			} else {
				$object->populateValue($cq, $value);
			}

		}
	}

	/**
	 * Recursively look up parent's transaction to participate, if no parent, then create new transaction
	 * @return NedVisol\Orm\Transaction
	 */
	protected function lookupTransaction() {
		if ($this->transaction != null) {
			//good - do nothing
		}  else {
			if ($this->parentObject != null) {
				//this object has a parent, get from parent
				$this->transaction = $this->parentObject->lookupTransaction();
			} else {
				//top-level or orphan, create an auto-commit transaction
				$this->transaction = new Transaction(true);
			}
		}

		return $this->transaction;
	}

	/**
	 * Check to make sure we have Optimistic read lock on this row
	 * @param array $result
	 */
	private function readLock($result) {
		$this->lookupTransaction();
		//check write timestamp
		$columns = $result['columns'];
		$writeTS = 0;
		if (isset($columns['_system_._writeTS_'])) {
			$writeTS = $columns['_system_._writeTS_'];
			if ($this->transaction->getTimestamp() < $writeTS) {
				//this object was written by a transaction that started after this txn, abort
				$this->transaction->abort();
				throw new OptimisticLockException('Unable to obtain read lock',OptimisticLockException::READ_LOCK);
			}
		}
		//we got read lock, write read timestamp
		$readTSPut = array(
				'id' => $result['id'],
				'columns' => array('_system_._readTS_' => $this->transaction->getTimestamp())
		);
		if ($writeTS != 0) {
			$readTSPut['checks'] = array('_system_._writeTS_' => $writeTS);
		}
		$ret = $this->adapter->putRow(self::STORAGE_TABLENAME, $readTSPut);

		if (!$ret) {
			$this->transaction->abort();
			throw new OptimisticLockException('Unable to obtain read lock',OptimisticLockException::UNABLE_LOCK);
		}
	}

	/**
	 * Record write timestamp
	 */
	private function updateWriteTS($result, $readTS)
	{
		//we got write lock, write write timestamp
		$writeTSPut = array(
				'id' => $result['id'],
				'columns' => array('_system_._writeTS_' => $this->transaction->getTimestamp())
		);
		if ($readTS != 0) {
			$writeTSPut['checks'] = array('_system_._readTS_' => $readTS);
		}
		$ret = $this->adapter->putRow(self::STORAGE_TABLENAME, $writeTSPut);

		if (!$ret) {
			$this->transaction->abort();
			throw new OptimisticLockException('Unable to obtain write lock',OptimisticLockException::UNABLE_LOCK);
		}
	}

	/**
	 * Obtain optimistic write lock
	 * @param array $data Data to be written (will be written to transaction log)
	 * @param bool $delete if true, mark operations as delete
	 * @return array Data array of the 'previous' version to be overwritten, this is the version we "locked" on
	 * @throws OptimisticLockException
	 */
	private function writeLock($data, $delete = false) {
		$this->lookupTransaction();
		//get fresh data
		$id = $this->id;

		//see if we already have a lock on this row
		$previousLock = $this->transaction->getPendingData($id);
		$result = null;
		if ($previousLock != null) {
			//we already have the lock, don't need to do anything . . .
			$result = $previousLock;
		} else {
			$results = $this->adapter->getRows(self::STORAGE_TABLENAME, array($id));
			$result = null;
			if (count($results)==0) {
				//nothing returned . . .new row, automatic locked
			} else {
				$result = $results[0];
				//check read timestamp
				$columns = $result['columns'];
				$readTS = 0;
				if (isset($columns['_system_._readTS_'])) {
					$readTS = $columns['_system_._readTS_'];
					if ($this->transaction->getTimestamp() < $readTS) {
						//this object was read by a transaction that started after this txn, abort writing
						$this->transaction->abort();
						throw new OptimisticLockException('Unable to obtain write lock',OptimisticLockException::WRITE_LOCK);
					}
				}
				$this->updateWriteTS($result, $readTS);
			}
		}
		//don't write data right away, save it in txn log
		$op = $delete?'delete':($result==null?'insert':'update');
		$this->transaction->writeLog($this, $data, self::STORAGE_TABLENAME, $op);
		return $result;
	}



	public function getById($id) {
		$this->beforeRetrieve();
		
		if ($this->transaction != null) {
			//check txn for uncommited data
			$data = $this->transaction->getPendingData($id);
			if ($data != null) {
				$this->populateValuesFromStorage($this, $data);
				$this->dataState = self::DATASTATE_RETRIEVED;
				return $this;
			}
		}

		$results = $this->adapter->getRows(self::STORAGE_TABLENAME, array($id));
		if (count($results) == 0) {
			//nothing returned. . .
			return false;
		}
		$result = $results[0];
		$this->populateValuesFromStorage($this, $result);
		
		//clear updated values
		$this->updatedValues = array();

		$this->dataState = self::DATASTATE_RETRIEVED;
		$this->afterRetrieve();

		return $this;
	}
	
	/**
	 * Delete object from storage
	 */
	public function delete() {
		if ($this->id == null) {
			throw new InvalidOperationException('Unable to delete unsaved object');
		}
		
		//get write lock
		$this->writeLock(array('id'=> $this->id), true);		
		
		if ($this->transaction->getAutoCommit()) {
			$this->transaction->commit();
		}
		
		$this->dataState = self::DATASTATE_NEW;
		$this->retrievedValues = array();
		$this->updatedValues = array();
		
	}

	private function validateValues() {
		$mergedValues = array_merge($this->retrievedValues, $this->updatedValues);
		foreach($this->propertiesDefinition as $propName => $definition) {
			//skip system properties
			if ($this->getPropertyAttribute($propName, 'system', false) == true) {
				continue;
			}

			//check if required

			if ((isset($definition['required'])) &&  ($definition['required'] === true) && (!isset($mergedValues[$propName]))) {
				throw new InvalidOperationException("Field [$propName] is required but missing");
			}

			if (!isset($mergedValues[$propName])) {
				continue;
			}

			//check data type
			$value = $mergedValues[$propName];
			switch ($definition['dataType']) {
				case self::DATATYPE_ARRAY:
					if (!is_array($value)) {
						throw new InvalidOperationException("Invalid data type for [$propName], expecting an array");
					}
					break;
				case self::DATATYPE_REF:
					if (!is_a($value, 'NedVisol\Orm\BaseModel')) {
						throw new InvalidOperationException("Invalid data type for [$propName], expecting a reference to a model");
					}
					//and data must come from the database
					if ($value->dataState != self::DATASTATE_RETRIEVED) {
						throw new InvalidOperationException("Referenced model must be persisted in storage [$propName] state:[{$value->dataState}]");
					}
					break;
				case self::DATATYPE_REFARRAY:
				default:
			}
		}
	}

	public function joinTransaction($transaction) {
		if ($this->transaction != null) {
			throw new InvalidOperationException("Unable to change transaction");
		}
		$this->transaction = $transaction;
	}
	

	public static function GenerateUid() {
		/*$uid = uniqid('', true);
		$uid = hash('md5', $uid . microtime(true));
		return $uid;*/
		
		$uuid = '';
		for ($i=0; $i < self::UUID_SIZE; $i++) {
			$uuid .= str_pad(base_convert(mt_rand(0, 0x7fffffff), 10, 36), 6, '0', STR_PAD_LEFT);
		}
		
		return $uuid;		
	}
	
	//meant to be overriden by subclasses
	
	/**
	 * To be called automatically before restoring data from storage 
	 */
	public function beforeRetrieve() {
					
	}
	
	/**
	 * To be called automatically after restoring from storage
	 */
	public function afterRetrieve() {
		
	}
}