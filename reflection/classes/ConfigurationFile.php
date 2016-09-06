<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace lib\reflection\classes;
class ConfigurationFile extends \lib\reflection\classes\ClassFileGenerator
{     
    var $config;
    var $definition;
    var $path;
    var $namespace;
    function __construct($path,$namespace)
    {
        $this->path=$path;
        $this->namespace=$namespace;        
        ClassFileGenerator::__construct("Config", $layer, $namespace, $path);                
        if(!$this->existsFile())
        {
            
            $this->generateDefault();
            $this->config=$this->getInstance();
            $this->definition=$this->config->definition;
        }
        else
        {
            $instance=$this->getInstance();
            $this->definition=$instance->definition;
            $this->config=$instance;
        }
    }
       
    function generateDefault()
    {        
        $classes=opendir(PROJECTPATH."/lib/reflection/classes");
        while($curClass=readdir($classes))
        {
            if(is_dir($curClass))continue;
            $fullClassName='\lib\reflection\classes\\'.$curClass;
            if(method_exists($fullClassName, "initializeModelConfig"))
            {
                $fullClassName::initializeModelConfig($definition);
            }            
        }
        $this->addProperty(array("NAME"=>"definition",                                      
                                      "DEFAULT"=>$definition
                                      ));
        $this->addProperty(array("NAME"=>"lastBuild",
                                 "DEFAULT"=>"0"
            ));
        $this->generate();
    }    
    
    function exists($objType,$name)
    {
        return isset($this->definition["codeGeneration"][$objType][$name]);
    }
    function mustRebuild($objType,$name,$filePath)
    {

        if(!$this->exists($objType,$name))
        {
            $this->definition["codeGeneration"][$objType][$name]=time();
            return true;                
        }
        
            
        if($this->definition["codeGeneration"][$objType][$name]=="NO_GENERATE")
        {        
            @unlink($filePath);
            return false;
        }
        
        if($this->definition["codeGeneration"][$objType][$name]=="NO_MODIFY")
        {
            if(!is_file($filePath))
                return true;
            return false;
        }
        
        if($this->config->lastBuild==0)
            return true;
        
        if(!is_file($filePath))
            return true;
        
        clearstatcache();
        
        $data=stat($filePath);
        
        if(abs($data["ctime"] - $this->config->lastBuild)>15)
        {            
             

             
            $this->definition["codeGeneration"][$objType][$name]="NO_MODIFY";
            return false;
        }
        
        $this->definition["codeGeneration"][$objType][$name]=time();
        return true;        
    } 
    function mustRegenerateStorage()
    {
        
        if($this->definition["storage"])
        {
            
            if(array_key_exists("regenerate",$this->definition["storage"]))
            {                
                return $this->definition["storage"]["regenerate"];
            }
        }
        $this->definition["storage"]["regenerate"]=true;
        return true;
    }
    function save()
    {
         $this->addProperty(array("NAME"=>"definition",                                      
                                      "DEFAULT"=>$this->definition
                                      ));
        $this->addProperty(array("NAME"=>"lastBuild",
                                 "DEFAULT"=>time()
            ));
        $this->generate();        
    }
}
?>
