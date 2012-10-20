<?php
namespace NedVisol\Orm;

class OptimisticLockException extends \Exception {
	const READ_LOCK = 1001;
	const WRITE_LOCK = 1002;
	const UNABLE_LOCK = 2001;
	const INVALID_RECORDVERSION = 2002;
	
	public function __construct($message = null, $code = null, $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}