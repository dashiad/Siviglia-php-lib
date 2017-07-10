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
//define(SIVIGLIA_TEMPLATES_PATH,dirname(__FILE__)."/");

include_once(dirname(__FILE__)."/"."Grammar.class.php");
include_once(dirname(__FILE__)."/Lock.php");
define("WIDGET_EXTENSION","wid");      
function print_backtrace_and_exit($msg)
{
    echo $msg;
    exit();
}
function TemplateParser_debug($msg)
{
    echo '<div style="color:red;padding:3p;color:white;font-family:Calibri">'.$msg.'</div>';
}
abstract class CGrammarParser {
    var $grammar;
    function createGrammar(& $grammarObj)
    {          
        $this->grammar=$grammarObj;
       
        $funcs=array_keys($this->grammar->params["nt"]);
        $prefix=create_function('$a','return "eval_".$a;');
        
        $nonTerminals=array_map($prefix,$funcs);
        for($k=0;$k<count($nonTerminals);$k++)
        {
            $callbackName=$nonTerminals[$k];
            if(!method_exists(get_called_class(),$callbackName))
                $callbackName="defaultCallback";
            $callbacks[substr($nonTerminals[$k],5)]=new FunctionObject($this,$callbackName);
        }
        $this->grammar->setPointCuts($callbacks);
    }
    abstract function initializeGrammar();
    function compile($text)
    {

        $output=$this->grammar->compile($text);
        if($output=="")
        {
            echo "ERROR:".$this->grammar->getError();
            echo "There's an error in the following template:";
            echo "<pre>".htmlentities($text)."</pre>";
            echo "<br><br>Exiting...";
            exit();
        }
        return $output;
    }
    function defaultCallback($data)
    {
        //echo "<div style=\"background-color:red;color:white;margin:1px\">LLAMADO DEFAULTCALLBACK";
        //print_r($data);
        //echo "</div>";
        return $data;
    }

}

define("PHP_CODE_REGEXP","~<\?php?.*\?>~sU");
class CWidgetGrammarParser extends CGrammarParser {


    function __construct($codeExpr,$curLevel,$parentWidget,$manager)
    {       
        $this->parentWidget=$parentWidget;
        $this->layoutManager=$manager;
        $this->codeExpr=$codeExpr;
        $this->curLevel=$curLevel;
        $this->initializeGrammar();
    }
    function initializeGrammar()
    {
    $this->grammar= new Grammar(array(
            'root'=>'code',
            'nt'=>array(
            'passthruText'=>new AltParser(array(
                    // Texto html
                    "text"=>"~([^[<{]++|\[(?![#@=_*])|<(?![?])|\{(?![%]))*~ms",
                    "php"=>PHP_CODE_REGEXP,
            )),
            "json_value"=>new AltParser(array('~"[^"]*"~',"~[+-]*[0-9]*[\.]*[0-9]*~",new SubParser("json_expr"))),

            "json_assignable"=>new AltParser(array(new SubParser("json_value"),new SubParser("json_array"))),
            "json_array"=>new SeqParser(array("[",
                                              new ListParser(new SubParser("json_assignable"),new Symbol(",")),
                                              "]")
                                        ),
            "json_assign_expr"=>new SeqParser(array("tag"=>'~"[a-zA-Z0-9_]+"~',
                                                           ":",
                                                            "data"=>new SubParser("json_assignable"))),
            "json_expr"=>new SeqParser(array("{",new ListParser(new SubParser("json_assign_expr"),new Symbol(",")),"}")),

            "content_tag"=>new SeqParser(array(
                            "[_*",
                           "phpAssign"=>new MaybeParser(new SeqParser(array("->$","varName"=>"~[a-zA-Z_][a-zA-Z0-9_]*~"))),
                            "]")),
             "widget_open_tag"=>new SeqParser(array(
                                            "openTag"=>"~\[[*@]:*~",
                                            "tag"=>"~[/a-zA-Z0-9_]+~",
                                            "parameters"=>new MaybeParser(new SeqParser(array("(","expr"=>new SubParser("json_expr"),")"))),
                                            "control"=>new MaybeParser(PHP_CODE_REGEXP),
                                            "]"
                                                )
                                              ),
                "open_tag"=>new SeqParser(array(
                                            "openTag"=>"~\[_:*~",
                                            "tag"=>"~[/a-zA-Z0-9_]+~",
                                            "phpAssign"=>new MaybeParser(new SeqParser(array("->","varName"=>"~[a-zA-Z_][a-zA-Z0-9_]*~"))),
                                            "parameters"=>new MaybeParser(new SeqParser(array("(","expr"=>new SubParser("json_expr"),")"))),
                                            "control"=>new MaybeParser(PHP_CODE_REGEXP),
                                            "]"
                                                )
                                           ),

              "close_tag"=>new SeqParser(array("[#",
                                                new MaybeParser(new EregSymbol("~[/a-zA-Z0-9_]+~")),
                                               "control"=>new MaybeParser(PHP_CODE_REGEXP),"]")),
                "tag_contents"=>new MaybeParser(new AltParser(array(
                                "simpleText"=>new MultiParser(new AltParser(array(
                                                "passthru"=>new SubParser("passthruText"),
                                                "content"=>new SubParser("content_tag"),
                                                "subwidget"=>new SubParser("subwidget"),
                                                "widget"=>new SubParser("widget")
                                            )
                                        )
                                    )
                            )
                        )
                    ),
              "subwidget"=>new SeqParser(array("tag"=>new SubParser("open_tag"),"contents"=>new SubParser("tag_contents"),"tag_close"=>new SubParser("close_tag"))),
              "widget"=>new SeqParser(array("tag"=>new SubParser("widget_open_tag"),"contents"=>new SubParser("tag_contents"),"tag_close"=>new SubParser("close_tag"))),
              "subwidgetFile"=>new SeqParser(array("contents"=>new SubParser("tag_contents"))),
              "layoutFile"=>new MultiParser(new AltParser(array(new SubParser("passthruText"),new SubParser("widget")))),
              "code"=>new SubParser("subwidgetFile")
            )
            ));
    $this->createGrammar($this->grammar);
    }
    var $treeRoot;

