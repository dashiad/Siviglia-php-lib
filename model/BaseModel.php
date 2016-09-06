<?php

namespace lib\model;

class BaseModelException extends \lib\model\BaseException
{

    const ERR_NO_SERIALIZER = 1;
    const ERR_NOT_A_FIELD = 2;
    const ERR_INVALID_VALUE = 3;
    const ERR_WRONG_TYPE_IN_RELATIONSHIP = 4;
    const ERR_UNKNOWN_KEY_FIELD = 5;
    const ERR_INCOMPLETE_KEY = 6;
    const ERR_CANT_LOAD_EMPTY_OBJECT = 7;
    const ERR_INVALID_OFFSET = 8;
    const ERR_INVALID_SERIALIZER = 9;
    const ERR_NO_SUCH_METHOD=10;
    const ERR_NO_STATUS_FIELD=11;
    const ERR_DOUBLE_STATE_CHANGE=12;
    const ERR_UNKNOWN_OBJECT=13;
    const ERR_INVALID_STATE_DATASOURCE=14;
    const ERR_NOT_ENOUGH_PERMISSIONS=15;
}


class BaseModel extends BaseTypedObject
{

    protected $__aliasDef;
    protected $__filterConditions;
    protected $__key;
    protected $__inherits;
    protected $__inheritedModel;
    //protected $__nextState;
    protected $__getConditions;
    protected $serializer;
    protected $__objName;
    protected $__new = true;
    protected $__filters = array();
    protected $__relayAllowed=true;
    protected $__writeSerializer;
    protected $__saving;
    function __construct($serializer = null, $definition = null)
    {
        $this->__objName = new \lib\reflection\model\ObjectDefinition('\\'.get_class($this));
        if (!$definition)
        {            
            $defname = $this->__objName->getNamespaced() . "\\Definition";
            include_once($this->__objName->getDestinationFile()."/Definition.php");
            // Se hace new() por si la definicion requiere inicializacion de constantes.
            $ins=new $defname();
            BaseTypedObject::__construct($defname::$definition);
        }
        else
            BaseTypedObject::__construct($definition);

        $this->__aliasDef = & $this->__objectDef["ALIASES"];     

        if ($this->__objectDef["INDEXFIELDS"])
            $this->__key = new ModelKey($this, $this->__objectDef);

        if ($serializer)
        {
            $this->__serializer = $serializer;
            if(!isset($this->__objectDef["DEFAULT_WRITE_SERIALIZER"]))
                $this->__writeSerializer=$this->__serializer;
        }
    }

    function & __getAlias($aliasName)
    {
            if(!isset($this->__fields[$aliasName]))
            {
                if(isset($this->__aliasDef[$aliasName]))
                {
                    $this->__fields[$aliasName]=\lib\model\ModelField::getModelField($aliasName,$this,$this->__aliasDef[$aliasName]);
                }
                else
                {
                    // Si no era exactamente el nombre de un alias,se ve
                    // si se estan asignando parametros al alias.
                    //$reg=preg_match("/^([^{]*)(:?\{([^}]*)\}){0,1}$/",$aliasName,$matches);
                    //if(isset($matches[3]) || $matches[3]=="")
                    //{
                        // clean_debug_backtrace();
                        //echo "ALIAS::$aliasName";
                        include_once(PROJECTPATH."/lib/model/BaseModel.php");
                        throw new BaseModelException(BaseTypedException::ERR_NOT_A_FIELD,array("name"=>$aliasName));
                    /*}
                    $fname=$matches[1];
                    $aliasF=$this->__getAlias($fname);
                    $params=explode(",",$matches[3]);
                    for($k=0;$k<count($params);$k++)
                    {
                        $curParam=explode(":",$params[$k]);

                    }*/

                }
            }

            return $this->__fields[$aliasName];            
    }
    function getAliases()
    {
        $res=array();
        foreach($this->__aliasDef as $key=>$value)
        {
            $res[$key]=$this->__getAlias($key);
        }
        return $res;
    }

    function setId($id)
    {
        $this->__key->set($id);
    }

    function loadFromArray($data, $serializer,$raw=false)
    {        
        BaseTypedObject::loadFromArray($data, $serializer,$raw);
        $this->__new = false;
        $this->__loaded=true;
    }

    function load($data)
    {
        BaseTypedObject::load($data);
        $this->__new = false;
    }



    function __getKeys()
    {
        return $this->__key;
    }

