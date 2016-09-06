<?php
namespace lib\reflection\html\forms;
class FormDefinition extends \lib\reflection\base\ConfiguredObject //ClassFileGenerator
{
    function __construct($name,$parentAction)
    {                
        $parentModel=$parentAction->parentModel;
        $this->name=$name;
        $this->action=$parentAction;
        
        parent::__construct($name,
                            $parentModel,
                            "\\html\\forms",
                            "/html/forms",
                            'forms',
                            "\\lib\\output\\html\\Form",
                            null);
    }

    function initialize($def=null)
    {
        parent::initialize($def);
        $this->action->addForm($this);
        $this->widget=new FormWidget($this->className,$this);

    }
    
    function create()
    {
        $modelDef=$this->parentModel;
        $objName=$modelDef->objectName->getNormalizedName();
        $name=$this->className;
        $actionDef=$this->action;
        $def=array();
        $def["NAME"]=$name;
        $def["OBJECT"]=$objName;
        $def["ACTION"]=array("OBJECT"=>$modelDef->objectName->getNamespaced("objects"),
                             "ACTION"=>$name,"INHERIT"=>true);

        $def["ROLE"]=$actionDef->getRole();
        $def["REDIRECT"]=array("ON_SUCCESS"=>"",
                               "ON_ERROR"=>"");
        $def["INPUTS"]=array();
        $relationName=$actionDef->getTargetRelation();
        if($relationName!="")
            $def["TARGET_RELATION"]=$relationName;

        $indexes=$actionDef->getIndexFields();
        if( $indexes )
        {
            $def["INDEXFIELDS"]=$indexes;
        }
        
        $fields=$actionDef->getFields();
        if($fields)
        {            
            
            $srcModel=$this->parentModel;
            
            $fromPrivateObject=$srcModel->objectName->isPrivate();
            
            foreach($fields as $key=>$value)
            {         
                // El parentModel del field actual es la Action, no es una modeldefinition.
                $targetDef=$value->getRawDefinition();
                $origField=$value->parentModel->parentModel->getFieldOrAlias($targetDef["FIELD"]);

                
                
                // En caso de que estemos editando un objeto privado, y tenemos una relacion con el objeto publico al que
                // pertenece, se incluye el campo de la relacion como parametro requerido.
                if($fromPrivateObject)
                {
                    if($origField->isRelation())
                    {
                        $targetObject=$origField->getRemoteModel();
                        
                        $role=$origField->getRole();
                        // Se comprueba si la relacion apunta al objeto que es el que define el namespace donde se encuentra este objeto.
                        if($role=="BELONGS_TO" && 
                           $targetObject->objectName->equals($srcModel->objectName->getNamespaceModel()))
                        {
                            $remFields=$origField->getRemoteFieldNames();
                                $def["INDEXFIELDS"][$key]=array("REQUIRED"=>1,"MODEL"=>''.$targetObject->objectName,"FIELD"=>$remFields[0],"MAP_TO"=>$key);
                                continue;
                        }
                    }
                }

                // El parentModel del field actual es la Action, no es una modeldefinition.
                /* $parent=$value->parentModel->parentModel;
                $def["FIELDS"][$key]=array("MODEL"=>$parent->objectName->getNormalizedName(),
                                           "FIELD"=>$targetDef["FIELD"],
                                           "REQUIRED"=>(isset($targetDef["REQUIRED"])?$targetDef["REQUIRED"]:0)
                                           );              
                $targetRelation=$value->getTargetRelation();
                if($targetRelation!="")
                {
                    $def["FIELDS"][$key]["TYPE"]="DataSet";
                    $def["FIELDS"][$key]["TARGET_RELATION"]=$targetRelation;
                }*/
                $params=null;
                if($relationName!="")
                {
                    $field=$this->parentModel->getFieldOrAlias($relationName);
                    $params=$field->getDefaultInputParams($this,$value);
                    // Ojo, los parametros de ponen al campo cuyo nombre es el nomrbe de la relacion multiple
                    if($params)
                        $def["INPUTS"][$relationName]["PARAMS"]=$params;
                }
                else
                {
                    $fieldModel=$targetDef["MODEL"];
                    if($fieldModel)
                    {
                        
                        $fieldParentModel=\lib\reflection\ReflectorFactory::getModel($fieldModel);
                        $fieldInstance=$fieldParentModel->getFieldOrAlias($targetDef["FIELD"]);
                        $params=$fieldInstance->getDefaultInputParams($this,$value);
                        if($params)
                            $def["INPUTS"][$key]["PARAMS"]=$params;
                    }                               
                }
                
                        
            }            
        }
        else
        {
            $def["NOFORM"]=true;
        }
        
        $this->initialize($def);
     
    }

