<?php
namespace lib\reflection\project;

class ProjectList extends \lib\reflection\PHPMeta\BaseMeta
{
    function __construct()
    {
        parent::__construct("Projects",\lib\project\PathMap::getProjectFilePath());
        $this->definition=$this->get(null);
    }
    function getSpecification()
    {
        return array(
            "ROOT"=>array(
                "LABEL"=>"Proyectos",
                "TYPE"=>"DICTIONARY",
                "VALUETYPE"=>"SINGLEPROJECT",
                "VALUEFIELD"=>"[#KEY#]"
            ),
            "SINGLEPROJECT"=>array(
                "TYPE"=>"SUBDEFINITION",
                "OBJECTTYPE"=>"Project"
            )
        );
    }
    function get($params)
    {
        include_once($this->classPath);
        return \Projects::$definition;
    }
    function set($value)
    {
        $this->definition=$value;
        $this->generateSourceCode();
    }
    function generateSourceCode()
    {
        $cGenerator=new \lib\reflection\base\ClassFileGenerator($this->className,null,$this->classPath,"",null,false);
        $cGenerator->addProperty(array("NAME"=>"definition",
            "ACCESS"=>"static",
            "DEFAULT"=>$this->definition
        ));
        $cGenerator->generate();
    }
} 