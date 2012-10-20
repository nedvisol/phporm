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

class ConsoleGenerateController extends AbstractActionController
{
    public function modelIniAction()
    {
        return 'running....';
    }
}
