<?php
namespace lib\reflection\actions;
class ActionDefinition extends \lib\reflection\base\ConfiguredObject
{
    const ASSUME_PUBLIC='\n    /* This action has no permissions defined. Assumed _PUBLIC_ */\n';
    const CONTROLLER_METHOD_HEADER="/**\n  Name:%methodName%\n  Params:%params%\n  Description:%description%\n*/\n";
    var $name;
    function __construct($projectName,$name,$parentModel)
    {
        $this->name=$name;
        parent::__construct($projectName,$name, $parentModel,'\actions','actions','actions','\lib\action\Action');
    }

    function initialize($definition=null)
    {
        parent::initialize($definition);
        
        if(!$this->definition)
        {
            echo "SIN DEFINICION<br>";
            return;
        }        
        $this->role=$this->definition["ROLE"];
        $this->isadmin=$this->definition["IS_ADMIN"];
        if($this->definition["PERMISSIONS"])
            $this->permissionsObj=new \lib\reflection\permissions\PermissionRequirementsDefinition($this->definition["PERMISSIONS"]);
        else
            $this->permissionsObj=null;
        $this->parentModel->addAction($this->className,$this);
        
    }
    

    function create($type, $indexFields,$requiredFields, $optionalFields,$remoteModel=null,$isadmin=false,$relationName="",$paths=array())
    {
        $parentModel=$this->parentModel;
        //$perm = \lib\reflection\permissions\PermissionRequirementsDefinition::create(array_merge($modifyPermission, array(array("MODEL"=>$objName,"PERMISSION"=>"edit"))));
        $pIndex=0;
        $cName=$parentModel->objectName->getNormalizedName();
        
        if($parentModel->getOwnershipField)
        {
            $pIndex=1;
            $basePerm[]="_OWNER_";
        }
        $basePerm[$pIndex]["MODEL"]=$cName;
        
        switch($type)
        {
        case "AddRelationAction":
        case "AddAction":{
             $modelPerms=$isadmin?"adminCreate":"create"; 
            }break;
        case "SetRelationAction":
        case "DeleteRelationAction":
        case "EditAction":{$modelPerms=$isadmin?"adminEdit":"edit";}break;        
        case "DeleteAction":{$modelPerms=$isadmin?"adminDelete":"delete";}break;
        }
        $basePerm[$pIndex]["PERMISSION"]=$modelPerms;
        $permissionsObj = \lib\reflection\permissions\PermissionRequirementsDefinition::create($basePerm);

        $req=is_array($requiredFields)?$requiredFields:array();
        $opt=is_array($optionalFields)?$optionalFields:array();
        $idx=is_array($indexFields)?$indexFields:array();

        $def=array(
                    "OBJECT"=>$this->getParentModelName(),
                    "ROLE"  =>str_replace("Action","",$type),        
                    "PERMISSIONS"=>$permissionsObj->getDefinition()
                   );
        $this->role=$type;
        $def["IS_ADMIN"]=$isadmin?true:false;
    
            
        if($relationName)
        {
            $def["TYPE"]="DataSet";
            $def["TARGET_RELATION"]=$relationName;
        }

        foreach($idx as $key=>$value)
        {
            if(!is_object($value))
            {
                echo "PADRE:".$this->parentModel->objectName->getNamespaced();
                echo "Action:".$this->className."<br>";
                var_dump($idx);
            }
            // Si el campo indice de esta accion es una relacion, y el objeto actual
            // extiende de otro, y esa relacion apunta a ese otro, se modifica la definicion
            // del indice, para que apunte al modelo actual, no al remoto.


                $def["INDEXFIELDS"][$key]=array(
                    "REQUIRED"=>true,
                    "FIELD"=>$value->getName(),
                    "MODEL"=>$value->parentModel->objectName->getNamespaced()
                );
            
            
        }

        if($this->parentModel->objectName->className=="WebLayout")
        {
            $pos=1;
        }
        foreach($req as $key=>$value)
        {
            $fieldClass=$value->parentModel->objectName->getNamespaced();
            $classInstance=\lib\reflection\ReflectorFactory::getModel($fieldClass);
            $fieldName=$value->getName();
            if($paths[$key])
            {
                $fieldName=$paths[$key];
                $classInstance=$this->parentModel;
            }
            $fieldInstance=$classInstance->getFieldOrAlias($fieldName);
            if(!$fieldInstance->isAlias())
            {
                $ftype=$fieldInstance->getType();
                $k1=array_keys($ftype);
                if(!$ftype[$k1[0]]->isEditable())
                {
                    unset($req[$key]);
                    continue;
                }
            }
            
            $newDef=array("REQUIRED"=>true,"FIELD"=>$value->getName());
            if($value->parentModel->isConcrete())
                $newDef["MODEL"]=$fieldClass;
           
            if($relationName!="")
            {
                // Si se esta editando una relacion, todos los campos requeridos van a tener establecido TARGET_RELATION
                // dentro de la definicion del campo.
                // Esta suposicion (que todos los campos requeridos sirven para resolver la TARGET_RELATION), solo es cierto
                // cuando el formulario que se esta creando, solo edita la relacion (no existen mas campos). O sea, si se
                // edita el objeto Seccion, que tiene una MxN sobre Usuarios a traves del alias "editors", hay 2 formularios:
                // Uno para los campos "normales" de Seccion, y otro exclusivo para modificar el campo "editors".
                // Esto no tiene por que ser siempre asi, pero, en el caso de los formularios generados, si que lo es.
                // Por eso, en el caso de que ambos formularios se fundieran en uno , hay que marcar a nivel de campo,
                // el hecho de que este campo no es un field, es un alias, y que simboliza la relacion.
                $newDef["TARGET_RELATION"]=$relationName;
                if($type=="SetRelation" && $relationName!="")
                    $newDef["DATACONTAINER"]="array";
            }

            
            $def["FIELDS"][$key]=$newDef;

            // Si el campo es una relacion, hay que definir un datasource de validacion.
            // Hay que propagarlo tambien al form.
            if($value->isRelation())
            {
                if(!isset($paths[$key]))
                {
                    $target=$value->getRemoteModelName();
                }
               $def["FIELDS"][$key]["DATASOURCE"]=array("OBJECT"=>$target,"NAME"=>"FullList","PARAMS"=>array());
            }
            // Si existe un mapeo de este campo, deshacemos lo ya hecho, y hacemos que el modelo sea el actual, y el nombre del campo, el path
            // Esto sirve para el caso en el que 1 campo es un path del tipo a/b/c.El campo que nos ha llegado es c, que apunta al modelo donde esta c.
            // Lo que queremos es que apunte al modelo actual, y el nombre del campo sea a/b/c.
            if(isset($paths[$key]))
            {
                $def["FIELDS"][$key]["MODEL"]=$this->parentModel->objectName->getNamespaced();
                $def["FIELDS"][$key]["FIELD"]=$paths[$key];
            }
        }
        // Hay que evitar que, en caso de que se este usando una FakeRelationship,se
        // ejecute el bucle interno.

        foreach($opt as $key=>$value)
        {
            echo "<h1>$key</h1>";
            $fieldClass=$value->parentModel->objectName->getNormalizedName();
            // Volvemos a obtener el campo.Esto es necesario por los campos basados en un path.
            // Si el campo es del tipo a/b/c, $value apunta a "a".Necesitamos algo que apunte a "c"

            //if(!is_a($value,'\lib\reflection\model\aliases\FakeRelationship'))
            //{                
                //$classInstance=\lib\reflection\ReflectorFactory::getModel($fieldClass);
            $classInstance=$value->parentModel;

                //$fieldInstance=$classInstance->getFieldOrAlias($key);
            $fieldInstance=$value;

                if(!$fieldInstance->isAlias())
                {
                    $ftype=$fieldInstance->getType();         
                    if(!$ftype[$value->name]->isEditable())
                    {
                        unset($req[$value->name]);
                        continue;
                    }
                }
            //}
            // El modelo de origen, siempre es el modelo de inicio incluso en los casos de "a/b/c".
            // De la misma forma, el campo es el campo inicial, del modelo inicial.
           $def["FIELDS"][$key]=array("MODEL"=>$fieldClass,
                                           "FIELD"=>$key);
           if($relationName!="")
           {
               $def["FIELDS"][$key]["TARGET_RELATION"]=$relationName;
           }
           // Si el campo es una relacion, hay que definir un datasource de validacion.
           // Hay que propagarlo tambien al form.
           if($value->isRelation())
           {
               // Pero el ds de validacion, en todo caso, debe provenir del ultimo objeto de la cadena "a/b/c"
               //$sourceField=
               $target=$value->getRemoteModelName();
               $def["FIELDS"][$key]["DATASOURCE"]=array("OBJECT"=>$target,"NAME"=>"FullList","PARAMS"=>array());
           }
            if(isset($paths[$key]))
            {
                $def["FIELDS"][$key]["MODEL"]=$this->parentModel->objectName->getNamespaced();
                $def["FIELDS"][$key]["FIELD"]=$paths[$key];
            }
        }
        $this->initialize($def);
        return $this;
    }