    function __getField($fieldName)
    {

        try
        {

            return parent::__getField($fieldName);
        }
        catch(\lib\model\BaseTypedException $e)
        {        
            
            if ($this->__aliasDef && isset($this->__aliasDef[$fieldName]))
            {
                $newField=$this->__addField($fieldName,$this->__aliasDef[$fieldName]);
                return $newField;
            }            
            include_once(PROJECTPATH."/lib/model/BaseModel.php");
            throw new BaseModelException(BaseModelException::ERR_NOT_A_FIELD,array("name"=>$fieldName));
        }        
    }
    function &  __getFieldDefinition($fieldName)
    {
            if(isset($this->__fieldDef[$fieldName]))
                return $this->__fieldDef[$fieldName];
            else
            {        
                if ($this->__aliasDef && isset($this->__aliasDef[$fieldName]))
                    return $this->__aliasDef[$fieldName];
            }            
            include_once(PROJECTPATH."/lib/model/BaseModel.php");
            throw new BaseModelException(BaseModelException::ERR_NOT_A_FIELD,array("name"=>$fieldName));
   }

    function __getObjectName()
    {
        return $this->__objName->className;
    }
    function __getObjectNameObj()
    {
        return $this->__objName;
    }

    function __getFullObjectName()
    {
        return $this->__objName->getNamespaced();
    }

    function __isNew()
    {
        return $this->__new;
    }

    function __getFilter($serializerType)
    {
        return $this->__filters[$serializerType];
    }

    function __getTableName()
    {
        $tableName = $this->__objectDef["TABLE"];
        if ($tableName)
            return $tableName;
        return $this->__objName;
    }

    function __getObjectDefinition()
    {
        return $this->__objectDef;
    }
    function __get($varName)
    {
        try{
            if($varName[0]=="!")
            {
                $varName=substr($varName,1);
                $f=$this->__getField($varName);
                if($f->isRelation())
                {
                    return $f->getRaw();
                }
            }
            $val= parent::__get($varName);
            return $val;
        }catch(\lib\model\BaseTypedException $e)
        {
            $alias=$this->__getAlias($varName);
            return $alias->get();
        }
    }
    function unserialize($serializer = null)
    {
        if (!$serializer)
            $serializer = $this->__getSerializer();
        if (!$serializer)
        {
            $layer = $this->__objName->layer;
            global $SERIALIZERS;
            $serializer = \lib\storage\StorageFactory::getSerializer($SERIALIZERS[$layer]);
            //$serializer->useDataSpace($SERIALIZERS[$layer]["ADDRESS"]["database"]["NAME"]);
        }
        if (!$serializer)
            throw new BaseModelException(BaseModelException::ERR_NO_SERIALIZER);
        try
        {
            $serializer->unserialize($this);
        }
        catch(\Exception $e)
        {
            throw new BaseModelException(BaseModelException::ERR_UNKNOWN_OBJECT);
        }
        $this->__new = false;
        $this->__loaded=true;
        $this->cleanDirtyFields();
    }

    function copy(& $remoteObject)
    {

        $remFields=$remoteObject->__getFields();

        foreach($remFields as $key=>$value)
        {
            $types=$value->getTypes();                 
            foreach($types as $tKey=>$tValue)
            {
                if(isset($this->__fieldDef[$tKey]))
                    $field=$this->__getField($tKey);
                else                
                {
                    $field=$this->__getAlias($tKey);                    
                }
                $field->copyField($tValue);                                     
            }                 
        }
        //$this->__dirtyFields=$remoteObject->__dirtyFields;             
        //$this->__isDirty=$remoteObject->__isDirty;             
        $this->__new=!$this->__key->is_set();
        if(!$this->__new)
            $this->__loaded=true;
    }

    function loadFromFields()
    {
        $filters = array();
        $serializer=$this->__getSerializer();
        // Aqui solo interesan los campos a los que ya se haya accedido.
        foreach ($this->__fields as $key => $value)
        {
            if ($value->isDirty())
            {
                $filters[] = array("FILTER" => array("F" => $key, "OP" => "=", "V" => \lib\model\types\TypeFactory::serializeType($value->getType(), $serializer->getSerializerType())));                
            }
        }
        if (count($filters) == 0)
        { // No existen filters
            throw new BaseModelException(BaseModelException::ERR_CANT_LOAD_EMPTY_OBJECT, array("object" => $this->__objName));
        }
        try
        {
            $this->__serializer->unserialize($this, array("CONDITIONS" => $filters));
        }
        catch(\Exception $e)
        {
            throw new BaseModelException(BaseModelException::ERR_UNKNOWN_OBJECT);
        }
        $this->__new=false;
        $this->__loaded=true;
        $this->__isDirty=false;
    }
    function reload()
    {
        if($this->__new)
            return;
        $this->unserialize();

    }
    function __call($name,$arguments)
    {        
        if(strpos($name,"fetchBy")==0)
        {
            $fieldName= str_replace("fetchBy", "", $name);
            if(!isset($this->__fieldDef[$fieldName]))
            {
                _d($fieldName);
                debug_trace_plain();
                throw new BaseModelException(BaseModelException::ERR_NOT_A_FIELD,array("name"=>$fieldName));
            }
            $cField=$this->__getField($fieldName);
            $cField->set($arguments[0]);
            $serializer=$this->__getSerializer();
            $filters[] = array("FILTER" => array("F" => $fieldName, "OP" => "=", "V" => \lib\model\types\TypeFactory::serializeType($cField->getType(), $serializer->getSerializerType())));
            
            $serializer->fetchAll(array("BASE"=>array("*"),"TABLE"=>$this->__getTableName(),"CONDITIONS"=>$filters),$data,$nRows, $matchingRows, null);
        
            if($nRows==0)
                return null;
            return $data; 
        }
        
        throw new BaseModelException(BaseModelException::ERR_NO_SUCH_METHOD,array("method"=>$name));
    }


