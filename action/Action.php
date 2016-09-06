<?php
namespace lib\action;

class ActionException extends \lib\model\BaseException
{
    const ERR_CANT_EDIT_WITHOUT_KEY=1;
    const ERR_REQUIRED_FIELD=2;
}

class Action
{
    protected $destModel = null;

    function __construct($definition)
    {
        $this->definition=$definition;
	//$this->initialize($definition);
    }
    function initialize($definition=null)
    {
        $this->definition = $definition;
	if(!$definition)
	{
 		$n=get_class($this);
		$this->definition=$n::$definition;	
	}
    }

    static function getAction($object, $name)
    {
        $objName = new \lib\reflection\model\ObjectDefinition($object);
        include_once($objName->getActionFileName($name));
        $className = $objName->getNamespacedAction($name);
        $instance = new $className();
        return $instance;
    }

    function setModel($model)
    {
        $this->destModel = $model;
    }

    // En este punto, la validacion de requisitos de campos, etc, ya esta hecha.

    function process($keys, $fields, & $actionResult, $user)
    {

 	$n=get_class($this);
	$def=$n::$definition;
        $role=strtoupper($def["ROLE"]);
    if(isset($this->definition["FIELDS"]))
    {
        foreach ($this->definition["FIELDS"] as $key => $value) {
            $cDef = $def["FIELDS"][$key];
            if($cDef["REQUIRED"])
            {
                try
                {
                    $f=$fields->{"*".$key};
                    if(!$f->hasOwnValue())
                    {
                        $actionResult->addFieldTypeError($key, null, new \lib\model\types\BaseTypeException(\lib\model\types\BaseTypeException::ERR_UNSET));
                        return false;
                    }
                }catch(\Exception $e)
                {
                    $actionResult->addFieldTypeError($key, null, new \lib\model\types\BaseTypeException(\lib\model\types\BaseTypeException::ERR_UNSET));
                    return false;
                }
            }
        }
    }
        try {
            $this->validate($fields, $actionResult, $user);
        } catch (\Exception $e) {
            $actionResult->addGlobalError($e);
        }

        if ($actionResult->isOk()) {
            $oldState = null;
            $newState = null;
            if ($role != "SEARCH") {

                if (!$this->destModel) {
                    // Se carga el modelo, y se asignan campos.
                    if ($def["OBJECT"]) {
                        $this->destModel = \lib\model\BaseModel::getModelInstance($def["OBJECT"]);
                        if ($keys) {
                            $this->destModel->setId($keys);
                            $this->destModel->unserialize();
                        }
                        else
                        {
                            if($role=="EDIT")
                            {
                                $actionResult->addGlobalError(new ActionException(ActionException::ERR_CANT_EDIT_WITHOUT_KEY));
                                return false;
                            }
                        }

                        $fDef = $fields->__getFields();
                        $namespaced = $this->destModel->__getFullObjectName();

                        // Lo primero que se modifica, si es necesario, es el campo estado.

                        $stateFieldName = $this->destModel->getStateField();
                        if ($stateFieldName && isset($fDef[$stateFieldName])) {
                            $stateField = $fDef[$stateFieldName];
                            if ($stateField->hasOwnValue()) {
                                try {
                                    $oldState = $this->destModel->{$stateFieldName};
                                    $newState = $stateField->get();
                                    $this->destModel->{$stateFieldName} = $newState;
                                    unset($fDef[$stateFieldName]);
                                } catch (\Exception $e) {
                                    $actionResult->addFieldTypeError($stateFieldName, null, $e);
                                }
                            }
                        }

                        if (!$actionResult->isOk())
                            return $this->onError($keys, $fields, $actionResult, $user);
                        // KNOWN PROBLEM:
                        // Al crear un elemento, es necesario que se establezcan primero las relaciones, y luego
                        // el resto de campos.
                        // Por ejemplo, supongamos que tenemos un objeto nuevo A , con una relacion con B.
                        // B tiene un campo b1
                        // Supongamos que en el formulario se quiere editar,a la vez, un A, y el campo b1 de B.
                        // Pero no queremos crear un B nuevo, por lo que tambien se pasa el id_b.
                        // Si el orden de ejecucion es: Se asigna id_b/b1 , y luego id_b, se perdera el b1, ya que al establecer
                        // id_b, se resetean los valores cargados de la relacion.
                        // Hay que asignar primero id_b, y luego, id_b/b1.

                        if ($fDef && isset($this->definition["FIELDS"])) {
                            foreach ($this->definition["FIELDS"] as $key => $value) {
                                $cDef = $def["FIELDS"][$key];
                                if (isset($cDef["MODEL"])) {

                                    $mT = new \lib\reflection\model\ObjectDefinition($cDef["MODEL"]);
                                    if ($mT->getNamespaced() == $namespaced) {
                                        // $key es el campo del formulario, $targetField es el campo del modelo.
                                        $targetField = $cDef["FIELD"];
                                        try {
                                            $fieldSet = $fields->__getField($key)->hasOwnValue();
                                        } catch (\Exception $e) {

                                            exit();
                                        }
                                        try {
                                            if ($fieldSet) {
                                                $val = $fields->{$key};
                                            } else {

                                                $field = $this->destModel->__getField($targetField);
                                                $updatesOnNull = ($field->getType()->getFlags() & \lib\model\types\BaseType::TYPE_NOT_MODIFIED_ON_NULL) ||
                                                    (isset($this->__fieldDef[$key]["UPDATE_ON_NULL"]) && !$this->__fieldDef[$key]["UPDATE_ON_NULL"]);

                                                $fieldDefinition=$def["FIELDS"][$key];
                                                if($fieldDefinition && $fieldDefinition["REQUIRED"])
                                                    $actionResult->addFieldTypeError($key, null, new \lib\model\types\BaseTypeException(\lib\model\types\BaseTypeException::ERR_UNSET));
                                                else
                                                {
                                                if ($this->destModel->isRequired($targetField)) {
                                                    // Si es un campo requerido, que esta en unset desde el action, pero que el modelo si que
                                                    // tiene establecido, no se considera error a menos que UPDATE_ON_NULL sea true
                                                    if ($field->is_set() && !$updatesOnNull)
                                                        continue;
                                                    $actionResult->addFieldTypeError($key, null, new \lib\model\types\BaseTypeException(\lib\model\types\BaseTypeException::ERR_UNSET));
                                                }

                                                // Si el campo destino es una relacion, y el campo que viene del
                                                // formulario esta a nulo, se elimina la relacion.

                                                if ($field->isRelation()) {
                                                    $field->set(null);
                                                    continue;
                                                }
                                                if (!$updatesOnNull)
                                                    continue;
                                                if (!isset($this->__fieldDef[$key]["UPDATE_ON_NULL"]) && !$this->__fieldDef[$key]["UPDATE_ON_NULL"]) ;
                                                continue;
                                                }
                                            }
                                            if($actionResult->isOk())
                                                $this->destModel->{$targetField} = $val;
                                        } catch (\Exception $e) {
                                            $actionResult->addFieldTypeError($key, null, $e);
                                        }
                                    }

                                }
                            }
                        }
                    } else
                        $this->destModel = $fields;
                }
            }

            $actionResult->setModel($this->destModel);
        }
        // En este momento, ya se tiene el modelo.Ahora ya es posible comprobar permisos.
        if ($def["PERMISSIONS"]) {
            /* $permissionDef=new \lib\reflection\classes\PermissionRequirementsDefinition($def["PERMISSIONS"]);
             $requirements=$permissionDef->getRequiredPermissions($fields);
             include_once(PROJECTPATH."/lib/model/permissions/PermissionsManager.php");
             $oPerms=new \PermissionsManager($ser);
             $canAccess=$oPerms->canAccess($fields,$requirements,$user);
             if(!$canAccess)
             {
                // Error, no existen permisos para acceder a este objeto.
                $actionResult->addPermissionError();
             }*/
        }

        if ($actionResult->isOk()) {
            // Se llama al metodo que debe completar el objeto cuando hay cambios de estado.
            if ($oldState != $newState)
                $this->completeStateChange($this->destModel, $oldState, $newState);

            // Se comprueban los estados.
            if ($role != "SEARCH" && $role!="Delete") {
                try {
                    $this->destModel->__checkState();
                } catch (\lib\model\BaseTypedException $e) {
                    $params = $e->getParams();
                    $actionResult->addFieldTypeError($params["field"], null, $e);
                }
            }
        }

        if (!$actionResult->isOk()) {
            $this->onError($keys, $fields, $actionResult, $user);
            return;
        }


        if ($actionResult->isOk()) {
            global $globalContext;

            try {
                if ($role != "SEARCH") {
                    // Si el callback onSuccess devuelve un modelo, el actual no se guarda, y se establece el modelo retornado como
                    // el resultado de la accion.
                    $result = $this->onSuccess($this->destModel, $user);
                    if (is_object($result) && is_a($result, "\\lib\\model\\BaseModel"))
		            {
                        $actionResult->setModel($result);
			            $this->onSaved($result);
		            }
                    else
                    {
                        switch($role)
                        {
                            case "DELETE":
                            {
                                $this->destModel->delete();
                                $actionResult->setModel(null);
                            }break;
                            case "STATIC":
                            {
                                $actionResult->setModel(null);
                            }break;
                            default:
                                {
                                $this->destModel->save();
                                $this->onSaved($this->destModel);
                                }
                        }
                    }
                }
                else
                {
                    $result = $this->onSuccess($this->destModel, $user);
                    $actionResult->setModel($result);
                }
            } catch (\Exception $e) {
                $actionResult->addGlobalError($e);
                return false;
            }
        } else {
            $this->onError($keys, $fields, $actionResult, $user);
        }
    }

    function onError($keys, $params, $actionResult, $user)
    {
        return true;
    }

    function validate($params, $actionResult, $user)
    {
        return true;

    }

    function completeStateChange($model, $oldstate, $newstate)
    {

    }

    function onSuccess($model, $user)
    {
        return true;
    }

    function onSaved($model)
    {

    }

    function getModel()
    {
        return $this->model;
    }

    function getParametersInstance()
    {
        $definition=$this->definition;
        if(isset($definition["INDEXFIELDS"]))
            $definition["FIELDS"]=array_merge($definition["FIELDS"],$definition["INDEXFIELDS"]);
        $params=new \lib\model\BaseTypedObject($definition);

        return $params;
    }
}

?>
