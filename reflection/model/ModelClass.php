<?php
namespace lib\reflection\model;
class ModelClass extends \lib\reflection\base\ClassFileGenerator
{
    var $parentModel;
    function __construct($parentModel)
    {     
        $this->parentModel=$parentModel;
        $cName=$parentModel->objectName->className;
        $layer=$parentModel->objectName->layer;
        $classFile=$parentModel->objectName->getPath($cName.".php");
        $extended=$parentModel->getExtendedModelName();
        parent::__construct($cName,$layer,                         
                            $parentModel->objectName->getParentNamespace(),
                             $classFile,
                             $extended?"\\lib\\model\\ExtendedModel":"\\lib\\model\\BaseModel", 
                            true);
    }
    function mustRebuild()
    {
        $config=$this->parentModel->getConfiguration();
        return $config->mustRebuild("MODEL","Class",$this->filePath);
    }
}