    function delete($serializer=null)
    {
        if (!$serializer)
            $serializer = $this->__getSerializer("WRITE");
        $serializer->delete($this);
        $this->nuke();
    }

    function save($serializer = null)
    {
        if($this->__saving || $this->__stateDef->isChangingState())
            return;
        $this->__saving=true;
        // Ahora, cualquier relacion que tuviera este objeto con otro, a traves de un campo definido en este objeto, 			
        if (!$serializer)
            $serializer = $this->__getSerializer("WRITE");
        if($this->mustSelfNuke())
        {
            if($this->__isNew())
            {
                $this->nuke();
                $this->__saving=false;
                return;
            }
            $this->delete($serializer);
            $this->__saving=false;
            return;
        }
        $this->__checkState();
        $this->__loaded = true;
        $isNew=$this->__new;
        do
        {
            $this->__saveMembers($serializer);
        }
        while ($this->isDirty());
        parent::save();
        if($isNew)
        {
            \lib\model\ModelCache::store($this);
        }
        $this->__saving=false;
    }
    private function nuke()
    {
        // Se destruye de la cache
        \lib\Model\ModelCache::clear($this);
        $this->__new=true;
        $this->__fields=array();
        $this->__isDirty=false;
        $this->__dirtyFields=array();
    }
    private function mustSelfNuke()
    {
        foreach($this->__fieldDef as $key=>$value)
        {
            if(isset($value["DELETE_ON_NULL"]) && $value["DELETE_ON_NULL"])
            {
                $isIt=$this->__getField($key)->is_set();
                if(!$isIt)
                    return true;
            }
        }
        return false;
    }

    static function getModelInstance($objectName, $serializer = null, $definition = null)
    {        
        $objName = new \lib\reflection\model\ObjectDefinition($objectName);
        $objName->includeDefinition();
        $objName->includeModel();        
        $namespacedName=$objName->getNamespaced();
        $obj=new $namespacedName($serializer,$definition);        
        return $obj;
    }
    static function getModel($objectName,$fields=null)
    {
        $instance=BaseModel::getModelInstance($objectName);
        // La inicializacion del objeto puede hacer que se acceda a campos.No queremos esto.
        $instance->__fields=array();
        $instance->__key = new ModelKey($instance, $instance->__objectDef);
        if($fields)
        {
            foreach($fields as $key => $value)
                $instance->{$key}=$value;
            $instance->loadFromFields();
        }
        return $instance;
    }

    static function getTableName($objectName, $def)
    {

        if ($def["TABLE"])
            return $def["TABLE"];
        $objDef = new \lib\reflection\model\ObjectDefinition($objectName);
        return $objDef->className;
    }

