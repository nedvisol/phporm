<?php
namespace NedVisol\Orm;

class InvalidOperationException extends \Exception {
	const SECURITY_NONOBJ = 10000;
	const SECURITY_NOSECURE = 10001;
	const SECURITY_NOMETHOD = 10002;
	const SECURITY_NOMETHODSEC = 1003;
	
	public function __construct($message = null, $code = null, $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}