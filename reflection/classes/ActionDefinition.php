<?php
namespace lib\reflection\classes;
class ActionDefinition extends ClassFileGenerator{

    const ASSUME_PUBLIC='\n    /* This action has no permissions defined. Assumed _PUBLIC_ */\n';
    const CONTROLLER_METHOD_HEADER="/**\n  Name:%methodName%\n  Params:%params%\n  Description:%description%\n*/\n";
    function __construct($name,$parentModel,$definition)
    {
        $this->name=$name;
        $this->parentModel=$parentModel;
        $this->definition=$definition;
        $this->role=$definition["ROLE"];
        $this->isadmin=$definition["IS_ADMIN"];
        $this->label=$definition["LABEL"];
        if(!$this->label)
            $this->label=$this->name;
        if($this->definition["PERMISSIONS"])
        {            
            $this->permissionsObj=new PermissionRequirementsDefinition($this->definition["PERMISSIONS"]);
        }
        else
            $this->permissionsObj=null;
        ClassFileGenerator::__construct($this->name, $this->parentModel->objectName->layer, 
            $this->parentModel->objectName->getNamespace()."\\actions",
            $this->parentModel->objectName->getPath()."actions/".$this->name.".php",
            '\lib\action\Action');
    }
    
    static function createAction($type, $name, $parentModel, $indexFields,$requiredFields, $optionalFields, $permissionsObj,$remoteModel=null,$isadmin=false)
    {                
        $req=is_array($requiredFields)?$requiredFields:array();
        $opt=is_array($optionalFields)?$optionalFields:array();
        $idx=is_array($indexFields)?$indexFields:array();

        if($remoteModel)
             $remObjectRequired=$remoteModel->getIndexFields();


        $def=array(
                    "OBJECT"=>$parentModel->objectName->className,
                    "ROLE"             =>$type,        
                    "PERMISSIONS"=>$permissionsObj->getDefinition()
                   );
        if($isadmin)
            $def["IS_ADMIN"]=true;
        else 
            $def["IS_ADMIN"]=false;
            
        
        
        foreach($idx as $key=>$value)
        {            
            $def["INDEXFIELD"][$key]=array("REQUIRED"=>true,"MODEL"=>$value->parentModel->objectName->getNamespaced("compiled"),"FIELD"=>$key);
        }

        foreach($req as $key=>$value)
        {
            
            $def["FIELDS"][$key]=array("REQUIRED"=>true,"MODEL"=>$value->parentModel->objectName->getNamespaced("compiled"),"FIELD"=>$key);
        }
        foreach($opt as $key=>$value)
        {
            $def["FIELDS"][$key]=array("MODEL"=>$value->parentModel->objectName->getNamespaced("compiled"),"FIELD"=>$key);
        }
        $instance=new ActionDefinition($name,$parentModel,$def);
        return $instance;
    }
    function isAdmin()
    {
        return $this->definition["IS_ADMIN"];
    }
    function getRole()
    {
        return $this->role;
    }
    function getLabel()
    {
        return $this->label;
    }
    function getName()
    {
        return $this->name;
    }
    function getIndexes()
    {
        if($this->definition["INDEXFIELD"])
            return $this->definition["INDEXFIELD"];
    }
    function getFields()
    {
        return $this->definition["FIELDS"];
    }