    function __saveMembers($serializer)
    {        
        $dFields = array();
        // Se establece este modelo en el contexto global, para que sea accedido por las columnas
        // de este mismo modelo, que requiren acceder a el, desde los tipos de dato.
        // El nombre del objeto en el contexto es "currentModel"
        global $globalContext;
        $curSaved=$globalContext->currentModel; // Se guarda el valor actual, en caso de que estemos en un guardado en cadena.
        $globalContext->currentModel=$this;
        // se tienen que guardar todos, ya que puede haber valores por defecto.
        $fields=$this->__getFields();
        $aliasFields=array();
       //$this->__dirtyFields=array();
        foreach ($fields as $key => $value)
        {

            if (!$this->__dirtyFields[$key] && !$value->isAlias())
                $value->save();
            else
            {

                if($this->__new)
                {
                    // Si este elemento era nuevo, y tenemos alias que nos apuntan (mas especificamente, relaciones inversas), y estan sucias,
                    // tenemos que guardarnos primero nosotros, y luego las relaciones inversas.
                    // Esto es: A es nuevo.Accedemos a un B a traves de una relacion inversa de A.
                    // Esto significa que B tiene una relacion con A.Pero como A es nuevo, aun no tiene INDEX.Hay que esperar a que A se guarde ($this->__new==false) para
                    // guardar B

                    if($value->isAlias() && $value->isDirty())
                    {
                        $aliasFields[$key]=1;
                        continue;
                    }
                    //$type=$value->getType();

                    // Si es un tipo de dato (sobre todo, imagen), que para calcular su valor, puede
                    // requerir que el resto de los campos de este modelo ya tengan valor (especialmente, los
                    // ids autogenerados), se deja como dirty, y no se le pasa al serializador.
                    // Asi, el metodo save(), vera que aun quedan dirtyFields, y volvera a llamar a este metodo,
                    // guardandose asi el fichero , en la segunda llamada.
                    if($value->requiresUpdateOnNew())
                    {
                        continue;
                    }
                }
                $value->save();


                // Fields that require some kind of saving hare handled here.
                // Examples of this kind of fields are:
                // Relationships that may have pending saves on their remote objects
                // Files,etc.

                if (array_key_exists($key, $this->__fieldDef))
                    $dFields[$key] = $value;
            }
            unset($this->__dirtyFields[$key]);
        }
        $isNew = $this->__isNew();        
        if (count($dFields) > 0 || $isNew)
        {
            // Guardamos el estado del objeto.
            $this->__saveState();
            $serializer->_store($this, $isNew, $dFields);
        }
        foreach($aliasFields as $key=>$val)
            $this->__dirtyFields[$key]=$val;

        // Una vez que el modelo ya esta en el contexto global, se pueden guardar las columnas.
        foreach ($this->__fields as $key => $value)
            $value->onModelSaved();
        $this->__new = false;
        //$this->cleanDirtyFields();
        $this->__isDirty=(count(array_keys($this->__dirtyFields))>0);
        // Se recupera el valor antiguo para el valor del contexto del modelo guardado
        $globalContext->currentModel=$curSaved;
    }

    function getIndexes()
    {
        if ($this->__key)
            return $this->__key;
        return null;
    }

    function getDefaultPermissions()
    {
        $perms = $this->__objectDef["DEFAULT_PERMISSIONS"];
        return $perms ? $perms : null;
    }

    function getOwner()
    {

        if ($this->__objectDef["OWNERSHIP"])
            return $this->getPath($this->__objectDef["OWNERSHIP"],$this);
        return null;
    }

    function is_equal_to(& $model)
    {
        if (!$this->isLoaded() || !$model->isLoaded())
        {
            return false;
        }
        if ($this->__objName != $model->__objName)
            return false;

        foreach ($this->__fieldDef as $key => $value)
        {
            $curField = $this->__getField($key);
            $remField = $model->__getField($key);
            if ($curField->equals($remField)
                    && !in_array($key, (array) $this->__objectDef["INDEXFIELDS"]))
            {
                // Solo se permite que sea distinta la primary key.
                return false;
            }
        }
        return true;
    }

    function __getSerializer($op="READ")
    {
        if($op=="READ")
        {
            if($this->__serializer)
                return $this->__serializer;

            if(isset($this->__objectDef["DEFAULT_SERIALIZER"]))
                $this->__serializer = \lib\storage\StorageFactory::getSerializerByName($this->__objectDef["DEFAULT_SERIALIZER"]);
            else
            {
                $this->__serializer= \lib\storage\StorageFactory::getSerializerByName(DEFAULT_SERIALIZER);
            }

            if (!$this->__serializer)
                throw new BaseModelException(BaseModelException::ERR_NO_SERIALIZER);
            return $this->__serializer;
        }

        if($this->__writeSerializer)
              return $this->__writeSerializer;
         if(isset($this->__objectDef["DEFAULT_WRITE_SERIALIZER"]))
                $this->__writeSerializer = \lib\storage\StorageFactory::getSerializerByName($this->__objectDef["DEFAULT_WRITE_SERIALIZER"]);
            else
                $this->__writeSerializer = $this->__getSerializer();

        return $this->__writeSerializer;
    }
    function __setSerializerFilters($serType,$data)
    {
        $this->__filters[$serType]=$data;
    }
    function __getSerializerFilters($serType,$data)
    {
        return $this->__filters[$serType];
    }
    // Esta funcion existe ya que los MultipleModels pueden necesitarla.
    function __allowRelay($allow)
    {
        $this->__relayAllowed=$allow;
    }
    function __getAliasPointingTo($model,$field)
    {        
        foreach($this->__aliasDef as $key=>$value)
        {
            if(isset($value["OBJECT"]) && isset($value["FIELD"]))
            {
                $parts=explode('\\',$value["OBJECT"]);
                if(array_pop($parts)==$model && $field==$value["FIELD"])
                    return $key;
            }
        }
        return null;
    }
    function __toString()
    {

        return "[ ".get_class($this)." ( ".$this->__key->__toString().") ]";
    }
            
}

