<?php
namespace NedVisol\Orm;

class Transaction {

	const STATUS_PENDING = 1001;
	const STATUS_COMMITTED = 1002;
	const STATUS_ABORTED = 1003;

	const STORAGE_TRANSACTIONLOGTABLE = 'log';

	/**
	 * @var string Timestamp representing this transaction
	 */
	private $timestamp;

	/**
	 *
	 * @var array A collection of all IDs updated within this transaction
	 */
	private $transactionLog;

	/**
	 *
	 * @var NedVisol\Orm\IStorage storage adatper
	 */
	private $adapter;

	/**
	 *
	 * @var int Transaction status
	 */
	private $transactionStatus;

	/**
	 *
	 * @var boolean Auto commit flag - true will always commit to live data on save
	 */
	private $autoCommit;

	public function __construct($autoCommit = false) {
		$this->timestamp = $this->timestamp = $this->generateTimestamp();
		$this->transactionLog = array();
		$this->transactionStatus = self::STATUS_PENDING;
		$this->autoCommit = $autoCommit;
	}

	/**
	 * @return string Timestamp of this transaction
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

	/**
	 * @return bool Auto commit flag
	 */
	public function getAutoCommit() {
		return $this->autoCommit;
	}

	public function getTransactionStatus() {
		return $this->transactionStatus;
	}

	/**
	 * Add ID to this transaction (will be rolled back if this transaction is aborted
	 * @param NedVisol\Orm\BaseModel $model
	 * @param array $data Data to be written
	 * @param string $table Table name
	 * @param string $op insert|update|delete
	 */
	public function writeLog($model, $data, $table, $op) {
		if ($this->transactionStatus != self::STATUS_PENDING) {
			throw new InvalidOperationException('Transaction is not in pending status');
		}

		//also add last commited txn, that's us
		$data['columns']['_system_._lastCommitedTS_'] = $this->timestamp;

		$this->adapter = $model->getAdapter();

		$this->transactionLog[$model->id] = array('data'=> $data, 'op'=>$op, 'table'=>$table);
	}

	/**
	 * Abort this transaction, roll back data saved in transaction log.
	 * Checks write timestamp before rolling back
	 */
	public function abort() {
		if ($this->transactionStatus != self::STATUS_PENDING) {
			throw new InvalidOperationException("Unable to abort this transaction; it has already been committed or aborted");
		}
		//pull all live data ... do nothing
		$this->transactionStatus = self::STATUS_ABORTED;
		$this->transactionLog = null;
	}

	/**
	 * Commit updates in the log
	 */
	public function commit() {
		if ($this->transactionStatus != self::STATUS_PENDING) {
			throw new InvalidOperationException("Unable to commit this transaction; it has already been committed or aborted");
		}

		//write everything in the log, make sure write timestamp matches this txn's
		foreach($this->transactionLog as $id => $pending) {
			$data = $pending['data'];
			$op = $pending['op'];
			$table = $pending['table'];
			if ($op == 'delete') {
				$id = $data['id'];
				$this->adapter->deleteRow($table, $id, null);
			} else {
				if ($op != 'insert') {
					//special case - checks the last commited transaction, in case newer txn gets the lock
					//but if it has not commited, this txn should write the data
					$results = $this->adapter->getRows(BaseModel::STORAGE_TABLENAME,array($id));
					if (count($results)==0) {
						throw new OptimisticLockException("Unable to commit transaction, data not found");
					}
					$lastCommitedTxn = $results[0]['columns']['_system_._lastCommitedTS_'];
					if ($this->getTimestamp() >= $lastCommitedTxn) {
						$data['checks'] = array('_system_._lastCommitedTS_' => $lastCommitedTxn);
					} else {
						//a new transaction has already commited, skip writing
						continue;
					}
				}
				try {
					$this->adapter->putRow($table, $data);
				} catch (\Exception $e) {
					echo "***** died trying to write...\n"; var_dump($data);
					throw $e;
				}
			}
		}

		//after commiting, clear out the transaction log - this transction can restart
		$this->timestamp = $this->generateTimestamp();
		$this->transactionLog = array();
	}

	private function generateTimestamp() {
		$txn = sprintf("%20.10f#%5d", microtime(true), mt_rand(0,99999));
		return $txn;
	}

	/**
	 * Return data in transaction log for this transaction
	 * @param string $id row ID
	 * @return array Array of pending data, or null if none in the log
	 */
	public function getPendingData($id) {
		$pending = $this->getArrayData($this->transactionLog, $id, null);
		return $pending == null?null:$pending['data'];
	}

	private function getArrayData($array, $idx, $default) {
		if (isset($array[$idx])) {
			return $array[$idx];
		}
		return $default;
	}
}