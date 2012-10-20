<?php
namespace NedVisol\StorageAdapter;

interface IStorage {
	function initialize($param);
	
	/**
	 * @param array $data Data to be updated/inserted
	 * @return TRUE if operation is successful
	 * Set data in this format
	 * ( 'id' => row ID,
	 * 	 'columns => ('column_family.column_name' => value, 'column_family.column_name' => value, ...)
	 *   'checks' => ('column_family.column_name' => value)
	 * )
	 * 
	 * If "checks" exist, this command will use checkAndPut method if available
	 */
	function putRow($tableName, $data);
	
	
	/**
	 * @param array $rowIds Array of rowIDs to be retrieved
	 * @return array Return data in this format
	 * [ ('id' => rowID,
	 *    'columns' => ('column_family.column_name' => value, 'column_family.column_name' => value, ...)
	 *   ),
	 *   ('id' => rowID,
	 *    'columns' => ('column_family.column_name' => value, 'column_family.column_name' => value, ...)
	 *   ),
	 *   ...
	 * ]
	 */
	function getRows($tableName, $rowIds);
	
	/**
	 * Delete data
	 * @param string $tableName
	 * @param string $rowId
	 * @param array $checks format ('column_family.column_name' => value)
	 */
	function deleteRow($tableName, $rowId, $checks);
	
	
	/**
	 * Retrieve rows with IDs begin with $idSearch
	 * @param string $tableName Table name
	 * @param string $idSearch search key
	 * @param int $startIndex default is 0
	 * @param int $maxItems default is -1, unlimited
	 */
	function retrieveIdsBeginsWith($tableName, $idSearch, $startIndex, $maxItems);
	
	/**
	 * @param string $tableName
	 */
	function createTable($tableName);
	
	/**
	 * @param string $cf
	 */
	function addColumnFamily($cf);	
	
}