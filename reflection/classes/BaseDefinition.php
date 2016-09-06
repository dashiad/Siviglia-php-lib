<?php
namespace lib\reflection\classes;
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
    function getClassName()
    {
        if(!$this->existsFile())
            return null;
        include_once($this->filePath);
        if($this->namespace)
            return $this->namespace.'\\'.$this->className;
        else
            return $this->className;
    }    
    function getInstance()
    {
        $className=$this->getClassName();
        if($className)
            return new $className();
        return null;
    }    
    // Metodos de ayuda a la generacion de codigo
   
 function dumpArray($arr,$initialNestLevel=0)
 {
     return \lib\php\ArrayTools::dumpArray($arr,$initialNestLevel);     
 }

 function createType($parentModel,$type,$typeType,$definition)
 {
     
     $typeReflectionFile=LIBPATH."/reflection/classes/".$typeType."/$type".".php";
     $dtype=$type;


     if(!is_file($typeReflectionFile))
     {
         $dtype="BaseType";
         $typeReflectionFile=LIBPATH."/reflection/classes/".$typeType."/BaseType.php";
     }
     $className='\lib\reflection\classes\\'.$typeType.'\\'.$dtype;

     include_once($typeReflectionFile);

     // Los "alias" siempre tienen un parentModel.Los "types", no necesariamente.
     // Por eso, los constructores de alias tienen $parentModel como primer parametro del constructor.
     if($typeType=="aliases")
         $instance=new $className($parentModel,$definition);
     else
         $instance=new $className($definition);

     if($dtype=="BaseType")
     {
         $instance->setTypeName($type);
     }
     return $instance;

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
