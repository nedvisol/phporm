<?php
namespace NedVisol\Orm;

class ModelDefinitionGenerator {
	
	/**
	 * @var NedVisol\Orm\BaesModel Model to be generated
	 */
	private $model;
	
	public function __construct(BaseModel $model) {
		$this->model = $model;
	}
	
	public function generate($additionPropertiesDefinition) {
		//first read from annotation
		 
		
		$defClass = $this->model->name;
		$arrayDefinition = $this->convertToArray($additionPropertiesDefinition);
		$content = " 
<?php
namespace NedVisol\\Orm\\Definition;
class $defClass {
	public static \$definition = $arrayDefinition;
}
		";
		//@TODO find a smart way to find file location
		
		$fileLoc = "";
	}
}