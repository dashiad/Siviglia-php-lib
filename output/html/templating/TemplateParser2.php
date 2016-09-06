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
define(SIVIGLIA_TEMPLATES_PATH,dirname(__FILE__)."/");

include_once(dirname(__FILE__)."/"."Grammar.class.php");
include_once(dirname(__FILE__)."/Lock.php");
define("WIDGET_EXTENSION","wid");      
function print_backtrace_and_exit($msg)
{
    echo $msg;
    exit();
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

    var $WidgetStack=array();

    function __construct($codeExpr,$curLevel,$parentWidget,$manager)
    {       
        $this->parentWidget=$parentWidget;
        $this->layoutManager=$manager;
        $this->codeExpr=$codeExpr;
        $this->curLevel=$curLevel;
        CWidgetGrammarParser::$widgetStack=array();
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
            "json_value"=>new AltParser(array('~"[^"]*"~',"~[+-]*[0-9]*[\.]*[0-9]*~",new SubParser(json_expr))),

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
                                            //"tag"=>"~[/a-zA-Z0-9_\\-@]+~",
                                            "tag"=>"~[/a-zA-Z0-9_@]+~",
                                            "parameters"=>new MaybeParser(new SeqParser(array("(","expr"=>new SubParser("json_expr"),")"))),
                                            "control"=>new MaybeParser(PHP_CODE_REGEXP),
                                            "]"
                                                )
                                              ),
                "open_tag"=>new SeqParser(array(
                                            "openTag"=>"~\[_:*~",
                                            "tag"=>"~[/a-zA-Z0-9_@]+~",
                                            "widgetAssign"=>new MaybeParser(new SeqParser(array(":","widgetName"=>"~[/a-zA-Z0-9_@\\-]+~"))),
                                            "phpAssign"=>new MaybeParser(new SeqParser(array("=>","varName"=>"~[a-zA-Z_][a-zA-Z0-9_]*~"))),
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
    static $widgetStack=array();

    /**
     * 
     *    EVALUACION DE ESTRUCTURA JSON:
     * 
     **/
    function eval_close_tag($params)
    {
        array_pop(CWidgetGrammarParser::$widgetStack);
        return $params;
    }
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
        $info=array(
            "TYPE"=>"TAG_CONTENT"
        );

        if(isset($params["tag"]["phpAssign"]))
            $info["ASSIGN_TO"]=$params["tag"]["phpAssign"]["varName"];

        return $info;
    }
    
    function common_widget_eval($params,$type)
    {
        $subwidgetPrefix=$params["tag"]["openTag"];
        if($subwidgetPrefix[1]=="@") // Es un plugin
            $type="PLUGIN";
        if($params["tag"]["control"])
        {
            $h=11;
            $p=22;
        }

        $info=array(
            "TYPE"=>$type,
            "NAME"=>$params["tag"]["tag"],
            "TAG"=>$params["tag"],
            "PASSTHRU"=>$subwidgetPrefix[1]=="=",
            "LEVEL"=>strlen($subwidgetPrefix)-2,
            "CONTROL"=>array("start"=>$params["tag"]["control"],"end"=>$params["tag_close"]["control"])
        );

        if(isset($params["tag"]["phpAssign"]))
            $info["ASSIGN_TO"]=$params["tag"]["phpAssign"]["varName"];

        $paramExpr=$params["tag"]["parameters"]["expr"];
        if($paramExpr)
        {
            $info["PARAMS"]=$paramExpr;
        }
        if($params["contents"])
        {
            $c=$params["contents"];
            if(!is_array($params["contents"]))
                $c=array($c);
            $info["CONTENTS"]=$c;
        }
        return $info;
    }

    function eval_subwidget($params)
    {
        return $this->common_widget_eval($params,"SUBWIDGET");
    }
    function eval_widget($params)
    {
        return $this->common_widget_eval($params,"WIDGET");
        //$common->subLoad();
    }
    
    function eval_passthruText($params)
    {

        switch($params["selector"])
        {
        case "text":
            {
                $trimmed=trim($params["result"]);
                // Evitar problemas con jquery
                if($trimmed[0]=="$" && ($trimmed[1]!="(" && $trimmed[1]!="."))
                    return array(
                        "TYPE"=>"PHP",
                        "TEXT"=>"<?php echo ".$trimmed.";?>"
                    );
                else
                {
                    if($trimmed=="")
                        return null;
                    return array("TYPE"=>"HTML","TEXT"=>$params["result"]);
                }
            }break;
        case "php":
            {
                return array("TYPE"=>"PHP","TEXT"=>$params["result"]);
            }break;
        }
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
              $results[]=$params["result"][$k]["result"];
        return $results;

    }

    function eval_subwidgetFile($param)
    {
        $filteredContents=array();
        if(is_array($param["contents"]))
        {
            for($k=0;$k<count($param["contents"]);$k++)
            {
                if($param["contents"][$k])
                    $filteredContents[]=$param["contents"][$k];
            }
        }
        return array("TYPE"=>"SUBWIDGET_FILE","CONTENTS"=>$filteredContents);
    }

    function eval_layoutFile($param)
    {
        $nParams=count($param);
        for($k=0;$k<$nParams;$k++)
        {
            if($param[$k]["result"])
                $results[]=$param[$k]["result"];
        }
        return array("TYPE"=>"LAYOUT_FILE","CONTENTS"=>$results);
    }
}