   /*  function getMethodDefinition()
    {
        $code=ActionDefinition::CONTROLLER_METHOD_HEADER." public function on_".$this->name."(\$params,\$user)\n";
        $code.=" {\n";

        // Primero, se va a buscar si en esta accion, se encuentra el posible campo de Estado de este objeto.
        $stateField=$this->parentModel->getStateField();

        if($stateField)
        {
            $keys=array_keys($stateField);
            $stateFieldName=$keys[0];
            $stateFieldParam=$this->getStateFieldParam($stateFieldName);            
        }

        if(!$this->permissionsObj)
            $code.=ActionDefinition::ASSUME_PUBLIC;           

        else
        {
            switch(strtolower($this->role))
            {
               case 'create':
                   {
                       // Para crear un objeto, tenemos que tener permisos en su estado por defecto.
                       // Si no, tenemos que tener el permiso <modulo>_create
                       // 1) En caso de que tengamos estado:
                       //    Hay dos opciones: que entre los campos de la accion, se encuentre el campo estado.
                       //    En ese caso, hay que obtener el campo de estado, ver su valor , y buscar los permisos segun el valor.
                       $perms=null;
                       if($stateField)
                           {
                               if($stateFieldParam)
                               {
                                   if($this->permissionsObj)
                                       $code.='$this->checkStatedPermissions($params["'.$stateFieldParam.'"]->getValue(),\n'.
                                            $this->dumpArray($this->permissionsObj->getDefinition()).
                                            '$user);\n';
                               }
                               else
                               {
                                       $defaultState=$this->parentModel->getDefaultState();
                                       if($defaultState)                               
                                           $perms=$this->permissionsObj->getRequiredPermissionsForState($defaultState);
                                       
                                       if(!$perms)
                                           $code.=ActionDefinition::ASSUME_PUBLIC;
                                       
                                       $code.='$this->checkStatedPermissions("'.$defaultState.'",'.
                                            $this->dumpArray($this->permissionsObj->getDefinition()).
                                            '$user);\n';
                               }
                           }
                           else
                           {
                                $perms=$this->permissionsObj->getRequiredPermissions();
                                if(!$perms)
                                    $code.=ActionDefinition::ASSUME_PUBLIC;
                           }

                           if($perms)
                               $code.=$this->getPermissionsCode($perms);

                           $code.=$this->getDefaultModelCreation();
                           
                           $code.=$this->getDefaultFieldCheck();                           
                   }break;
               case 'delete':
               case 'edit':
                   {
                       

                       if($stateField)
                       {
                           $code.='$this->checkStatedPermissions($instance->getState(),'.
                               $this->dumpArray($this->permissionsObj->getDefinition()).
                               '$user);\n';
                       }
                       else
                       {
                           $perms=$this->permissionsObj->getRequiredPermissions();
                           $code.=$this->getPermissionsCode($perms,$fields);
                       }
                       $indexes=$this->definition["INDEXES"];
                       $code.="  \$index=array(\n";
                       $j=0;
                       foreach($indexes as $key=>$value)
                       {
                           if($j>0)
                               $code.=",\n";
                           $code.="    \"".$value["FIELD"]."\"=>\$params[\"".$key."\"]->getValue()";
                       }
                       $code.="\n   );\n";
                       $code.=$this->getDefaultModelCreation();
                       $code.="  \$instance->setId(\$index);\n";
                       $code.="  try {\n";
                       $code.="       \$instance->unserialize();\n";
                       $code.="      }\n";
                       $code.="  catch(\\lib\\model\\BaseException \$e)\n";
                       $code.="      {\n";
                       $code.="          \$result->addGlobalException(\$e);\n";
                       $code.="      }\n";
                       $code.="\n";
                       $code.=$this->getDefaultFieldCheck();
                       
                       $code.="   return \$result;\n";



                   }break;
               case 'batch':
                   {
                       // Hay que asegurarnos de que tenemos permisos sobre todos los objetos que nos han pasado.



                   }break;
            }
        }
        $code.="\n }\n";
        return $code;
    }

    function getDefaultFieldCheck()
    {
        $code="";
        $code.=" \$this->instance=\$instance;\n";

        if($this->definition["FIELDS"])
        {
            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                if($value["MODEL"]==$this->parentModel->objectName->getNamespaced("compiled"))
                {
                    // Es de este mismo objeto.
                    $code.="  \$this->copyField('$key',\$instance->".$value["FIELD"].",\$params->$key,\$result);\n";

                }
                else
                {
                    $path=$value["PATH"];
                    if(!$path)
                    {
                        printWarning("ATENCION, en el objeto ".$this->parentModel->objectName->className.", Accion:".$this->name.", se especifica un modelo para el campo ".$key." distinto de ".$this->parentModel->objectName->className.",pero no se indica el PATH");
                        continue;
                    }
                    $code.="  \$currentField=\$this->getPath(\"/instance".$value["PATH"]."\",\$this->context);\n";
                    $code.="  try{\n";
                    $code.="       \$currentField->set(\$params->".$key."->getValue());\n";
                    $code.="  }\n";
                    $code.="  catch(\\lib\\model\\BaseException \$e)\n";
                    $code.="  {\n";
                    $code.="       \$result->addFieldException(\"".$key."\",\$e);\n";
                    $code.="  }\n";

                }
            }
        }
        

        if($this->definition["VALIDATE"])
        {
            $code.="  try {\n";
            $code.="       \$isValid=\$instance->".$this->definition["VALIDATE"]["METHOD"]."(\$params);\n";
            $code.="      }\n";
            $code.="  catch(\\lib\\model\\BaseException \$e)\n";
            $code.="      {\n";
            $code.="          \$result->addGlobalException(\$e);\n";
            $code.="      }\n";                                              
        }
        else
            $code.="   \$isValid=true;\n";

        $code.="   if(\$isValid && \$result->getErrorCount()==0)\n";
        $code.="   {\n";
        $code.="        \$instance->save();\n\n";

        if($this->definition["CALLBACK"])
        {
            $code.="        \$instance->".$this->definition["CALLBACK"]["METHOD"]."(\$params);\n";
        }
        $code.="   }\n";
        return $code;
    }

    function getPermissionsCode($permissions,$keys=null)
    {
        return "";
    }

    function getStateFieldParam($stateField)
    {

        $name=$this->parentModel->objectName->getNamespaced("compiled");

        if($this->definition["FIELDS"])
        {

            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                if($value["MODEL"]==$name && $value["FIELD"]==$stateField)
                    return $key;
            }
        }
        return null;
    }
    
    function getDefaultModelCreation()
    {
        return "  \$result=\$this->createResult();\n  \$instance=\\lib\\model\\BaseModel::getModelInstance('".$this->parentModel->objectName->getNamespaced("compiled")."',\$this->serializer);\n";
    }
    */