    function eval_php_assign_block($params)
    {   
        
        $nParams=count($params["assigns"]);
        $assigns=array();
        for($k=0;$k<$nParams;$k++)
        {
            $curAssign=$params["assigns"][$k];
            $assigns[]=array("VAR"=>$curAssign["id"],"TAG"=>$curAssign["tag"]["tag"]);
        }
        return new CAssignBlock($assigns,$this->parentWidget);
    }

    //         tag_contents::= dataText=><datasource_text> || 
    //                         simpleText=>( passthru=><passthruText>
    //                                     || subwidget=><subwidget> 
    //                                     || widget=><widget>)* 
    //                        || compound=>(dsstart-><datasource_text> subwidget-><subwidget> dsend-><datasource_text>).
    //
    //         subwidget::=  tag-><open_tag>           assign->[ assign_block-><php_assign_block> ] contents-><tag_contents> tag_close-><close_tag>.                
    //         widget::=     widget-><widget_open_tag> assign->[ assign_block-><php_assign_block> ] contents-><tag_contents> tag_close-><close_tag>.
    //
    //         code::= <widget>.

    /**
     * 
     *    EVALUACION DE ESTRUCTURA JSON:
     * 
     **/
    function eval_json_value($params)
    {

        return $params["result"];
    }
    function eval_json_assignable($params)
    {
        return $params["result"];
    }
    function eval_json_array($params)
    {
        return "[".implode("",$params[1])."]";

    }
    function eval_json_assign_expr($params)
    {
        return $params["tag"].":".$params["data"];
    }
    function eval_json_expr($params)
    {
        return "{".implode("",$params[1])."}";
    }
    /**
     * FIN DE EVALUACION DE JSON
     */

    function eval_content_tag($params)
    {
        $contentTag=new CContentTag($this->parentWidget);
        $contentTag->level=$this->curLevel;
        if(isset($params["tag"]["phpAssign"]))
        {
            $contentTag->assignToVariable($params["tag"]["phpAssign"]["varName"]);
        }
        if(isset($params["parameters"]))
            $contentTag->setParams(json_decode($params["parameters"],true));

        return $contentTag;
    }
    
    function common_widget_eval($params,$className)
    {
        $subwidgetPrefix=$params["tag"]["openTag"];
        $h=$params["tag"];
        
        $len=strlen($subwidgetPrefix);
        $isPlugin=false;
        if($subwidgetPrefix[1]=="@") // Es un plugin
        {
            $isPlugin=true;
        }
        $passThru=false;
        if($subwidgetPrefix[1]=="=")
        {
            $passThru=true; // Es un passthru: un widget que va a recibir como contenido, el mismo contenido recibido por el widget padre.
        }
        $level=strlen($subwidgetPrefix)-2;

        $subwidgetTag=$params["tag"]["tag"];
        $widgetPath=null;
        if($subwidgetTag[0]=="/")
        {
            $parts=explode("/",$subwidgetTag);
            $nParts=count($parts);
            $tagName=$parts[$nParts-1];
            unset($parts[$nParts-1]);
            $widgetPath=implode("/",$parts);
            
        }
        else
            $tagName=$subwidgetTag;

        $subWidget=new $className($tagName,$this->parentWidget,$this->layoutManager);
        $subWidget->setPassThru($passThru);
        $subWidget->setControl($params["tag"]["control"],$params["tag_close"]["control"]);
        
        if($widgetPath)
            $subWidget->setPath($widgetPath);
        if($isPlugin)
        {
            $subWidget->setPlugin(true);
        }
        if(isset($params["tag"]["phpAssign"]))
        {
            $subWidget->assignToVariable($params["tag"]["phpAssign"]["varName"]);
        }

        $paramExpr=$params["tag"]["parameters"]["expr"];
        $param1=$params["tag"]["parameters"];
        if($paramExpr)
        {
            
            $subWidget->setParams(json_decode($paramExpr,true));
        }


        $definitionExpr=$params["tag"]["parameters"]["result"]["tagDefinition"]["expr"];

        if($definitionExpr)
        {
            $subWidget->setDefinition(json_decode($definitionExpr));
        }



        $subWidget->setLevel($level+$this->curLevel);
        $subWidget->setRelativeLevel($level);
        //$assign=$params["assign"];
        if(isset($params["assign"]))
        {

            $assignObj=new CAssignBlock($params["assign"]["assign_block"],$this->parentWidget);
            $subWidget->addAssignBlock($assignObj);
        }
        if(!is_array($params["contents"]))
        {
            if($params["contents"])
                $subWidget->addContent($params["contents"]);        
        }
        else
        {
            $nContents=count($params["contents"]);
            for($k=0;$k<$nContents;$k++)
                $subWidget->addContent($params["contents"][$k]);
        }        
        return $subWidget;
    }