class LayoutBuilder
{
    var $phpPrefix;
    var $prefixCounter;
    var $currentWidget;
    var $phpState;
    var $layoutManager;
    var $noWidGlobals=array();
    var $dataStack=array();
    function __construct(CLayoutManager $layoutManager)
    {
        $this->layoutManager=$layoutManager;
    }
    function remapVariables($text,$prefix=null)
    {
        $state = $this->phpState;
        if(!$prefix)
            $prefix = $this->phpPrefix;
        $tokens = token_get_all($text);
        $nTokens = count($tokens);
        $newText = "";
        $k = 0;
        $oldPrefix = "";
        $lastWasObject = false;
        $lastWasGlobal = false;

        while ($k < $nTokens) {

            if (is_array($tokens[$k])) {

                if ($oldPrefix) {
                    $prefix = $oldPrefix;
                    $oldPrefix = "";
                }
                if ($lastWasObject) {
                    $newText .= $tokens[$k][1];
                    $lastWasObject = false;
                    $k++;
                    continue;
                }
                if ($lastWasGlobal) {
                    if( $tokens[$k][0] == T_WHITESPACE ) {
                        $k++;
                        $newText .= ' ';
                        continue;
                    }
                    $lastWasGlobal = false;
                    //if ($this->currentWidget)
                    //    $this->currentWidget["GLOBALS"][] = $tokens[$k][1];
                    //else
                        $this->noWidGlobals[]=$tokens[$k][1];

                }
                if ($this->currentWidget) {
                    //if (isset($this->currentWidget["GLOBALS"]) && in_array($tokens[$k][1], $this->currentWidget["GLOBALS"])) {
                    if (isset($this->noWidGlobals) && in_array($tokens[$k][1], $this->noWidGlobals)) {
                        $oldPrefix = $prefix;
                        $prefix = "";
                    }
                }
                else
                    $prefix = "";

                switch ($tokens[$k][0]) {
                    //case T_OPEN_TAG:{$newText.='<?php ';echo "<h1>OPEN</h1>";}break;
                    //case T_OPEN_TAG_WITH_ECHO:{$newText.='<?=';}break;
                    case T_STRING_VARNAME: {
                        $newText .= '$' . $prefix . substr($tokens[$k][1], 1);
                    }
                        break;
                    case T_ENCAPSED_AND_WHITESPACE: {
                        $newText .= '$' . $prefix . substr($tokens[$k][1], 1);
                    }
                        break;
                    case T_FUNCTION: {
                        if ($state["CONTEXT"] == "global")
                            $state["CONTEXT"] = "function";
                        $newText .= $tokens[$k][1];
                    }
                        break;
                    case T_VARIABLE: {
                        // Evitamos sobreescribir variables superglobales
                        if ($tokens[$k][1] == '$GLOBALS' || $tokens[$k][1][1] == '_')
                            $newText .= $tokens[$k][1];
                        else {

                            if ($state["CONTEXT"] == "global")
                                $newText .= '$' . $prefix . substr($tokens[$k][1], 1);
                            else
                                $newText .= $tokens[$k][1];
                        }
                    }
                        break;
                    case T_DOUBLE_COLON:
                    case T_OBJECT_OPERATOR: {
                        $lastWasObject = true;
                        $newText .= $tokens[$k][1];
                    }
                        break;
                    case T_GLOBAL: {
                        $lastWasGlobal = true;
                        $newText .= $tokens[$k][1];
                    }
                        break;
                    default: {
                        $newText .= $tokens[$k][1];
                    }
                }
            } else {
                $lastWasObject = false;
                $newText .= $tokens[$k];
                if ($state["CONTEXT"] != "global") {

                    if ($tokens[$k] == "{")
                        $state["BRACKETS"]++;
                    if ($tokens[$k] == "}") {
                        $state["BRACKETS"]--;
                        if ($state["BRACKETS"] == 0)
                            $state["CONTEXT"] = "global";
                    }
                }

            }

            $k++;
        }
        $this->phpState = $state;
        return $newText;
    }
    var $widgetStack=array();

