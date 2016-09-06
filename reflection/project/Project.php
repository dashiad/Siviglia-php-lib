<?php
namespace lib\reflection\project;

class Project extends \lib\reflection\PHPMeta\BaseMeta
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
            "LABEL"=>"Proyecto",
            "TYPE"=>"DICTIONARY",
            "VALUETYPE"=>"SINGLEPROJECT"
            ),
            "SINGLEPROJECT"=>array(
                "TYPE"=>"CONTAINER",
                "FIELDS"=>array(
                    "base"=>array(
                        "TYPE"=>"STRING",
                        "LABEL"=>"Sitio base"
                    ),
                    "type"=>array(
                        "TYPE"=>"SELECTOR",
                        "OPTIONS"=>array("Website"=>"web","Estatico"=>"static"),
                        "DEFAULT"=>"web",
                        "LABEL"=>"Tipo"
                    ),
                    "sites"=>array(
                        "TYPE"=>"DICTIONARY",
                        "VALUETYPE"=>"SINGLESITE"
                    )
                )
            ),
            "SINGLESITE"=>array(
                "TYPE"=>"CONTAINER",
                "FIELDS"=>array(
                    "CANONICAL_URL"=>array(
                        "TYPE"=>""
                    ),
                    "DOMAINS"=>array(
                        "TYPE"=>"ARRAY",
                        "LABEL"=>"Dominios (regexp)"
                    )
                )
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