    function eval_subwidget($params)
    {
        return $this->common_widget_eval($params,"CSubWidget");
    }
    function eval_widget($params)
    {
        $common=$this->common_widget_eval($params,"CWidget");
        $common->subLoad();
        return $common;
    }
    
    function eval_passthruText($params)
    {
        $h=11;
        switch($params["selector"])
        {
        case "text":
            {
                $trimmed=trim($params["result"]);
                if($trimmed=="")
                    $el= new CHTMLElement("",$this->parentWidget);
                else {
                    // Evitar problemas con jquery
                    if ($trimmed[0] == "$" && ($trimmed[1] != "(" && $trimmed[1] != "."))
                        $el = new CPHPElement("<?php echo " . $trimmed . ";?>", $this->parentWidget);
                    else
                        $el = new CHTMLElement($params["result"], $this->parentWidget);
                }
            }break;
        case "php":
            {
                $el= new CPHPElement($params["result"],$this->parentWidget);
            }break;
        }
        $el->level=$this->curLevel;
        return $el;
    }



    /**
     *         tag_contents::= dataText-><datasource_text> || simpleText=>( passthru-><passthruText>
                               || subwidget-><subwidget> 
                               || widget-><widget>)*.
     */
    function eval_tag_contents($params)
    {
        
                $results=array();
                $nItems=count($params["result"]);
                for($k=0;$k<$nItems;$k++)
                {
                    $results[]=$params["result"][$k]["result"];
                }                
                return $results;

    }
    function eval_subwidgetFile($param)
    {
        $el= new SubWidgetFile(isset($param["assign"])?$param["assign"]:null,$param["contents"],$this->parentWidget);
        $el->level=$this->curLevel;
        return $el;
    }
    function eval_layoutFile($param)
    {
        $nParams=count($param);
        for($k=0;$k<$nParams;$k++)
        {
            $results[]=$param[$k]["result"];
        }
        $el= new SubWidgetFile(null,$results,$this->parentWidget);
        $el->level=$this->curLevel;
        return $el;
    }
}


abstract class CLayoutElement
{
    var $parentWidget;
    var $name;
    function __construct($parentWidget)
    {
        $this->parentWidget=$parentWidget;
    }
    abstract function getClone();
    function cloneContents($arr)
    {
        if(!is_array($arr))
        {
           $c=array($arr);
        }
        else
            $c=& $arr;
        $result=array();
        $nEl=count($c);
        for($k=0;$k<$nEl;$k++)
        {
            if(!$c[$k])
                continue;
            /*
            if(!is_object($c[$k]))
            {
                debug($this->contents);
            }
            if(!is_object($c[$k]))
            {
                _d($this);
            } 
            */ 
            $result[]=$c[$k]->getClone();
        }
        return $result;
    }
}

class CAssignBlock extends CLayoutElement{
    function __construct($contents,$parentWidget)
    {
        CLayoutElement::__construct($parentWidget);
        $this->preparedContents=$contents;
    }
    function prepare($level,$contents,$parentNode)
    {
        return $this;
    }

    function process()
    {
        return $this->preparedContents;
    }
    function getClone()
    {
        return new CAssignBlock($this->preparedContents,$this->parentWidget);
        
    }
}



class CPHPElement extends CLayoutElement{
    var $preparedContents;
    var $contents;
    function __construct($contents,$parentWidget,$processed=0)
    {        
        CLayoutElement::__construct($parentWidget);
        $this->preparedContents=$contents;
        if(!$processed)
        {
            $currentPrefix="";
            if($parentWidget)
            {
                $currentPrefix=$parentWidget->getPrefix();
            }

            $this->remapVariables($currentPrefix);
            
        }
    }