    function evaluate_tree($contentTree, $currentLevel = 0, $widgetTree = null)
    {
        $layoutManager=$this->layoutManager;
        $nodePreProcessor=null;

        $result = array();
        if ($widgetTree == null)
            $tree = &$contentTree;
        else
            $tree = &$widgetTree;

        for ($k = 0; $k < count($tree); $k++) {
            $c = $tree[$k];
            switch ($c["TYPE"]) {
                case "SUBWIDGET_FILE": {
                    $newTree = $this->evaluate_tree($c["CONTENTS"],  $currentLevel, $widgetTree);
                    $result = array_merge($result, $newTree);
                }
                    break;
                case "PLUGIN":
                {
                    $newTree = $layoutManager->parsePlugin($c, $c["NAME"], $c["CONTENTS"]);
                    $this->layout = $this;
                    $subResult = $this->evaluate_tree($newTree,  $currentLevel, null);
                    if(is_array($subResult))
                        $result = array_merge($result, $subResult);
                    else
                        $nodePreProcessor=$subResult;

                    $layoutManager->addDependency($c["FILE"], "plugin");

                }break;
                case "WIDGET": {
                    if ($widgetTree != null) {
                        $newContents = $this->evaluate_tree($contentTree, $currentLevel, $c["CONTENTS"]);
                        if ($c["LEVEL"] > $currentLevel) {
                            $c["CONTENTS"] = $newContents;
                            $c["LEVEL"] = $c["LEVEL"] - 1;
                            $result[] = $c;
                        } else
                            $result = array_merge($result, $newContents);
                        continue;
                    }
                    $location = "";
                    $widgetContents = $layoutManager->findWidget($c["NAME"], $location);
                    $currentPrefix = $this->prefixCounter;
                    $this->prefixCounter++;
                    $this->phpPrefix = "v" . $currentPrefix . "_";
                    $currentWidget = $this->currentWidget;
                    $c["PHP_PREFIX"]=$this->phpPrefix;
                    $this->currentWidget = $c;
                    $this->widgetStack[]=$c;
                    $this->dataStack[]=$nodePreProcessor;
                    $oParser = new CWidgetGrammarParser("subwidgetFile", $currentLevel + 1, $c, $layoutManager);
                    $layoutManager->currentWidget = array("FILE" => $location, "NAME" => $c["NAME"]);
                    $layout = $oParser->compile($widgetContents, $layoutManager->getLang(), $layoutManager->getTargetProtocol());
                    if (!$layout) {
                        echo "ERROR AL COMPILAR " . $this->name . " FILE :" . $location;
                        exit();
                    }
                    $layoutManager->addDependency($location, "widget");
                    $newHash = null;
                    $subResult = $c["CONTENTS"];
                    $jsonParams=$this->parseParams($c);
                    if($jsonParams)
                    {
                        $result[]=$jsonParams;
                    }

                    $n = $c["LEVEL"];
                    do {
                        $subResult = $this->evaluate_tree($subResult,  $n > $c["LEVEL"] ? null : $c["LEVEL"], $n > $c["LEVEL"] ? null : $layout["CONTENTS"]);
                        $n++;
                    } while (count($subResult) > 1);
                    $this->currentWidget = $currentWidget;
                    array_pop($this->widgetStack);
                    $nodePreProcessor=array_pop($this->dataStack);
                    $this->prefixCounter = $currentPrefix;
                    $this->phpPrefix = "v" . $currentPrefix . "_";
                    $result = array_merge($result, $subResult);
                }
                    break;
                case "SUBWIDGET": {
                    if ($c["LEVEL"] != $currentLevel) {
                        $subTree = $this->evaluate_tree($contentTree,  $currentLevel, $c["CONTENTS"]);
                        if ($c["LEVEL"] > $currentLevel) {
                            $c["CONTENTS"] = $subTree;
                            $c["LEVEL"] = $c["LEVEL"] - 1;
                            $result[] = $c;
                        } else {
                            $result = array_merge($result, $subTree);
                        }
                        continue;
                    }
                    if($nodePreProcessor)
                        $c=$nodePreProcessor->preProcess($c);

                    foreach ($contentTree as $value) {
                        if ($value["TYPE"] == "SUBWIDGET" && $value["NAME"] == $c["NAME"]) {
                            if($c["ASSIGN_TO"] && count($value["CONTENTS"])==1 && $value["CONTENTS"][0]["TYPE"]=="HTML")
                            {
                                $varName=$c["ASSIGN_TO"];
                                if($varName[0]!='$')
                                    $varName='$'.$varName;
                                $result[]=array("TYPE"=>"PHP","TEXT"=>$this->remapVariables("<?php ".$varName."='".addslashes($value["CONTENTS"][0]["TEXT"])."';?>"));
                                continue;
                            }
                            $subResult = $this->evaluate_tree($value["CONTENTS"],  $currentLevel, $c["CONTENTS"]);
                            // Se gestiona el bloque de control.Como el inicio o el fin, por separado, no forman
                            // un php completo parseable, hay que unirlos, parsearlos, y luego separarlos.
                            // Hay que tener en cuenta que la zona de control, aunque esta siendo parseada en el widget actual,
                            // pertenece al widget superior.Es decir, las variables deben ser remapeadas con el prefijo del widget padre.
                            $mapend=null;
                            if($value["CONTROL"] && $value["CONTROL"]["start"])
                            {

                                $start=$value["CONTROL"]["start"];

                                $end=$value["CONTROL"]["end"];
                                $start=trim($start);
                                $end=trim($end);
                                if(substr($start,-1)=="{")
                                {
                                    if(substr($end,1)!="}")
                                        $end="}".$end;
                                }
                                // Se obtiene el prefijo del padre del widget actual.El widget actual está en -1, el padre del actual, en -2
                                $prefix=$this->widgetStack[count($this->widgetStack)-1-$c["LEVEL"]]["PHP_PREFIX"];
                                $fullC=$start."/* --CONTROL-- */".$end;
                                $remapped=$this->remapVariables($fullC,$prefix);
                                $parts=explode("/* --CONTROL-- */",$remapped);
                                $result[]=array("TYPE"=>"PHP","TEXT"=>$parts[0]);
                                $mapend=array("TYPE"=>"PHP","TEXT"=>$parts[1]);
                            }
                            $result = array_merge($result, $subResult);
                            if($mapend!=null)
                                $result[]=$mapend;
                        }
                    }
                }
                    break;
                case "TAG_CONTENT": {
                    $result = array_merge($result, $contentTree);
                }
                    break;
                case "PHP": {
                    $c["TEXT"] = $this->remapVariables($c["TEXT"]);
                    $result[] = $c;
                }
                    break;
                default: {
                    if ($c == null)
                        continue;
                    $result[] = $c;
                }
                    break;
            }

        }
        // Se simplifica el resultado
        $finalResult = array();
        $currentHTML = null;
        for ($k = 0; $k < count($result); $k++) {
            $c = $result[$k];
            if ($c["TYPE"] == "HTML" || $c["TYPE"] == "PHP") {
                if ($currentHTML == null)
                    $currentHTML = $c;
                else
                    $currentHTML["TEXT"] .= $c["TEXT"];
            } else {
                if ($currentHTML != null)
                    $finalResult[] = $currentHTML;
                $currentHTML = null;
                $finalResult[] = $c;
            }
        }
        if ($currentHTML != null) {
            $finalResult[] = $currentHTML;
        }

        return $finalResult;
    }
    function parseParams($element)
    {
        if (!isset($element["PARAMS"]) || $element["PARAMS"] == null)
            return null;
        $localPrefix = $element["PHP_PREFIX"];
        $n=count($this->widgetStack);
        if($n -  2 - $element["LEVEL"] >= 0)
            $parentPrefix = $this->widgetStack[$n - 2 - $element["LEVEL"]]["PHP_PREFIX"];
        else
            $parentPrefix = '';

        $data = json_decode($element["PARAMS"], true);
        $text="";
        foreach($data as $key=>$value) {
            ob_start();
            var_export($value);
            $exported=ob_get_clean();
            $replaced=preg_replace('/[\'"](&{0,1}\$)([^\'"]*)[\'"]/', '\1'.$parentPrefix.'\2',$exported);
            $text.=('$'.$localPrefix.$key."=".$replaced.";\n");
        }
        $code = "<?php " . $text . " ?>";
        return array("TYPE" => "PHP", "TEXT" => $code);
    }

