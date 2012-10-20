<?php
namespace NedVisol\Orm;

use NedVisol\StorageAdapter\IStorage;

class ReferenceCollection extends BaseModel implements \Iterator{

	const MAX_ITEMS = 5000;

	const STORAGE_COLLECTIONTABLE = 'collection';

	/*****
	 * disable 'collection'; drop 'collection'; create 'collection','_system_','item'
	*/

	/**
	 * @var int Maximum retrieved items
	 */
	private $max_items;

	/**
	 * @var array Cached items loaded from storage
	 */
	private $cache;

	private $position;

	public function __construct(IStorage $adapter) {
		$props = array (
				'counter' => array (
						'dataType' => BaseModel::DATATYPE_SINGLE,
						'required' => true
				)
		);
		parent::__construct($adapter, $props, 'referenceCollection');
		$this->set('counter',0);
		$this->cache = array();
	}

	/**
	 * Add a model object to this collection
	 * @param NedVisol\Orm\BaseModel $model Model to be added
	 */
	public function add(BaseModel $model) {
		$this->set('counter', ($this->get('counter'))+1);
		$this->save();
		// if we can save that means we got the write lock
		$counter = str_pad(base_convert($this->get('counter'),10,36),6,'0',STR_PAD_LEFT);
		$itemId = $this->id . '-' . $counter;
		$modelId = $model->name . '#' . $model->id;
		$data = array(
				'id'=> $itemId,
				'columns' => array('item.refid' => $modelId)
		);			
		$this->lookupTransaction();
		$this->getTransaction()->writeLog($this, $data, self::STORAGE_COLLECTIONTABLE, 'insert');
		$this->cache[] = array('id'=> $itemId, 'item'=> $model);

		if ($this->getTransaction()->getAutoCommit()) {
			$this->getTransaction()->commit();
		}
	}
	
	/**
	 * returns a number of items in this Reference array
	 * @return int number of items
	 */
	public function count() {
		return count($this->cache);
	}
	
	/**
	 * Remove item in the $idx position
	 * @param int $idx position of the item to be removed
	 */
	public function remove($idx) {
		if (!isset($this->cache[$idx])) {
			throw new \InvalidArgumentException("Index $idx does not exist");
		}
		$this->save(); //just to get the write lock
		$itemId = $this->cache[$idx]['id'];
		$this->lookupTransaction();
		$data = array('id'=> $itemId);
		$this->getTransaction()->writeLog($this, $data, self::STORAGE_COLLECTIONTABLE, 'delete');		
		
		array_splice($this->cache, $idx, 1);
					
		if ($this->getTransaction()->getAutoCommit()) {
			$this->getTransaction()->commit();				
		}
	}

	/**
	 * (non-PHPdoc) Load references from storage
	 * @see NedVisol\Orm.BaseModel::afterRetrieve()
	 */
	public function afterRetrieve() {
		$results = $this->adapter->retrieveIdsBeginsWith(self::STORAGE_COLLECTIONTABLE, $this->id);
		foreach($results as $result) {
			$id = $result['id'];
			$columns = $result['columns'];

			$modelId = $columns['item.refid'];
			$nameid = explode('#', $modelId);
			$model = $nameid[0];
			$refid = $nameid[1];
			$lazyRef = new LazyLoadReference($refid, $model, $this->adapter, $this);
			$this->cache[] = array('id' => $id, 'item'=> $lazyRef);
		}
	}

	/**
	 * (non-PHPdoc) Get object at current position
	 * @see Iterator::current()
	 */
	public function current() {
		$value = $this->cache[$this->position]['item'];
		if(is_a($value,'NedVisol\Orm\LazyLoadReference')) {			
			$value = $value->loadObject();
			//replace lazy load ref			
			$this->cache[$this->position]['item'] = $value;
		}
		return $value;
	}

	/**
	 * (non-PHPdoc) current "position"
	 * @see Iterator::key()
	 */
	public function key() {
		return $this->position;
	}

	/**
	 * (non-PHPdoc) move to next position
	 * @see Iterator::next()
	 */
	public function next () {
		++$this->position;
	}

	/**
	 * (non-PHPdoc) Reset index to 0
	 * @see Iterator::rewind()
	 */
	public function rewind ( ) {
		$this->position = 0;
	}

	/**
	 * (non-PHPdoc)
	 * @see Iterator::valid()
	 */
	public function valid () {
		return isset($this->cache[$this->position]);
	}


}