    function remapVariables($prefix)
    {
        if($this->parentWidget)
            $state=$this->parentWidget->getPHPState();
        else
            $state["CONTEXT"]="global";

        $tokens=token_get_all($this->preparedContents);
        $nTokens=count($tokens);
        $newText="";
        $k=0;   
        $globalVars=array();
        $oldPrefix="";
        $lastWasObject=false;
        $lastWasGlobal=false;
        
        while($k < $nTokens)
        {
    
        if(is_array($tokens[$k]))
        { 
                     
            if($oldPrefix)
            {
                $prefix=$oldPrefix;
                $oldPrefix="";
            }
            if($lastWasObject)
            {
                $newText.=$tokens[$k][1];
                $lastWasObject=false;
                $k++;
                continue;
            }
            if($lastWasGlobal  && $tokens[$k][0]!=T_WHITESPACE )
            {
                $lastWasGlobal=false;
                if($this->parentWidget)
                    $this->parentWidget->addGlobal($tokens[$k][1]);                

            }
            if($this->parentWidget)
            {
                if($this->parentWidget->isGlobal($tokens[$k][1]))
                {                
                    
                    $oldPrefix=$prefix;
                    $prefix="";
                }
            }
            else
            {
                
                
                $prefix="";
            }
            
            switch($tokens[$k][0])
            {
                
                //case T_OPEN_TAG:{$newText.='<?php ';echo "<h1>OPEN</h1>";}break;
                //case T_OPEN_TAG_WITH_ECHO:{$newText.='<?=';}break;
                case T_STRING_VARNAME:{$newText.='$'.$prefix.substr($tokens[$k][1],1);}break;
                case T_ENCAPSED_AND_WHITESPACE:{$newText.='$'.$prefix.substr($tokens[$k][1],1);}break;
            case T_FUNCTION:{
                if($state["CONTEXT"]=="global")
                    $state["CONTEXT"]="function";
                $newText.=$tokens[$k][1];
            }break;
                case T_VARIABLE:{
                        // Evitamos sobreescribir variables superglobales
                        if($tokens[$k][1]=='$GLOBALS' || $tokens[$k][1][1]=='_')
                            $newText.=$tokens[$k][1];
                        else
                        {

                        if($state["CONTEXT"]=="global")
                        $newText.='$'.$prefix.substr($tokens[$k][1],1);
                        else
                            $newText.=$tokens[$k][1];
                        }
                    }break;
                case T_DOUBLE_COLON:
                case T_OBJECT_OPERATOR:{$lastWasObject=true;$newText.=$tokens[$k][1];}break;
                case T_GLOBAL:{$lastWasGlobal=true;$newText.=$tokens[$k][1];
                    }break;
                default:
                {
                    $newText.=$tokens[$k][1];
                }
            }
        }
        else 
        {
            $lastWasObject=false;
            $newText.=$tokens[$k];
            if($state["CONTEXT"]!="global")
            {
            
                if($tokens[$k]=="{")
                    $state["BRACKETS"]++;
                if($tokens[$k]=="}")
                {
                    $state["BRACKETS"]--;
                    if($state["BRACKETS"]==0)
                        $state["CONTEXT"]="global";
                }
            }

        }
        
        $k++;
    }
    
        $this->preparedContents=$newText;
         if($this->parentWidget)
            $state=$this->parentWidget->setPHPState($state);
    }
    function prepare($level,$contents,$parentNode)
    {
        return $this;
    }
    function process()
    {
        return $this->preparedContents;
    }
    function getClone()
    {
                return new CPHPElement($this->preparedContents,$this->parentWidget,1);
    }
}

class CHTMLElement extends CLayoutElement  {
    var $preparedContents;
    var $contents;
    function __construct($contents,$parentWidget)
    {
        CLayoutElement::__construct($parentWidget);
        $this->preparedContents=$contents;                               
    }
    function prepare($level,$value,$currentNode)
    {
        return $this;
    }
    
    function process()
    {
        return $this->preparedContents;
    }
    function getClone()
    {
        return new CHTMLElement($this->preparedContents,$this->parentWidget);
    }
}

class CDataSourceElement extends CLayoutElement {
    function __construct($contents,$parentWidget)
    {
        CLayoutElement::__construct($parentWidget);
        $this->preparedContents=$contents;
    }
    function prepare($level,$contents,$parentNode)
    {
        return $this;
    }
    function getClone()
    {
        //$contentClone=$this->cloneContents($this->contents);
        return new CDataSourceElement($this->preparedContents,$this->parentWidget);
    }


    function process()
    {
        return $this->preparedContents;
    }
}

class CContentTag extends CLayoutElement{

    
    var $contents=array();
    var $processed=false;
    var $assignTo;
    var $params=null;
    function setValue($contents)
    {
        if(!$contents)
            return $this;
        if($contents[0]==$this)
            $h=1;
        if($this->processed==true)
        {
            for($k=0;$k<count($this->contents);$k++)
            {
                if(method_exists($this->contents[$k],"setValue"))
                    $newContents[]=$this->contents[$k]->setValue($contents);
                else
                    $newContents[]=$this->contents[$k];
            }
            $this->contents=$newContents;
        }
        else
        {
            $this->processed=true;
            $nLayout=count($contents);

            if($this->assignTo)
            {
                $currentValue="";
                for($k=0;$k<$nLayout;$k++)
                {
                    if(is_a($contents[$k]->contents[0],"CHTMLElement"))
                        $currentValue.=$contents[$k]->contents[0]->preparedContents;
                }
                $pf=$this->parentWidget->getPrefix();
                $this->contents=array(new CHTMLElement("<?php \$".$pf.$this->assignTo."='".str_replace("'","\\'",str_replace("\n","\\n",trim($currentValue)))."'; ?>",$this));

                return $this;
            }

            for($k=0;$k<$nLayout;$k++)
            {
                    if(is_a($contents[$k],"CWidget"))
                    {

                        if(!$contents[$k]->parsed)
                            $this->contents[]=$contents[$k]->subLoad();
                        else
                            $this->contents[]=$contents[$k];
                    }
                    else
                    {
                        if(is_a($contents[$k],"CSubWidget"))
                        {
                            if($contents[$k]->level==$this->level-1)
                            {
                                //debug("ERROR: SUBTAG DESCONOCIDO:".$contents[$k]->name);
                            }
                        }
                        if($contents[$k]==$this)
                        {
                            $this->processed=false;
                            $this->contents=array();
                        return $this;
                    }
                        $this->contents[]=$contents[$k];
                    }
            }
        }        
        return $this;
    }
     function setParams($params)
        {
            
            $this->params=$params;
        }
    function assignToVariable($varName)
    {
        $this->assignTo=$varName;
    }
       