    function recurse_tree($tree)
    {

        for ($k = 0; $k < count($tree); $k++) {
            $c = $tree[$k];
            echo "<li><a>" . $c["TYPE"] . ($c["NAME"] ? ":" . $c["NAME"] : "") . "</a>";
            if (isset($c["CONTENTS"])) {
                echo "<ul>";
                $this->recurse_tree($c["CONTENTS"]);
                echo "</ul>";
            }
            echo "</li>";
        }
    }


    function print_tree($tree)
    {

        echo '<div class="tree">';
        echo "<ul>";
        $this->recurse_tree(array($tree));
        echo "</ul>";
        echo "</div>";
    }

    function parseTree($layout)
    {
        $this->prefixCounter = "0";
        $this->phpState = array("CONTEXT"=>"global");
        $result=$this->evaluate_tree(array($layout), null, null);
        return $result[0];
    }
}
/*
    function parsePlugin()
    {
        return $this->layoutManager->parsePlugin($this,$this->name,$this->contents);
        
        //$pluginTree=new CWidgetGrammarParser("<layoutFile>",$this->level+1,$this,$this->layoutManager);
        //return $pluginTree->compile($this->contents);
    }
    

*/
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
        $parent["FILE"]=$pluginName;
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
        

        $compiledFile=$compiledDir."/".$base;
        //include_once($compiledFile);
        //return;

        $depsFile=$compiledDir."/deps_".$base;

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
                return file_get_contents($compiledFile);

        }
    }
    var $layoutStack=array();

    function processContents($contents)
    {
        $this->layoutStack=array();
        $widgetParser=new CWidgetGrammarParser('layoutFile',1,null,$this);

        $layout=$widgetParser->compile($contents);
        $builder=new LayoutBuilder($this);
        $returned = $builder->parseTree($layout);
        return $returned["TEXT"];
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
    }
    function findWidget($widgetName,& $widgetLocation,$widgetPath=null)
    {
        $widgetFile=$this->findWidgetFile($widgetName,$widgetPath);
        if(!$widgetFile)
        {            
            return SIVIGLIA_TEMPLATES_PATH."/".$widgetPath[0]."/DEFAULT.wid";
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
