<?php
namespace lib\reflection\base;

class ConfiguredObject extends SimpleModelDefinition
{
        var $srcModel;
        var $parentModelName;
        var $configSection;

        function __construct($project,$className,$parentModel,$namespace, $filePath, $configSection, $extends = null, $createException = false,$extension=".php")
        {

                $this->parentModel=$parentModel;
                $this->parentModelName=$parentModel->objectName->getNormalizedName();
                $this->configSection=$configSection;
                parent::__construct($project,
                                    $className,
                                    $parentModel->objectName->layer, 
                                    $parentModel->objectName->getNamespace().$namespace, 
                                    $parentModel->objectName->getPath($filePath."/".$className.$extension), 
                                    $extends, $createException);
        }
        function getParentModelName()
        {
                return $this->parentModelName;
        }
        function getParentModel()
        {
                return $this->parentModel;
        }
        function mustRebuild()
        {
            return true;
                $config=$this->parentModel->getConfiguration();
                return $config->mustRebuild($this->configSection,$this->className,$this->filePath);
        }
}

?>