    function getClone()
    {
        $newContent=new CContentTag($this->parentWidget);
        $newContent->contents=$this->cloneContents($this->contents);
        $newContent->level=$this->level;
        $newContent->processed=$this->processed;
        $newContent->params=$this->params;

        return $newContent;
    }
}

abstract class CWidgetItem extends CLayoutElement {
    var $name;
    var $assignBlock;
    var $contents;
    var $startBlockControl;
    var $endBlockControl;
    var $passThru=false;
    var $assignTo;
    var $processed=false;

        function __construct($name,$parentWidget)
        {
            
            CLayoutElement::__construct($parentWidget);
            
            $this->name=$name;
            
        }
        function setPassThru($passThru)
        {
            $this->passThru=$passThru;
        }
        function setControl($startBlock,$endBlock)
        {
          
        if($startBlock)
            $this->startBlockControl=new CPHPElement($startBlock,$this->parentWidget);
        if($endBlock)
            $this->endBlockControl=new CPHPElement($endBlock,$this->parentWidget);
        }
        function addAssignBlock($block)
        {
            $this->assignBlock=$block;
        }
        function addContent($content)
        {
            $this->contents[]=$content;
        }
        
        function setLevel($level)
        {
            $this->level=$level;
        }
        function assignToVariable($varName)
        {
            $this->assignTo=$varName;
        }
        function setRelativeLevel($relLevel)
        {
            $this->relativeLevel=$relLevel;
        }
        function setParentTag($tag)
        {
            $this->parentTag=$tag;
        }   
        function setParams($params)
        {
            
            $this->params=$params;
        }
        function setDefinition($definition)
        {
            $this->definition=$definition;
        }
        var $parentTag;
        var $level;
        var $relativeLevel;
        var $definition;
        var $params;
        function commonClone($clone)
        {
            $clone->level        = $this->level;
            $clone->parentTag    = $this->parentTag;
            $clone->contents     = $this->cloneContents($this->contents);
            $clone->relativeLevel= $this->relativeLevel;
            $clone->definition   = $this->definition;
            $clone->params       = $this->params;
            $clone->processed    = $this->processed;
            $clone->startBlockControl=$this->startBlockControl;
            $clone->endBlockControl=$this->endBlockControl;
            return $clone;
        }    
         function setValue($val)
        {

            if($this->processed==true)
            {
                for($k=0;$k<count($this->contents);$k++)
                {
                     if(method_exists($this->contents[$k],"setValue"))
                         $result=$this->contents[$k]->setValue($val);                        
                }
                return $this;
            }
            
            $this->processed=true;
            $nVals=count($val);
            if($this->assignTo)
            {
                $currentValue="";
                for($k=0;$k<$nVals;$k++)
                {
                    if($val[$k]->name==$this->name)
                    {
                        $currentValue.=$val[$k]->contents[0]->preparedContents;
                    }


                }
                $pf=$this->parentWidget->getPrefix();
                $this->contents=array(new CHTMLElement("<?php \$".$pf.$this->assignTo."='".str_replace("'","\\'",str_replace("\n","\\n",trim($currentValue)))."'; ?>",$this));

                return $this;
            }

            $newContents=array();
            for($k=0;$k<$nVals;$k++)
            {                
                if($val[$k]->name==$this->name)
                {                    
                    if(!($val[$k]->level==$this->level-1))
                    {
                        for($j=0;$j<count($this->contents);$j++)
                        {
                            if( method_exists($this->contents[$j],"setValue") )
                            {
                                $newContents[]=$this->contents[$j]->setValue($val[$k]->contents);
                            }
                            else
                                $newContents[]=$this->contents[$j];
                        }
                        continue;
                    }
                    $instance=$this->getClone();
                    $instance->setParentTag($this);
                    $instance->params=$val[$k]->params;
                    $instance->processed=true;
                    $instance->definition=$val[$k]->definition; 

                    // Control blocks are copied to the clone, without copying it to ourselves.
                    // Note that, the generated tree for a certain [_A] tag , has a "generic" [_A] root (the $this), with children ($instance)
                    // that are copies of this node, for each [_A] in the template.The startBlock and endBlock are related to those children,
                    // and not to the generic [_A] root.
                    $instance->startBlockControl=$val[$k]->startBlockControl;
                    $instance->endBlockControl=$val[$k]->endBlockControl;
                                                            
                    for($j=0;$j<count($instance->contents);$j++)
                    {                       
                        if(method_exists($instance->contents[$j],"setValue"))
                        {
                             $instance->contents[$j]->setValue($val[$k]->contents);
                        }                                                
                    }                                        
                    $newContents[]=$instance;
                }
               
            }
            
            

            $this->contents=$newContents;
            return $this;   
            
        }         
}

