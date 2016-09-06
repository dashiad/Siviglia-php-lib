<?php
namespace lib\reflection\classes;
class FormDefinition extends ClassFileGenerator
{
    function __construct($parentModel,$definition,$action)
    {
        $this->parentModel=$parentModel;
        $this->definition=$definition;
        $this->action=$action;
        ClassFileGenerator::__construct($definition["NAME"],
                                        $parentModel->objectName->layer,
                                        $parentModel->objectName->getNamespace()."\\html\\forms",
                                        $parentModel->objectName->getPath()."/html/forms/".$definition["NAME"].".php",
                                        "\\lib\\output\\html\\Form");
    }

    static function createFromAction($modelDef,$objName,$name,$actionDef)
    {
        $def=array();
        $def["NAME"]=$name;
        $def["OBJECT"]=$objName;
        $def["ACTION"]=array("OBJECT"=>$modelDef->objectName->getNamespaced("objects"),
                             "ACTION"=>$name);
        $def["FIELDS"]=array();
        $def["ROLE"]=$actionDef->getRole();
        $def["REDIRECT"]=array("ON_SUCCESS"=>"",
                               "ON_ERROR"=>"");
        $def["INPUTS"]=array();
        $indexes=$actionDef->getIndexes();
        if( $indexes )
        {
            $def["INDEXFIELD"]=$indexes;
        }
        
        $fields=$actionDef->getFields();
        if($fields)
        {
            foreach($fields as $key=>$value)
            {                
                $def["FIELDS"][$key]=$value;
            // Se obtiene una instancia del tipo.
                
            $type=\lib\model\types\TypeFactory::getType($value);
              $fullclass=get_class($type);
              $parts=explode('\\',$fullclass);
              $className=$parts[count($parts)-1];
            // se obtiene el input asociado por defecto.            
            $def["INPUTS"][$key]=array("TYPE"=>"/types/inputs/".$className,
                                       "PARAMS"=>array());
            }
        }
        else
        {
            $def["NOFORM"]=true;
        }
        return new FormDefinition($modelDef,$def,$actionDef);       
    }
    static function getFormClass($layer,$objName,$name)
    {
        return '\\'.$layer.'\\'.$objName.'\html\forms\\'.$name;
    }
    function getFormPath()
    {
        return dirname($this->filePath);
    }
    function getWidgetPath()
    {
        return "/".$this->parentModel->objectName->layer."/objects/".$this->parentModel->objectName->className."/html/forms/".$this->action->name;
    }
    function getDefinition()
    {
        if( !$this->definition["INDEXFIELD"] )
        {
            $this->definition["INDEXFIELD"]=array();
        }
        return $this->definition;
    }
    function saveModelMethods($generator)
    {
        $def=$this->getDefinition();
        
           /* $generator->addMethod(array(
                "NAME"=>"__construct",
                "COMMENT"=>" Constructor for ".$this->name,
                "CODE"=>"\t\t\t\\lib\\action\\Action::__construct(".$this->name."::\$definition);\n"
            ));*/
        
            $this->addMethod(array(
                    "NAME"=>"validate",
                    "COMMENT"=>" Callback for validation of form :".$this->name,
                    "PARAMS"=>array(
                        "params"=>array(
                            "COMMENT"=>" Parameters received,as a BaseTypedObject.\nIts fields are:\n".($def["INDEXES"]?"keys: ".implode(",",array_keys($def["INDEXES"]))."\n":"").
                                ($def["FIELDS"]?"fields: ".implode(",",array_keys($def["FIELDS"])):"")
                                        
                            ),
                        "actionResult"=>array("COMMENT"=>"\\lib\\action\\ActionResult instance.Errors found while validating this action must be notified to this object"
                            ),
                        "user"=>array(
                            "COMMENT"=>" User executing this request"
                            )

                        ),
                    "CODE"=>"\n/"."* Insert the validation code here *"."/\n\n\t\treturn \$actionResult->isOk();\n"

                ));
        
            $this->addMethod(array(
                    "NAME"=>"onSuccess",
                    "COMMENT"=>" Callback executed when this form had success.".$this->name,
                    "PARAMS"=>array(
                        "actionResult"=>array(
                            "COMMENT"=>" Action Result object"
                            )
                        ),
                    "CODE"=>"\n/"."* Insert callback code here *"."/\n\nreturn true;\n"
                ));
            $this->addMethod(array(
                    "NAME"=>"onError",
                    "COMMENT"=>" Callback executed when this action had an error".$this->name,
                    "PARAMS"=>array(
                        
                        "actionResult"=>array("COMMENT"=>"\\lib\\action\\ActionResult instance.Errors found while validating this action must be notified to this object"
                            )
                        ),
                    "CODE"=>"\n/"."* Insert callback code here *"."/\n\nreturn true;\n"
                ));        
    }

