<?php
namespace NedVisol\StorageAdapter\Hbase;

//thrift2 client
$GLOBALS['THRIFT_ROOT'] = './vendor/thrift';
include_once('vendor/hbase_client/THBaseService.php');
include_once('vendor/thrift/transport/TSocket.php');
include_once('vendor/thrift/transport/TBufferedTransport.php');
include_once('vendor/thrift/protocol/TBinaryProtocol.php');


use NedVisol\StorageAdapter\IStorage;

class HbaseClient implements IStorage {

	const BATCH_SIZE = 50;

	private $host;

	private $port;

	private $hbaseThriftClient;

	public function __construct($param) {
		$this->initialize($param);
	}

	/* (non-PHPdoc)
	 * @see NedVisol\StorageAdapter.IStorage::initialize()
	*/
	public function initialize($param) {
		$this->host = $param['host'];
		$this->port = $param['port'];

		$socket = new \TSocket($this->host, $this->port);
		$socket->setSendTimeout(2000);
		$socket->setRecvTimeout(5000);
		$transport = new \TBufferedTransport($socket, 512, 512);
		$protocol = new \TBinaryProtocol($transport);

		$this->hbaseThriftClient = new \THBaseServiceClient($protocol);

		$transport->open();
	}

	public function putRow($tableName, $data) {
		$put = new \TPut();
		$put->row = $data['id'];
		$put->columnValues = array ();
		$columns = $data['columns'];
		foreach($columns as $name => $value) {
			$colNames = explode('.', $name);
			if (count($colNames) < 2) {
				throw new Exception("Invalid column name [$name]");
			}
			$cf = $colNames[0];
			$cq = $colNames[1];
			$colValue = new \TColumnValue();
			$colValue->family = $cf;
			$colValue->qualifier = $cq;
			$colValue->value = $value;
			$put->columnValues[] = $colValue;
		}
		if (isset($data['checks'])) {
			$col = array_keys($data['checks']);
			$colNames = explode('.', $col[0]);
			if (count($colNames) < 2) {
				throw new Exception("Invalid column name [$name]");
			}
			$cf = $colNames[0];
			$cq = $colNames[1];
			$value = $data['checks'][$col[0]];
			return $this->hbaseThriftClient->checkAndPut($tableName, $put->row, $cf, $cq, $value, $put);
		}

		$this->hbaseThriftClient->put($tableName, $put);
		return true; //always "success"
	}

	public function getRows($tableName, $rowIds) {
		$gets = array();
		foreach($rowIds as $rowId) {
			$get = new \TGet();
			$get->row = $rowId;
			$gets[] = $get;
		}

		$results = $this->hbaseThriftClient->getMultiple($tableName, $gets);

		$rows = array();

		foreach($results as $result) {
			$rowId = $result->row;
			if ($rowId == null) {
				continue;
			}
				
			$rows[] = $this->convertTResultToRow($result);
		}
		return $rows;
	}

	private function convertTResultToRow($result) {
		$rowId = $result->row;
		$resultColumns= $result->columnValues;
		$cols = array();
		foreach($resultColumns as $resultColumn) {
			$cf = $resultColumn->family;
			$cq = $resultColumn->qualifier;
			$val = $resultColumn->value;
			$cols["$cf.$cq"] = $val;
		}

		return array('id'=>$rowId, 'columns'=>$cols);
	}

	/**
	 * (non-PHPdoc)
	 * @see NedVisol\StorageAdapter.IStorage::retrieveIdsBeginsWith()
	 */
	public function retrieveIdsBeginsWith($tableName, $idSearch, $startIndex = 0, $maxItems = -1) {
		$scanner = new \TScan();
		$scanner->startRow = $idSearch;

		$scannerId = $this->hbaseThriftClient->openScanner($tableName, $scanner);

		//just to be safe we will retrieve small amount at a time
		$retrievedItemCount = 0;
		$maxItems = $maxItems == -1? 2147483647:$maxItems; //set to max int if -1
		$rows = array();
		while ($retrievedItemCount < $maxItems) {
			$results = $this->hbaseThriftClient->getScannerRows($scannerId, self::BATCH_SIZE);
			if (count($results)==0) {
				break;
			}
			foreach($results as $result) {
				$rowId = $result->row;
				if ($rowId == null) {
					break;
				}
				$pos = strpos($rowId, $idSearch);
				if ($pos === FALSE) {
					$maxItems = -1; //break the outer loop
					break;
				}
				if ($pos !== 0) {
					$maxItems = -1; //break the outer loop
					break;
				}
				$rows[] = $this->convertTResultToRow($result);
				$retrievedItemCount++;
				if ($retrievedItemCount >= $maxItems) {
					break;
				}
			}
		}
		$this->hbaseThriftClient->closeScanner($scannerId);
		return $rows;
	}

	
	/**
	 * (non-PHPdoc)
	 * @see NedVisol\StorageAdapter.IStorage::deleteRow()
	 */
	public function deleteRow($tableName, $rowId, $checks = NULL) {
		$delete = new \TDelete();
		$delete->row = $rowId;
		if ($checks == null) {
			$this->hbaseThriftClient->deleteSingle($tableName, $delete);
			return true;
		} else {
			$col = array_keys($checks);
			$colNames = explode('.', $col[0]);
			if (count($colNames) < 2) {
				throw new Exception("Invalid column name [$name]");
			}
			$cf = $colNames[0];
			$cq = $colNames[1];
			$value = $checks[$col[0]];
			return $this->hbaseThriftClient->checkAndDelete($tableName, $rowId, $cf, $cq, $value, $delete);
		}
		
	}
	

	public function createTable($tableName) {
		throw new \Exception('Not supported');

	}

	public function addColumnFamily($cf) {
		throw new \Exception('Not supported');
	}


}