class SubWidgetFile extends CWidgetItem
{
    function __construct($assign,$contents,$parentWidget)
    {
        $this->assignBlock=$assign;
        $this->contents=$contents;
        CWidgetItem::__construct(null,$parentWidget);
    }
    function setName($name)
    {
        $this->name=$name;
    }
    function getClone()
    {
        $obj=new SubWidgetFile($this->assignBlock,null,$this->parentWidget);
        $this->commonClone($obj);
        $obj->assignBlock=$this->assignBlock;
        return $obj;
    }
}

class CWidget extends CWidgetItem{
    var $isPlugin;
    var $pluginProcessed;
    var $parsed=false;
    var $widgetPath;
    var $finalPath;
    var $phpState=null;
    
    function __construct($name,$parentWidget,$layoutManager)
    {
        $this->layoutManager=$layoutManager;
        
        $this->varPrefix=$this->layoutManager->getVarPrefix($name);
        CWidgetItem::__construct($name,$parentWidget);
    }
    function getPHPState()
    {
        if($this->phpState==null)
            return array("CONTEXT"=>"global");
        return $this->phpState;
    }
    function setPHPState($state)
    {
        $this->phpState=$state;
    }
    

    function setPath($path)
    {
        $this->widgetPath=$path;
    }
    function getPrefix()
    {
        return $this->varPrefix;
    }
    function addGlobal($varName)
    {
        $this->globalVars[$varName]=1;
    }
    function isGlobal($varName)
    {
        if(!isset($this->globalVars[$varName]))
            return false;
        return $this->globalVars[$varName]==1;
    }
    function setPlugin($val)
    {
        $this->isPlugin=$val;
    }
    
    function subLoad()
    {
        
        
        if($this->isPlugin)
        {
            if(!$this->pluginProcessed)
            {
                $this->contents=$this->parsePlugin();
                $this->layout=$this;
            }
            $this->pluginProcessed=true;
            
            return $this;
        }
        
        //$this->parentWidget=$parentWidget;
       
        
        //flush();
        $layout=$this->getDefinition($this->name);
        $this->layoutManager->addDependency($this->finalPath,$this->isPlugin?"plugin":"widget");
        if($this->passThru)
            $this->setValue($this->parentWidget->contents);
        else
            $this->setValue($this->contents);
        
       
        return $this;
    }

    function setValue($contents)
    {
            
        $layout=$this->layout;
        $nContents=count($layout->contents);
        $curResult=array();
        $newContents=array();

        for($k=0;$k<$nContents;$k++)
        {
            
                if(method_exists($layout->contents[$k],"setValue"))
                {
                    $layout->contents[$k]->setValue($contents);
                    $result=$layout->contents[$k];
                }
                else
                    $result=$layout->contents[$k];             
                $newContents[]=$result;
        }
        
        $this->contents=$newContents;
        $this->layout->contents=$this->contents;         
        $this->parsed=true;
        return $this;
        
    }
   

    function parsePlugin()
    {
        return $this->layoutManager->parsePlugin($this,$this->name,$this->contents);
        
        //$pluginTree=new CWidgetGrammarParser("<layoutFile>",$this->level+1,$this,$this->layoutManager);
        //return $pluginTree->compile($this->contents);
    }
    
    function getDefinition($name)
    {
        

        $widgetFile=$this->layoutManager->findWidget($this->name,$this->finalPath,$this->widgetPath);

        //$contents=$this->extractNodes($contents);

        // Del contenido, se extraen los scripts y el css
        
        $widgetDef=$oParser=new CWidgetGrammarParser("subwidgetFile",$this->level+1,$this,$this->layoutManager);
        $this->layoutManager->currentWidget=array("FILE"=>$this->finalPath,"NAME"=>$this->name);

        $this->layout=$oParser->compile($widgetFile,$this->layoutManager->getLang(),$this->layoutManager->getTargetProtocol());
        if(!$this->layout)
        {
            echo "ERROR AL COMPILAR ".$this->name;
            exit();
        }
        
        $this->layout->setName($this->name);
        return $this->layout;
    }

    function getClone()
    {
        $obj=new CWidget($this->name,$this->parentWidget,$this->layoutManager);
        $this->commonClone($obj);
        if(!$this->isPlugin)
        {
            $obj->layout=$this->layout->getClone();
        }
        else
            $obj->layout=$obj;
        $obj->isPlugin=$this->isPlugin;
        
        $obj->pluginProcessed=$this->pluginProcessed;
        $obj->varPrefix=$this->varPrefix;
        return $obj;
    }
    function helper_deleteNode($node) { 
        $this->helper_deleteChildren($node); 
        $parent = $node->parentNode; 
        $oldnode = $parent->removeChild($node); 
        } 

    function helper_deleteChildren($node) { 
        while (isset($node->firstChild)) { 
            $this->helper_deleteChildren($node->firstChild); 
            $node->removeChild($node->firstChild); 
            } 
        } 

}

class CSubWidget extends CWidgetItem {
    var $isPlugin;
    function __construct($subwidgetTag,$parentWidget)
    {    
        CWidgetItem::__construct($subwidgetTag,$parentWidget);
    }
    function getClone()
    {
        $obj=new CSubWidget($this->name,$this->parentWidget);
        return $this->commonClone($obj);
    }
}