    function saveDefinition()
    {
        $definition=$this->getDefinition();
        if(!$this->parentModel->config->mustRebuild("htmlforms",$definition["NAME"],$this->filePath))
            return;
            
        $this->addProperty(array("NAME"=>"definition",                                  
                                      "DEFAULT"=>$this->getDefinition()
                                      ));
        $this->saveModelMethods($generator);
        $this->generate();
        if(!$this->parentModel->config->mustRebuild("htmlformWidget",$definition["NAME"],str_replace(".php",".wid",$this->filePath)))
                return;
        $this->saveCode();
    }


    function hasForm()
    {
        return !$this->definition["NOFORM"];
    }
    function saveCode()
    {
        if( $this->definition["NOFORM"] )
            return;
        
        if(!$this->parentModel->config->mustRebuild("htmlformLayouts",$this->definition["NAME"],$this->filePath))
            return;        
        
        $phpCode = <<<'TEMPLATE'
            global $SERIALIZERS;
            $formKeys={%formKeys%};
            $serializer=\lib\storage\StorageFactory::getSerializerByName('{%layer%}');
            $serializer->useDataSpace($SERIALIZERS["{%layer%}"]["ADDRESS"]["database"]["NAME"]);
TEMPLATE;
        $phpCode="<"."?php\n".$phpCode."\n?>\n\n";

        $formCode=<<<'TEMPLATE'
                [*/FORMS/form({"object":"{%objectName%}","layer":"{%layer%}","name":"{%actionName%}"})]        
                [_TITLE]{%title%}[#]
                [_DESCRIPTION]Form Description[#]
                [_MODEL({"keys":"$formKeys","serializer":"$serializer"})][#]                           
                [_FORMGROUP]
                        [_TITLE]Form Group Title[#]
                        [_DESCRIPTION]Form Group Description[#]
                        [_FORMERRORS]
{%formerrors%}
                        [#]
                        [_FIELDS]
{%inputs%}         
                        [#]
                [#]
                [_BUTTONS]
                        [*/INPUTS/Submit][_LABEL][@L]Aceptar[#][#][#]
                [#]        
          [#]
TEMPLATE;
             

        $this->fillActionErrors($actionErrors);
        
        if(is_array($actionErrors))
        {
            
            foreach($actionErrors as $key2=>$value2)
            {
              $formErrors.="\t\t\t\t[_ERROR({\"type\":\"".$key2."\",\"code\":\"".$value2."\"})][@L]".$this->parentModel->objectName->className."_".$this->action->name."_".$key2."[#][#]\n";
            }
            
        }
      // [*/types/inputs/Relation1x1Input({"name":"a3","labelField":"c2","valueField":"c1"})][#]
      
      $actionRole=$this->action->getRole();
      switch($actionRole)
      {
      case "CREATE":
          {
              $keys=null;
          }break;
      case "EDIT":
      case "AddRelation":
      case "SetRelation":
          {
              $keys=$this->action->getIndexes();              
          }break;
      default:
          {
              printWarning("No es posible generar codigo para formularios basados en acciones de tipo ".$actionRole);
              return;
          }
      }
      if($keys)
      {
          foreach($keys as $kkey=>$kvalue)
              $keyCads[]='"'.$kkey.'"=>Registry::$registry["params"]["'.$kkey.'"]';
          $keyExpr="array(".implode(",",$keyCads).");";
      }
      else
          $keyExpr="null;";

      foreach($this->definition["FIELDS"] as $key=>$value)
      {
          $inputStr="\n\n\t\t\t\t[*/FORMS/inputContainer({\"name\":\"".$key."\"})]\n\t\t\t\t\t[_LABEL]".($value["LABEL"]?$value["LABEL"]:$key)."[#]\n";
          $inputStr.="\t\t\t\t\t[_HELP]".($value["HELP"]?$value["HELP"]:"**Insert Field Help**[#]\n");
          if($value["REQUIRED"])
              $inpuStr.="\t\t\t\t\t[_REQUIRED][#]\n";
          $input=$this->definition["INPUTS"][$key]["TYPE"];


          $curType=\lib\reflection\model\types\BaseType($value);

          if(!$input)
          {
              $input=$curType->getDefaultInputName();

          }
          // Se obtienen las cadenas de errores por defecto para este campo.

          $errors=array();
          $errors=$curType->getTypeErrors();
          

          $parameters=$this->definition["INPUTS"][$key]["PARAMS"];
          if( $parameters )
          {
              $inputStr.="\t\t\t\t\t<?php\n";
              $inputStr.="\t\t\t\t\t\t\$inputParams=".$this->dumpArray($parameters,8).";\n";
              $inputStr.="\t\t\t\t\t?>\n";
          }
          else
          {
              $inputStr.="\t\t\t\t\t<?php \$inputParams=\"\";?>\n";
          }
          $inputStr.="\t\t\t\t\t[_INPUT]\n\t\t\t\t\t\t[*".$input."({\"name\":\"".$key."\",\"value\":\"".($value["FIELD"]?$value["FIELD"]:$key)."\",\"params\":\"\$inputParams\"})][#]\n\t\t\t\t\t[#]\n";
          $inputStr.="\t\t\t\t\t[_ERRORS]\n";
          foreach($errors as $key2=>$value2)
          {
              $inputStr.="\t\t\t\t\t\t[_ERROR({\"type\":\"".$key2."\",\"code\":\"".$value2."\"})][@L]".$this->parentModel->objectName->className."_".$this->action->name."_".$key."_".$key2."[#][#]\n";
          }
          $inputStr.="\t\t\t\t\t[#]\n";
          $inputStr.="\t\t\t\t[#]\n";
          $inputsExpr.=$inputStr;
      }

      $searchs = array("{%formKeys%}","{%layer%}","{%objectName%}","{%actionName%}","{%inputs%}","{%title%}","{%formerrors%}");
      $replaces = array($keyExpr,
                          $this->action->parentModel->objectName->layer,
                          $this->action->parentModel->objectName->className,
                          $this->action->name,
                          $inputsExpr,
                          $this->action->name." ".$this->parentModel->objectName->className,
                          $formErrors
                       );
      
      $formWidget=$phpCode."\n".$formCode."\n";
      
      $formWidget=str_replace($searchs,$replaces,$formWidget);      
      $path=$this->getFormPath();
      file_put_contents($path."/".$this->definition["ACTION"]["ACTION"].".wid",$formWidget);      
    }

    function fillBaseErrors($typeDef,& $errors)
    {
        
        // Al final, de BaseTypeException solo quiero unset
            if( $typeDef["REQUIRED"] )
                $errors["UNSET"]=1;
    }
    function fillActionErrors(& $errors)
    {
        $layer=$this->parentModel->objectName->layer;
        $objname=$this->parentModel->objectName->className;
        if(!is_file(PROJECTPATH."/$layer/objects/$objname/actions/".$this->action->name.".php"))
                return;
        include_once(PROJECTPATH."/$layer/objects/$objname/actions/".$this->action->name.".php");
        
        $exceptionClass="\\".$layer."\\".$objname."\\actions\\".$this->action->name."Exception";
        
        if( !class_exists($exceptionClass) )
            return;
        
        
        $reflectionClass=new \ReflectionClass($exceptionClass);

        // Se obtienen las constantes
        $constants = $reflectionClass->getConstants ();
        foreach($constants as $key=>$value)
        {        

         if( strpos($key,"ERR_")===0 )
            {
                $key=substr($key,4);
            }
            $errors[$key]=$value;
        }
    }


}
