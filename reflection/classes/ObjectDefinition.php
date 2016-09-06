<?php
namespace lib\reflection\classes;
class ObjectDefinition
{
        var $layer;
        var $className;
		
        function __construct($definition)
        {
            if(is_object($definition))
                debug_trace();
                if($definition[0]!="\\")
                {
                    $parts=explode("\\",$definition);
                    $this->layer=$parts[0];
                        $this->className=$definition;
                }
                else
                {
                        $parts=explode("\\",$definition);
                        $this->layer=$parts[1];
						
                        $this->className=$parts[count($parts)-1];
                }
        }
        function getDestinationFile($extraPath=null)
        {
			
                return PROJECTPATH."/".$this->layer."/objects/".$this->className.($extraPath?"/".$extraPath:""); 
         }
        function getNamespaced($tree=null)
        {
			
             return '\\'.$this->layer.'\\'.$this->className;
        }
        function getNamespace()
        {
            return $this->layer."\\".$this->className;
        }
        function getPath()
        {
            return PROJECTPATH."/".$this->layer."/objects/".$this->className."/";
        }
        function __toString()
        {
            return $this->className;
        }
}

?>
