<?php
set_include_path(get_include_path(). ':/Shared/ZF2rc03/library');
date_default_timezone_set('America/Los_Angeles');

require_once 'Zend/Loader/StandardAutoloader.php';
$loader = new Zend\Loader\StandardAutoloader(array('autoregister_zf' => true));

$loader->registerNamespace('NedVisol', __DIR__ . '/../vendor/NedVisol/library/NedVisol');
$loader->registerNamespace('NedVisol\Orm\Definition', __DIR__ . '/../vendor/NedVisol/generated/NedVisol/Orm/Definition');

// Register with spl_autoload:
$loader->register();

define('HBASE_THRIFT_HOST','hbase-thrift');
define('HBASE_THRIFT_PORT','9090');