    function getIndexFields()
    {
        if(!isset($this->definition["INDEXFIELDS"]))
            return array();
        return $this->definition["INDEXFIELDS"];
    }

    function isAdmin()
    {
        return $this->definition["IS_ADMIN"];
    }
    function getRole()
    {
        return $this->role;
    }
    
    function getTargetRelation()
    {
        return $this->definition["TARGET_RELATION"];
    }
   
    function saveModelMethods($generator)
    {
        $def=$this->getDefinition();
        
            $this->addMethod(array(
                "NAME"=>"__construct",
                "COMMENT"=>" Constructor for ".$this->name,
                "CODE"=>"\t\t\tparent::__construct(".$this->className."::\$definition);\n"
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

    
    function saveClass()
    {
        $this->addProperty(array("NAME"=>"definition",
                                      "ACCESS"=>"static",
                                      "DEFAULT"=>$this->getDefinition()
                                      ));
        $this->saveModelMethods($generator);
        $this->generate();
    }

    function getDefinition()
    {
        return $this->definition;
    }    
    function save()
    {        
            $this->saveClass();
    }

    function addForm($formDef)
    {
        $this->forms[]=$formDef;
    }
    function getForms()
    {
        return $this->forms;
    }

    function generateErrors()
    {
        // Se obtienen las constantes de la clase base, BaseType, para filtrarlas.
        // Se deben filtrar ya que dichas constantes son para excepciones internas,
        // no relacionadas con los errores que pueden aparecer en un formulario.
        $exName=$this->namespace.'\\'.$this->className."Exception";
        if(!class_exists($exName))
            return array();

        return $exName::getPrintableErrors($exName);
     }
}
