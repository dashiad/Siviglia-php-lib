<?php
/*

  Siviglia Framework templating engine

  BSD License

  Copyright (c) 2012, Jose Maria Rodriguez Millan
  All rights reserved.

  Redistribution and use in source and binary forms, with or without modification, are permitted provided that 
  the following conditions are met:

  * Redistributions of source code must retain the above copyright notice, this list of conditions and 
    the following disclaimer.
  * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and 
    the following disclaimer in the documentation and/or other materials provided with the distribution.
  * Neither the name of the <ORGANIZATION> nor the names of its contributors may be used to endorse or 
    promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, 
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
ARE DISCLAIMED. 
IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS 
OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY 
OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


Please, send bugs, feature requests or comments to: 
 
dashiad at hotmail.com 
 
You can find more information about this class at: 
 
http://xphperiments.blogspot.com 

*/
class CLayoutEditorParserManager
{
    var $varMap;
    var $name;
    function __construct()
    {
        $this->varMap=array();
    }
    function getInstanceVar($widgetName)
    {
        $this->varMap[$widgetName]++;
        return $widgetName.$this->varMap[$widgetName];
    }
    function process($layout,$layoutManager)
    {    
        $result="";    
        $staticData=$layoutManager->staticData;
        
        foreach($staticData as $key=>$value)
        {
            foreach($value as $key1=>$value1)
            {
                foreach($value1 as $key2=>$value2)
                {
                    $result.=$value2;
                }
            }
        }        
        $nResults=count($layout->contents);
        for($k=0;$k<$nResults;$k++)
        {
            $resultClass=get_class($layout->contents[$k]);
            $parserClass=$resultClass."Parser";
            $oNodeParser=new $parserClass($layout->contents[$k]);            
            $result.=$oNodeParser->process($this);
        }        
        //echo htmlentities($result);
        return $result;
    }
}  


abstract class CLayoutEditorElementParser
{
    var $element;

    function __construct($node)
    {
       
        $this->element=$node;

        if($node->contents)
            $this->set($node->contents);
    }
    function set($contents)
    {

        $nContents=count($contents);
        for($k=0;$k<$nContents;$k++)
        {
            if($contents[$k]==NULL)
                continue;
            $cName=get_class($contents[$k])."ElementParser";
            /*if($cName=="CLayoutEditorElementParserParser")
            {
                var_dump($this->element);
            }*/
            $this->contents[]=new $cName($contents[$k]);
        }               
    }
    abstract function process($referenceNode);
    function processContents()
    {
        for($k=0;$k<count($this->contents);$k++)
            $result.=$this->contents[$k]->process($this->referenceNode);
        return $result;
    }
}

class CAssignBlockParser extends CLayoutEditorElementParser{
    function process($referenceNode)
    {
        return '';
    }
}



class CPHPElementParser extends CLayoutEditorElementParser{
    function process($referenceNode)
    {
        return '';
        
    }
}

class CHTMLElementParser extends CLayoutEditorElementParser{
    function process($referenceNode)
    {
        return $this->element->preparedContents;
    }
}


class CContentTagParser extends CLayoutEditorElementParser{
    function process($referenceNode)
    {
           if($this->element->params)
            {
            // Si existen parametros, se van a mapear los parametros que nos han pasado, a variables PHP.
            // Para ello, hay que conocer el prefijo que tiene el widget actual, y el prefijo que tenia
            // el widget donde, en su caso, fue declarado el parametro.
            // Hay que tener en cuenta que los parametros son definidos en el widget, y usados desde la plantilla.
            // El arbol que se recibe aqui, por cada tag, tiene 1 primer nivel, que es el tag en el widget.Este
            // nodo del arbol, tiene como contents, a las instancias de ese tag en la plantilla.
            // Por lo tanto, en el primer nivel esta la definicion, y en el segundo nivel, esta el uso de parametros.
            // Y, en los nodos de segundo nivel, en parentWidget esta la plantilla, y en parentTag, esta la instancia
            // del tag en el widget.
              

            if(!$this->element->parentWidget)
            {
                
                $parentPrefix="";
                $localPrefix=$this->element->getPrefix();
            }
            else
            {
                $parentPrefix=$this->element->parentWidget->getPrefix();
                /*if($this->element->parentTag)
                    $localPrefix=$this->element->parentTag->parentWidget->getPrefix();
                else*/
                    $localPrefix=$this->element->getPrefix();
            }

            
            $text="<?php \n";
            foreach($this->element->params as $key=>$value)
            {
              
                if($value[0]=='$')
                {
                    $text.='$'.$localPrefix.$key.'=$'.$parentPrefix.substr($value,1).";\n";
                }
                else
                {
                    if( $value[0]=='&')
                    {
                        $text.='$'.$localPrefix.$key.'=& $'.$parentPrefix.substr($value,$value[1]=='$'?2:1).";\n";
                    }
                    else
                        $text.='$'.$localPrefix.$key."='".str_replace("'","\\'",$value)."';\n";
                }
            }
            $text.="\n?>";
            

        }
          

     
        return $this->processContents();
    }
}

class CWidgetItemParser extends CLayoutEditorElementParser {