    function getFormClass()
    {
        $layer=$this->parentModel->getLayer();
        $objName=$this->parentModel->objectName->getNormalizedName();
        $name=$this->name;
        return '\\'.$layer.'\\'.$objName.'\html\forms\\'.$name;
    }
    function getRole()
    {
        return $this->action->getRole();
    }

    function getFormPath()
    {
        return dirname($this->filePath);
    }

    function getWidgetPath()
    {
        $objName=$this->parentModel->objectName->getNormalizedName();
        $name=$this->name;
        return '/'.$objName.'/html/forms/'.$name;
    }
    
    function getDefinition()
    {
        if( !$this->definition["INDEXFIELDS"] )
        {
            $this->definition["INDEXFIELDS"]=array();
        }
        return $this->definition;
    }
    function saveModelMethods($generator)
    {
        $def=$this->getDefinition();
    
        $this->addProperty(array("NAME"=>"definition",
                                      "ACCESS"=>"static",
                                      "DEFAULT"=>& $this->definition
                                      ));
        $this->addMethod(array(
                "NAME"=>"__construct",
                "COMMENT"=>" Constructor for ".$this->name,
                "CODE"=>"\t\t\tparent::__construct(".$this->className."::\$definition,\$actionResult);\n",
                "PARAMS"=>array(
                        "actionResult"=>array(
                            "DEFAULT"=>"null",
                            "COMMENT"=>"\\lib\\action\\ActionResult instance.Errors found while validating this action must be notified to this object"
                    )
            )));
        $this->addMethod(array(
            "NAME"=>"initializeForm",
            "COMMENT"=>" Initialize form fields taken from the context (session,etc) :".$this->name,
            "CODE"=>"\n/"."* Insert the initialization code here *"."/\n\n"

        ));
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
           

        $fieldErrors=$this->getFieldErrors();
        // Se introducen los tags de traduccion.
        foreach($fieldErrors as $key=>$value)
        {
            foreach($value as $key2=>$value2)
            if(isset($value2["txt"]))
                $fieldErrors[$key][$key2]["txt"]="[@L]".$value2["txt"]."[#]";
        }
        $globalErrors=$this->getActionErrors();
        foreach($globalErrors as $key=>$value)
        {
            if(isset($value["txt"]))
                $globalErrors[$key]["txt"]="[@L]".$value["txt"]."[#]";
        }
        $this->definition["ERRORS"]["GLOBAL"]=$globalErrors;
        if($this->inheritsFromAction())
        {
            $this->definition["ERRORS"]["FIELDS"]=$fieldErrors;
        }
        else
        {
            foreach($this->definition["FIELDS"] as $key=>$value)
                $this->definition["FIELDS"][$key]["ERRORS"]=$value;
        }
        $this->addProperty(array("NAME"=>"definition",
            "DEFAULT"=>$this->definition
        ));
        $this->saveModelMethods($generator);

        $this->generate();
    }
    function inheritsFromAction()
    {
        $def=$this->getDefinition();
        return isset($def["ACTION"]["INHERIT"]) && $def["ACTION"]["INHERIT"] && !isset($def["FIELDS"]);

    }
    function generateCode()
    {
        $this->widget->generateCode();
    }
    function hasForm()
    {
        return !$this->definition["NOFORM"];
    }

    function getAction()
    {
        return $this->action;
    }
    function setWidget($wid)
    {
        $this->widget=$wid;
    }
    function getWidget()
    {
        return $this->widget;
    }
    function getIndexFields()
    {
        if(!isset($this->definition["INDEXFIELDS"]))
            return array();
        return $this->definition["INDEXFIELDS"];
    }
    function getFields()
    {
        $def=$this->getDefinition();
        if($this->inheritsFromAction())
            return $this->action->getFields();
        return parent::getFields();
    }
    function getField($key)
    {

        if($this->inheritsFromAction())
            return $this->action->getField($key);
        return parent::getField($key);
    }
    function getTranslatedDefinition()
    {
        $def=$this->getDefinition();
        if($this->inheritsFromAction())
        {
            $fDef=array();
            $fields=$this->getFields();
            foreach($fields as $key=>$value)
            {
                $fDef[$key]=$value->getDefinition();
            }
            $def["FIELDS"]=$fDef;
        }
        return $def;
    }

    function getFieldErrors()
    {
        $fields=$this->getFields();
        foreach($fields as $key=>$value)
        {
            $errors[$key]=$value->getFormInputErrors($value->getDefinition());
        }
        return $errors;
    }
    function getActionErrors()
    {
        return $this->action->generateErrors();
    }
}
