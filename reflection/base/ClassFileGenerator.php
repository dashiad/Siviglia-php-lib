<?php

namespace lib\reflection\base;

class ClassFileGenerator extends BaseDefinition
{

    var $properties = array();
    var $methods = array();
    var $parentModel;
    var $className;
    var $tree;
    var $namespace;

    const FILE_HEADER = "/**\n FILENAME:{%filename%}\n  CLASS:{%classname%}\n*\n*\n**/";

    function __construct($project,$className, $layer, $namespace, $filePath, $extends = null, $createException = false)
    {
        $this->project=$project;
        @mkdir(dirname($filePath), 0777, true);        
        $this->extends = $extends;
        $this->createException = $createException;
        parent::__construct($className,$layer,$namespace,$filePath);
    }
    function addMethod($def)
    {
        $this->methods[] = $def;
    }

    function addProperty($def)
    {
        foreach($this->properties as $key=>$value)
        {
            if($value["NAME"]==$def["NAME"])
            {
                $this->properties[$key]=$def;
                return;
            }
                
        }
        $this->properties[] = $def;
    }

    function generate()
    {   
        echo "Guardando fichero ".$this->filePath."<br>";
        @mkdir(dirname($this->filePath),0777,true);
        
        $code.="<?php\n";
        if ($this->namespace)
        {
            // Hay que eliminar la primera '\' del nombre del namespace.
            $code.="namespace " . substr($this->namespace,1) . ";\n";
        }
        if ($this->createException)
        {
            $code.="// Exception Class";
            $code.="\n\nclass " . $this->className . "Exception extends \\lib\\model\\BaseException\n";
            $code.="{\n";
            $code.="}\n\n";
        }
        $code.=str_replace(array("{%filename%}", "{%classname%}"), array($this->filePath,$this->className), ClassFileGenerator::FILE_HEADER);
        $code.="\n\nclass " . $this->className . ($this->extends ? " extends " . $this->extends : "") . "\n";
        $code.="{\n";
        foreach ($this->properties as $number => $def)
        {
            if ($def["COMMENT"])
                $code.="\t/* " . $def["COMMENT"] . " */\n";

            $code.="\t " . ($def["ACCESS"] ? $def["ACCESS"] . " " : "var") . " \$" . $def["NAME"];
            if (is_array($def["DEFAULT"]))
            {
                $code.="=" . $this->dumpArray($def["DEFAULT"], 5) . ";\n";
            }
            else
                $code.=(isset($def["DEFAULT"]) ? "=" . $def["DEFAULT"] : "") . ";\n";
        }

        foreach ($this->methods as $number => $def)
        {
            $code.="\n\n\t/**\n";
            $code.="\t *\n\t * NAME:" . $def["NAME"] . "\n";
            $code.="\t *\n\t * DESCRIPTION:" . $def["COMMENT"] . "\n";
            $code.="\t *\n\t * PARAMS:\n";
            if ($def["PARAMS"])
            {
                foreach ($def["PARAMS"] as $paramName => $paramDef)
                {
                    $code.="\t *\n\t * \$" . $paramName . ":";
                    if ($paramDef["COMMENT"])
                        $code.=str_replace("\n", "\n\t *\t\t ", $paramDef["COMMENT"]);
                    $paramDef.="\n";
                }
            }
            $code.="\t *\n\t * RETURNS:";
            if ($def["RETURNS"])
                $code.=$def["RETURNS"];
            $code.="\n\t */\n\t";

            if ($def["ACCESS"])
            {
                $code.=$def["ACCESS"] . " ";
            }
            $code.="function " . $def["NAME"] . "( ";
            $nParams = 0;
            if ($def["PARAMS"])
            {
                foreach ($def["PARAMS"] as $paramName => $paramDef)
                {
                    if ($nParams > 0)
                    {
                        $code.=", ";
                    }
                    $nParams++;

                    $code.="\$" . $paramName;
                    if ($paramDef["DEFAULT"])
                    {
                        $code.="=" . $paramDef["DEFAULT"];
                    }
                }
            }
            $code.=")\n";
            $code.="\t{\n";
            if ($def["CODE"])
            {
                $code.="\n";
                $code.=implode("\n\t", explode("\n", $def["CODE"]));
                $code.="\n";
            }
            $code.="\t}\n\n";
        }
        $code.="}\n?>";
        file_put_contents($this->filePath, $code);
    }

}