    var $definition;
    var $params;
    function __construct($node)
    {
         $this->element=$node;

        if($node->definition)
        {
            $this->definition=& $node->definition;
        }
        if($node->params)
        {
            $this->params=& $node->params;
        }
        CLayoutEditorElementParser::__construct($node);
    }
    function process($referenceNode)
    {
        
        if($this->element->startBlockControl)
            $content=$this->element->startBlockControl->preparedContents;
        $content.=$this->processContents();
        if($this->element->endBlockControl)
            $content.=$this->element->endBlockControl->preparedContents;
        return $content;
    }
}

class SubWidgetFileParser extends CWidgetItemParser {
    function process($referenceNode)
    {
        return $this->processContents();
    }
}

class CWidgetParser extends CWidgetItemParser {
    function __construct($node)
    {
        $this->layoutName=$node->name;
        //$node->contents=& $node->layout->contents;
        CWidgetItemParser::__construct($node);
        
    }
    function process($referenceNode)
    {
        if($this->element->startBlockControl)
            $content=$this->element->startBlockControl->preparedContents;
        $content.=$this->__process($referenceNode);
        if($this->element->endBlockControl)
            $content.=$this->element->endBlockControl->preparedContents;
        return $content;
    }
    function __process($referenceNode)
    {

          if($this->element->params)
        {
            // Si existen parametros, se van a mapear los parametros que nos han pasado, a variables PHP.
            // Para ello, hay que conocer el prefijo que tiene el widget actual, y el prefijo que tenia
            // el widget donde, en su caso, fue declarado el parametro.
            // Hay que tener en cuenta que los parametros son definidos en el widget, y usados desde la plantilla.
            // El arbol que se recibe aqui, por cada tag, tiene 1 primer nivel, que es el tag en el widget.Este
            // nodo del arbol, tiene como contents, a las instancias de ese tag en la plantilla.
            // Por lo tanto, en el primer nivel esta la definicion, y en el segundo nivel, esta el uso de parametros.
            // Y, en los nodos de segundo nivel, en parentWidget esta la plantilla, y en parentTag, esta la instancia
            // del tag en el widget.
              

            if(!$this->element->parentWidget)
            {
                
                $parentPrefix="";
                $localPrefix=$this->element->getPrefix();
            }
            else
            {
                $parentPrefix=$this->element->parentWidget->getPrefix();
                /*if($this->element->parentTag)
                    $localPrefix=$this->element->parentTag->parentWidget->getPrefix();
                else*/
                    $localPrefix=$this->element->getPrefix();
            }

            
            $text="<?php \n";
            foreach($this->element->params as $key=>$value)
            {
              
                if($value[0]=='$')
                {
                    $text.='$'.$localPrefix.$key.'=$'.$parentPrefix.substr($value,1).";\n";
                }
                else
                {
                    if( $value[0]=='&')
                    {
                        $text.='$'.$localPrefix.$key.'=& $'.$parentPrefix.substr($value,$value[1]=='$'?2:1).";\n";
                    }
                    else
                        $text.='$'.$localPrefix.$key."='".str_replace("'","\\'",$value)."';\n";
                }
            }
            $text.="\n?>";
            

        }
          

        return $text.=$this->processContents();
    }
}

class CSubWidgetParser extends CWidgetItemParser {
    function process($referenceNode)
    {        
        
        if($this->element->startBlockControl)
            $content=$this->element->startBlockControl->preparedContents;        
        $content.=$this->__process($referenceNode);
        if($this->element->endBlockControl)
            $content.=$this->element->endBlockControl->preparedContents;
        return $content;
    }
    function __process($referenceNode)
    {
         if($this->element->params)
        {
            // Si existen parametros, se van a mapear los parametros que nos han pasado, a variables PHP.
            // Para ello, hay que conocer el prefijo que tiene el widget actual, y el prefijo que tenia
            // el widget donde, en su caso, fue declarado el parametro.
            // Hay que tener en cuenta que los parametros son definidos en el widget, y usados desde la plantilla.
            // El arbol que se recibe aqui, por cada tag, tiene 1 primer nivel, que es el tag en el widget.Este
            // nodo del arbol, tiene como contents, a las instancias de ese tag en la plantilla.
            // Por lo tanto, en el primer nivel esta la definicion, y en el segundo nivel, esta el uso de parametros.
            // Y, en los nodos de segundo nivel, en parentWidget esta la plantilla, y en parentTag, esta la instancia
            // del tag en el widget.
            if(!$this->element->parentWidget)
            {
                $parentPrefix="";
            }
            else
            {
                if($this->element->parentWidget->parentWidget)                
                //$parentPrefix=$this->element->parentWidget->getPrefix();
                    $parentPrefix=$this->element->parentWidget->parentWidget->getPrefix();
            }
            if($this->element->parentTag)
            
                $localPrefix=$this->element->parentTag->parentWidget->getPrefix();
            else            
                $localPrefix=$this->element->parentWidget->getPrefix();
            $text="<?php \n";
            foreach($this->element->params as $key=>$value)
            {
               if($value[0]=='$')
                {
                    $text.='$'.$localPrefix.$key.'=$'.$parentPrefix.substr($value,1).";\n";
                }
                else
                {
                    if( $value[0]=='&')
                    {
                        $text.='$'.$localPrefix.$key.'=& $'.$parentPrefix.substr($value,$value[1]=='$'?2:1).";\n";
                    }
                    else
                        $text.='$'.$localPrefix.$key."='".str_replace("'","\\'",$value)."';\n";
                }
            }
            $text.="\n?>";
            

        }
        return $text.=$this->processContents();
    }
}
?>
