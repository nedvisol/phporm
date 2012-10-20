<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Di\Config;

use Zend\Di\Di;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use NedVisol\Orm\BaseModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
    	$config = $this->getServiceLocator('Configuration')->get('Configuration');   
    	$di = new Di(null,null,new Config($config['Di']));
    	$basemodel = $di->get('NedVisol\Orm\BaseModel');
    	$ret = $basemodel->adapter->putRow('test', 
    		array('id'=>'row2', 'columns'=> array('cf1.a'=>'234','cf1.b'=>'456'),
    				'checks'=>array('cf1.a'=>'999'))	
    			);

    	echo "****$ret***";
    	
        return new ViewModel();
    }
}
