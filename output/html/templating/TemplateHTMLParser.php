<?php

class CLayoutHTMLParserManager
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
            if($resultClass=="CLayoutHTMLParserManager")
            {
                $h=22;
                $q=11;
            }
            $parserClass=$resultClass."Parser";
            $oNodeParser=new $parserClass($layout->contents[$k]);            
            $result.=$oNodeParser->process($this);
        }        
        //echo htmlentities($result);
        return $result;
    }
}  


abstract class CLayoutElementParser
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
        if(!is_array($contents))
        {
            $h=1;
        }
        $nContents=count($contents);
        for($k=0;$k<$nContents;$k++)
        {
            if($contents[$k]==NULL)
                continue;
            $cName=get_class($contents[$k])."Parser";
            /*if($cName=="CLayoutElementParserParser")
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

class CAssignBlockParser extends CLayoutElementParser{
    function process($referenceNode)
    {
        return $this->element->preparedContents;
    }
}



class CPHPElementParser extends CLayoutElementParser{
    function process($referenceNode)
    {
        return $this->element->preparedContents;
        
    }
}

class CHTMLElementParser extends CLayoutElementParser{
    function process($referenceNode)
    {
        return $this->element->preparedContents;
    }
}

class CDataSourceElementParser extends CLayoutElementParser{
    function process($referenceNode)
    {
        return "<?php echo \$globalPath->getPath('".str_replace(array("{%","%}"),array('',''), $this->element->preparedContents)."',\$globalContext);?>";
    }
}

class CContentTagParser extends CLayoutElementParser{
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

class CWidgetItemParser extends CLayoutElementParser {

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
        CLayoutElementParser::__construct($node);
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
            //if($this->element->parentTag)
            //{
            //    $localPrefix=$this->element->parentTag->parentWidget->getPrefix();
            //}
            //else
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
