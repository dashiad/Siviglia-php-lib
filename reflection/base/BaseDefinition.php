<?php
namespace lib\reflection\base;
class BaseDefinition
{
    var $className;
    var $layer;
    var $namespace;
    var $filePath;
    function __construct($className,$layer,$namespace,$filePath)
    {
        
        $this->className=$className;
        $this->layer=$layer;
        $this->namespace=$namespace;
        $this->filePath=$filePath;
    }
    function existsFile()
    {                
        return is_file($this->filePath);
    }
    function getName()
    {
        return $this->className;
    }
    function getLayer()
    {
        return $this->layer;
    }
    function getNamespaced()
    {
        if(!$this->existsFile())
        {            
            return null;
        }
        include_once($this->filePath);
        
        if($this->namespace)
            return $this->namespace.'\\'.$this->className;
        else
            return $this->className;
    }    
    function getInstance()
    {
        $className=$this->getNamespaced();        
        if($className)
            return new $className();
        
        return null;
    }    
    function getFilePath()
    {
        return $this->filePath;
    }
    // Metodos de ayuda a la generacion de codigo
   
 function dumpArray($arr,$initialNestLevel=0)
 {
     return \lib\php\ArrayTools::dumpArray($arr,$initialNestLevel);     
 }

 static function loadFilesFrom($path,$regex=null,$onlyFiles=false,$onlyDirs=false,$noExtensions=false)
 {
     if(!is_dir($path))
         return array();
     $op=opendir($path);
     $matches=array();
     while($curFile=readdir($op))
     {
         if($curFile[0]==".")continue;
         
         $isDir=is_dir($path.DIRECTORY_SEPARATOR.$curFile);
         if($onlyDirs==true && !$isDir)continue;
         if($onlyFiles==true && $isDir)continue;
         if($regex!=null && !preg_match($regex,$curFile))continue;
         if(!$noExtensions)
             $matches[]=$curFile;
         else
         {
             $parts=explode(".",$curFile);
             array_pop($parts);
             $matches[]=implode(".",$parts);
         }
                  
     }
     return $matches;
 }
/* function saveFile($dir,$namespace,$className,$extends=null,$defVar=null)
 {
     if($extends!=null)
         $extends=" extends ".$extends;
     if($defVar==null)
         $defVar="definition";
      $text="<?php\r\n\tnamespace ".$namespace.";\nclass $className".$extends."\n".
                      " {\n".
                      "       static \$".$defVar."=";                                      
             $text.=$this->dumpArray($this->getDefinition(),5);
             $text.=";\n}\n?>";             
             @mkdir($dir,0777,true);
             file_put_contents($dir."/".$className.".php",$text);
 }*/

}