class CLayoutManager
{
    var $dependencies;
    var $widgetPath;
    var $pluginParams;
    var $lang;
    var $currentWidget;
    var $currentLayout;
    var $initializedPlugins=array();
    var $varCounter=0;
    var $preCompilers;
    var $layoutParser;
    static $defaultWidgetPath;
    
    function __construct($basePath,$targetProtocol,$widgetPath=null,$pluginParams=array(),$lang="es")
    {
        $this->targetProtocol=$targetProtocol;
        if(!$widgetPath)
            $widgetPath=CLayoutManager::$defaultWidgetPath;
        $this->widgetPath=$widgetPath;
        $this->dependencies=array();
        $this->staticData=array();
        $this->pluginParams=$pluginParams;
        $this->lang=$lang;
        $this->basePath=$basePath;
        $this->preCompilers=null;

    }
    static function setDefaultWidgetPath($path)
    {
        CLayoutManager::$defaultWidgetPath=$path;
    }
    static function getDefaultWidgetPath()
    {
        return CLayoutManager::$defaultWidgetPath;
    }
    function getBasePath()
    {
        return $this->basePath;
    }
    function getTargetProtocol()
    {
        return $this->targetProtocol;
    }
    function getLang()
    {
        return $this->lang;
    }
    function addDependency($widgetName,$widgetType="widget",$pluginParam=null)
    {
        if($widgetName=="")
            return;

        $this->dependencies[$widgetName]["TYPE"]=$widgetType;
        if($widgetType=="plugin")
            $this->dependencies[$widgetName]["PARAM"][]=$pluginParam;
    }

    function getPluginParams($pluginName)
    {
        return $this->pluginParams[$pluginName];
    }
    function parsePlugin($parent,$name,$contents)
    {

        $pluginName=dirname(__FILE__).'/'.$this->getTargetProtocol().'/plugins/'.$name.".php";
        include_once($pluginName);        
        $plugin=new $name($parent,$contents,$this);
        if(!in_array($pluginName,$this->initializedPlugins))
        {
            $plugin->initialize();
            $this->initializedPlugins[]=$pluginName;
        }
        return $plugin->parse();        
    }

    function getVarPrefix($widgetName)
    {
        return "v".($this->varCounter++)."_";
        $widgetName=str_replace("/","_",$widgetName);
        $suffix="_".$this->suffixes[$widgetName]["COUNTER"];
        if($suffix=="_0")
            $suffix="";
        $this->suffixes[$widgetName]["COUNTER"]++;
        
        return $widgetName.$suffix;
    }
    function getLayout()
    {
        return $this->currentLayout;
    }
    function renderLayout($layoutDefinition,$layoutParser,$include=false)
    {
        $this->layoutParser=$layoutParser;
        $fileName=isset($layoutDefinition["TEMPLATE"])?$layoutDefinition["TEMPLATE"]:$layoutDefinition["LAYOUT"];
        $this->currentLayout=$fileName;
        $targetDir=$layoutDefinition["TARGET"];

        if($targetDir)
            $compiledDir=$targetDir;
        else
        {
            $compiledDir=dirname($fileName)."/cache/".$this->lang."/".$this->targetProtocol."/";
        }           
        $pathInfo=pathinfo($fileName);
        $base=$pathInfo["basename"];

        // El siguiente codigo  supone que la extension (ej, .wid) solo aparece al final del nombre de fichero.
        if(isset($layoutDefinition["CACHE_SUFFIX"]))
	    {
            $suffix=$layoutDefinition["CACHE_SUFFIX"];
            $base=str_replace(".".$pathInfo["extension"],
                        $suffix[0]=="."?$suffix:".".$suffix,
                        $base
                        );
	    }
        

        $compiledFile=$compiledDir.$base;
        //include_once($compiledFile);
        //return;

        $depsFile=$compiledDir."deps_".$base;

        $this->currentWidget=array("FILE"=>$fileName);

        $mustRebuild=$this->checkCacheFile($fileName,$compiledFile,$depsFile);

	
        //$mustRebuild=false;
        if($mustRebuild)

        {
            @mkdir($compiledDir,0777,true);

            // Se obtiene el lock
            $lock=new Lock($compiledDir,$base);

            $lock->lock();


            // Cuando se obtiene el lock, se comprueba si realmente tenemos que reconstruir.
            $mustRebuild=$this->checkCacheFile($fileName,$compiledFile,$depsFile);
            if(true || $mustRebuild)
            {

                $contents=file_get_contents($fileName);
                if(isset($layoutDefinition["PREFIX"]))
                    $contents=$layoutDefinition["PREFIX"].$contents;

                if(isset($layoutDefinition["SUFFIX"]))
                    $contents=$contents.$layoutDefinition["SUFFIX"];

                $parsed=$this->processPrecompilers($contents);
                // En caso de que los preprocesadores hayan cambiado el fichero, se guarda
                if($parsed!=$contents)
                    file_put_contents($fileName,$parsed);
                $contents=$parsed;
                $oldMemoryLimit=ini_get('memory_limit');
                ini_set('memory_limit', '512M');
                $result=$this->processContents($contents);

                ini_set('memory_limit', $oldMemoryLimit);
                // El texto final se envia a los plugins, para que hagan las
                // ultimas sustituciones.
            
                foreach($this->initializedPlugins as $pluginClass)
                {
                    $cName=basename($pluginClass,".php");
                    $obj=new $cName(null,null,$this);
                    $result=$obj->postParse($result);
                }
                file_put_contents($compiledFile,$result);

                // Se almacenan las dependencias
                if(is_array($this->dependencies))
                {
                    foreach($this->dependencies as $key=>$value)
                    {
                        if($value["TYPE"]=="plugin")
                        {
                            $deps[]="*".$key."[".implode("@@",$value["PARAM"])."]";
                        }
                        else
                            $deps[]=$key;
                    }
                    if($deps)
                        file_put_contents($depsFile,implode(",",$deps));
                }

            }

            $lock->unlock();

        }
        
        if($include)
        {                        
           include($compiledFile);
        }
        else
        {
            if($mustRebuild)
                return $result;
            else
            {
                $contents=file_get_contents($compiledFile);
                return file_get_contents($compiledFile);
            }
        }
    }

