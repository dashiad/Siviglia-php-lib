<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace lib\reflection\base;
class ModelConfiguration extends ConfigurationFile
{
    var $objName;    
    var $config;
    var $definition;
    var $savedObjects;
    var $saved=0;
    var $mustRebuild=null;
    

    function __construct($parentModel)
    {
        $this->parentModel=$parentModel;
        
        $className=$parentModel->objectName->getNormalizedName();
        
        $layer=$parentModel->objectName->layer;
        $path=$parentModel->objectName->getPath("config.php");
        $namespace=$parentModel->objectName->getNamespaced();
        ConfigurationFile::__construct($path,$namespace,$layer);       
    }
       
    function generateDefault()
    {                
        $this->addProperty(array("NAME"=>"definition",                                      
                                      "DEFAULT"=>$definition));
        $this->addProperty(array("NAME"=>"lastBuild",
                                 "DEFAULT"=>"0"));
        $this->generate();
    }    
    
    function exists($objType,$name)
    {
        return isset($this->definition["codeGeneration"][$objType][$name]);
    }
    function mustRebuild($objType,$name,$filePath)
    {
        if(isset($this->mustRebuild[$objType]) && isset($this->mustRebuild[$objType][$name]))
            return $this->mustRebuild[$objType][$name];
        
        

        $this->savedObjects[$objType][$name]=1;
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
            $this->mustRebuild[$objType][$name]=false;
            return false;
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
        global $alreadySaved;

        if($alreadySaved[$this->path])
        {
            echo "DOBLE SAVE";
            exit();
        }
        $alreadySaved[$this->path]=1;        
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
