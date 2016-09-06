<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace lib\reflection\base;
class ConfigurationFile extends ClassFileGenerator
{     
    var $config;
    var $definition;
    var $path;
    var $namespace;
    var $mustRebuild=null;
    function __construct($path,$namespace,$layer)
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
        return true;
         if(isset($this->mustRebuild[$objType]) && isset($this->mustRebuild[$objType][$name]))
            return $this->mustRebuild[$objType][$name];
        
        if(!$this->exists($objType,$name))
        {            
            $this->definition["codeGeneration"][$objType][$name]=time();
            $this->mustRebuild[$objType][$name]=true;
            return true;                
        }                    
        if($this->definition["codeGeneration"][$objType][$name]=="NO_GENERATE")
        {   
            @unlink($filePath);
            $this->mustRebuild[$objType][$name]=false;
            return false;
        }
        
        if($this->definition["codeGeneration"][$objType][$name]=="NO_MODIFY")
        {
            $this->mustRebuild=!is_file($filePath)?true:false;
            return $this->mustRebuild;
        }
        
        if($this->config->lastBuild==0)
        {
            $this->mustRebuild[$objType][$name]=true;
            return true;
        }
        
        if(!is_file($filePath))
        {
            $this->mustRebuild[$objType][$name]=true;
            return true;
        }
        
        clearstatcache();
        
        $data=stat($filePath);
        
        if(($data["mtime"] - $this->config->lastBuild)>15)
        {                         
            $this->definition["codeGeneration"][$objType][$name]="NO_MODIFY";
            $this->mustRebuild[$objType][$name]=false;
            return false;
        }
        
        $this->definition["codeGeneration"][$objType][$name]=time();
        $this->mustRebuild[$objType][$name]=true;
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