    function processContents($contents)
    {
        $widgetParser=new CWidgetGrammarParser('layoutFile',1,null,$this);

        $layout=$widgetParser->compile($contents);
        if($layout=="")
        {
            echo "ERROR DE COMPILACION DE PLANTILLA ".$fileName;
            exit();
        }

        return $this->layoutParser->process($layout,$this);
    }
    function findWidgetFile($widgetName,$widgetPath)
    {
        if(!$widgetPath)
            $widgetPath="";

        reset($this->widgetPath);
        foreach($this->widgetPath as $key=>$value)
        {
            //echo "TRYING ".$value."/".$widgetPath."/".$widgetName.".".WIDGET_EXTENSION."<br>";
            if(defined("USE_WORK_WIDGETS"))
            {
                if(is_file($value."/".$widgetPath."/".$widgetName."_work.".WIDGET_EXTENSION))
                {
                    return $value."/".$widgetPath."/".$widgetName."_work.".WIDGET_EXTENSION;
                }
            }
            if(is_file($value."/".$widgetPath."/".$widgetName.".".WIDGET_EXTENSION))
            {
                //echo "LOADING ".$value."/".$widgetPath."/".$widgetName.".".WIDGET_EXTENSION;
                return $value."/".$widgetPath."/".$widgetName.".".WIDGET_EXTENSION;
            }
        }

        echo "WIDGET NO ENCONTRADO :: $widgetPath / $widgetName";
        var_dump($this->widgetPath);
        die();
        
        return null;
    }
    function findWidget($widgetName,& $widgetLocation,$widgetPath=null)
    {
        $widgetFile=$this->findWidgetFile($widgetName,$widgetPath);
        if(!$widgetFile)
        {
            die("Widget no encontrado : ".$widgetPath);
        }
        $widgetLocation=$widgetFile;
        $contents=file_get_contents($widgetFile);
        $parsed=$this->processPrecompilers($contents);
        if($parsed!=$contents)
            file_put_contents($widgetFile,$parsed);
        return $parsed;
    }

    function isProcessed($widgetName)
    {
        if($this->staticData[$widgetName])
            return true;
        return false;            
    }
    function addStaticData($widgetName,$datatype,$data)
    {
        $this->staticData[$widgetName][$datatype][]=$data;
    }
    function checkCacheFile($fileName,$compiledFile,$depsFile)
    {
        if(!is_file($compiledFile))
        {
            return true;
        }
        else
        {

            clearstatcache();
            $mustRebuild=false;
            $compiledInfo=stat($compiledFile);
            $layoutInfo=stat($fileName);

            if($layoutInfo["mtime"] > $compiledInfo["mtime"])
                return true;

            if($mustRebuild==false)
            {

                if(!is_file($depsFile))
                    return true;
                else
                {
                    $widgetDeps=explode(",",file_get_contents($depsFile));

                    foreach($widgetDeps as $key=>$value)
                    {
                        if($value[0]=="*")
                            continue;

                        $widgetInfo=@stat($value);
                        if(!$widgetInfo || $widgetInfo["mtime"]>$compiledInfo["mtime"])
                        {
                            return true;
                            break;
                        }
                    }
                }
            }
            return false;
        }
    }

    function processPrecompilers($content)
    {
        if($this->preCompilers===null)
        {
            $this->preCompilers=array();
            $srcDir=dirname(__FILE__).'/'.$this->getTargetProtocol().'/preCompilers';
            $d=opendir($srcDir);
            if($d)
            {
                while($f=readdir($d))
                {
                    $fullName=$srcDir.DIRECTORY_SEPARATOR.$f;
                    if(is_file($fullName))
                    {
                        include_once($fullName);
                        $className=str_replace(".php","",$f);
                        $ins=new $className($this,$this->getTargetProtocol());
                        $this->preCompilers[]=$ins;
                    }
                }
            }
        }
            for($k=0;$k<count($this->preCompilers);$k++)
            {
                $content=$this->preCompilers[$k]->parse($content);
            }
            return $content;

    }
}

// Funcion global de ayuda al debugging
function TemplateDebug()
{
    $debugging=true;
}