    function saveModelMethods()
    {
        $def=$this->getDefinition();
        
            $this->addMethod(array(
                "NAME"=>"__construct",
                "COMMENT"=>" Constructor for ".$this->name,
                "CODE"=>"\t\t\t\\lib\\action\\Action::__construct(".$this->name."::\$definition);\n"
            ));
            $this->addMethod(array(
                    "NAME"=>"validate",
                    "COMMENT"=>" Callback for validation of action :".$this->name,
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
                    "COMMENT"=>" Callback executed when this action had success.".$this->name,
                    "PARAMS"=>array(
                        "model"=>array(
                            "COMMENT"=>" If this object had a related model, it'll be received in this parameter, once it has been saved."
                            ),
                        "user"=>array(
                            "COMMENT"=>" User executing this request"
                            )
                        ),
                    "CODE"=>"\n/"."* Insert callback code here *"."/\n\nreturn true;\n"
                ));
            $this->addMethod(array(
                    "NAME"=>"onError",
                    "COMMENT"=>" Callback executed when this action had an error".$this->name,
                    "PARAMS"=>array(
                        "params"=>array(
                            "COMMENT"=>" Parameters received.Note these parameters are the same received in Validate"
                            ),
                        "actionResult"=>array("COMMENT"=>"\\lib\\action\\ActionResult instance.Errors found while validating this action must be notified to this object"
                            ),
                        "user"=>array(
                            "COMMENT"=>" User executing this request"
                            )
                        ),
                    "CODE"=>"\n/"."* Insert callback code here *"."/\n\nreturn true;\n"
                ));        
    }

    function addForm($form)
    {
        $this->form=$form;
    }

    function getForm()
    {
        return $this->form;
    }
    function saveClass()
    {
        $this->addProperty(array("NAME"=>"definition",
                                      "ACCESS"=>"static",
                                      "DEFAULT"=>$this->getDefinition()
                                      ));
        $this->saveModelMethods();
        $this->generate();
    }

    function getDefinition()
    {
        $def=array("OBJECT"=>$this->parentModel->objectName->className,
                   "ROLE"=>$this->role,
                   "LABEL"=>$this->label,
                   "INDEXFIELD"=>$this->definition["INDEXFIELD"],
                   "FIELDS"=>$this->definition["FIELDS"],                   
                    );        
        if($this->isAdmin())
            $def["IS_ADMIN"]=true;
        if($this->permissionsObj)
        {
            $def["PERMISSIONS"]=$this->permissionsObj->getDefinition();
        }
        return $def;
    }
    
    function save($actionName)
    {
        if($this->parentModel->config->mustRebuild("actions",$this->name,$this->filePath))
            $this->saveClass();
    }
}
