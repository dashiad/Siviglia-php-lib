<?php
 namespace lib\model;
 class RelationMxN extends InverseRelation1x1
 {        
    protected $relationModelName;
    protected $remoteModelName;
    protected $relationModelDefinition;
    protected $interTableName;
    protected $relationFields;
    protected $relationModelInstance;
    protected $relationModelMapping;
    protected $localModelMapping;
    protected $remoteModelMapping;
    protected $relationIndexType;    
    protected $relationModelIndexes;
    protected $uniqueRelations;
    function __construct($name,& $model, $definition, $value=null)
    {
        $this->relationModelName=new \lib\reflection\model\ObjectDefinition($definition["OBJECT"]);
        $this->remoteModelName=new \lib\reflection\model\ObjectDefinition($definition["REMOTE_MODEL"]);
        // Se necesita la definicion del objeto relacion.
        $this->uniqueRelations=isset($definition["RELATIONS_ARE_UNIQUE"])?$definition["RELATIONS_ARE_UNIQUE"]:false;
        $this->relationModelInstance=\lib\model\BaseModel::getModelInstance($definition["OBJECT"]);
        $this->remoteTable=$this->relationModelInstance->__getTableName();
        $rMD=$this->relationModelInstance->getDefinition();
        $this->relationFields=$rMD["MULTIPLE_RELATION"]["FIELDS"];
        foreach($this->relationFields as $value)
        {
            $f=$rMD["FIELDS"][$value]["OBJECT"];
            $ff=array_values($rMD["FIELDS"][$value]["FIELDS"]);
            if($model->__getObjectNameObj()->equals($f))
            {
                 $this->relationModelMapping["local"]=$value;
                 $this->localModelMapping[$ff[0]]=$value;
            }            
            else
            {
                $relationModelMapping["remote"]=$f;
                $this->remoteModelMapping[$ff[0]]=$value;
            }
        }
        $indexes=$rMD["INDEXFIELDS"];
        $this->relationModelIndexes=$indexes;
        $this->relationIndexType=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($this->relationModelName,$indexes[0]);
        parent::__construct($name,$model,$definition,$value);

    }
    function createRelationValues()
    {
        return new MultipleRelationValues($this,$this->definition["LOAD"]?$this->definition["LOAD"]:"LAZY");
    }

    function getRelationModelName()
    {
        return $this->relationModelName;
    }
    
    function getRemoteModelName()
    {
        return $this->remoteModelName;
    }
    
    function getRelationModelMapping()
    {
        return $this->relationModelMapping;
    }
    // Keys son los campos locales.Valores son los campos de la tabla relacion.
    function getLocalMapping()
    {
        return $this->localModelMapping;
    }
    // Keys son los campos remotos.Valores son los campos de la tabla relacion.
    function getRemoteMapping()
    {
        return $this->remoteModelMapping;
    }
    function getRelationIndexes()
    {
        return $this->relationModelIndexes;
    }
    // Devuelve el tipo del campo indice de la tabla relacion.
    function getRelationIndexType()
    {
        return $this->relationIndexType;
    }
    function getRelationModelInstance()
    {
        return $this->relationModelInstance;
    }
    function onModelSaved()
    {
        $this->relation->cleanState();        
    }
    function relationsAreUnique()
    {
        return $this->uniqueRelations;
    }
    function delete($value)
    {
        $this->relationValues->delete($value);
        $this->relationValues->reset();
    }
    function add($value)
    {
        $this->relationValues->add($value);
        $this->relationValues->reset();
    }
    function set($values,$extra=null)
    {
        $this->relationValues->set($values,$extra);
        $this->relationValues->reset();
    }
}

class MultipleRelationValues extends RelationValues
{
    function delete($value)
    {        
        // TODO: Optimizar para hacer el minimo numero de queries posibles.
        $type=$this->recognizeType($value);
        
        if(!is_array($type))
        {
            $type=array($type);
            $value=array($value);
        }
        $relInstance=$this->relField->getRelationModelInstance();
        $serializer=$relInstance->__getSerializer();

        for($k=0;$k<count($type);$k++)
        {
            switch($type[$k])
            {
            case "remote":
                {
                    // Tengamos A y B, relacionadas por C.
                    // Se nos pasa a borrar, una instancia de B.
                    // Por lo tanto, no tenemos los campos indice de C.Lo que podemos tener son los campos de C que relacionan A con B.
                    // Esto significa que se borran *todas* las relaciones entre A y B que existen en C.                    
                    $remFields=$this->relField->getRemoteMapping();
                    foreach($remFields as $key2=>$value2)
                    {
                        $field=$value[$k]->__getField($key2);
                        // TODO : Otro problema con formatos.
                        // A veces, serialize devuelve simplemente un valor,
                        // otras veces, un array asociativo tipo clave=>valor...
                        $serialized=$field->serialize($serializer);
                        if(is_array($serialized))
                        {
                            foreach($serialized as $fName=>$fval)
                                $deleteKeys[$k][$fName]=$fval;
                        }
                        else
                            $deleteKeys[$k][$key2]=$field->serialize($serializer);
                    }
                    $locFields=$this->relField->getLocalMapping();
                    foreach($locFields as $key2=>$value2)
                    {
                        $serialized=$this->relField->serialize($serializer);
                        if(is_array($serialized))
                        {
                            foreach($serialized as $fName=>$fval)
                                $deleteKeys[$k][$fName]=$fval;
                        }
                        else
                            $deleteKeys[$k][$key2]=$serialized;
                    }
                }break;
            case "relation":
                {
                    // Tengamos A y B, relacionadas por C.
                    // Se nos pasa a borrar una instancia de C.
                    // Por lo tanto, eliminamos solo esa relacion entre A y C.
                    $index=$value[$k]->getIndexes()->getKeyNames();
                    $firstIndex=$index[0];
                    $deleteKeys[$k][$firstIndex]=$value[$k]->__getField($firstIndex)->serialize($serializer);                    
                }break;
            case "value":
                {
                    $instance=$this->relField->getRelationModelInstance();
                    $index=$instance->getIndexes()->getKeyNames();
                    $firstIndex=$index[0];
                    $instance->{$firstIndex}=$value[$k];
                    $deleteKeys[$k][$firstIndex]=$instance->__getField($firstIndex)->serialize($serializer);
                }break;
            }
        }
        $serializer->delete($this->relField->getRelationModelInstance()->getTableName(),$deleteKeys);
    }


    function add($value)
    {
        // TODO : Optimizar para hacer el minimo numero de queries posibles.
        // Al aniadir, hay 2 casos: que el objeto relacion sea nuevo, o que ya exista.
        // Que ya exista es muy raro, pero por 
        $relInstance=$this->relField->getRelationModelInstance();
        $serializer=$relInstance->__getSerializer();
        $uniques=$this->relField->relationsAreUnique();
         $type=$this->recognizeType($value);
        
        if(!is_array($type))
        {
            $type=array($type);
            $value=array($value);
        }
        for($k=0;$k<count($type);$k++)
        {
            switch($type[$k])
            {
            case "remote":
                {
                    // Relacion A con B a traves de C. Nos han pasado un B.Hay por tanto que crear una instancia de C, y asignar campos.
                    // Si el objeto a relacionar (B) esta sucio, hay que guardarlo.
                    if($value[$k]->isDirty())
                        $value[$k]->save();
                    
                    $newInstance=\lib\model\BaseModel::getModelInstance($this->relField->getRelationModelName());                    
                    // Por lo tanto, no tenemos los campos indice de C.Lo que podemos tener son los campos de C que relacionan A con B.
                    // Esto significa que se borran *todas* las relaciones entre A y B que existen en C.                    
                    $remFields=$this->relField->getRemoteMapping();
                    foreach($remFields as $key2=>$value2)                    
                        $newInstance->{$value2}=$value[$k]->{$key2};                                        
                    $locFields=$this->relField->getLocalMapping();
                    foreach($locFields as $key2=>$value2)                    
                        $newInstance->{$value2}=$this->relField->getModel()->{$key2};
                    $newInstance->save();
                }break;
            case "relation":
                {
                    // Se ha recibido una instancia de la relacion.Hay que asignar el campo que apunta a este objeto.
                    $locFields=$this->relField->getLocalMapping();
                    foreach($locFields as $key2=>$value2)                    
                        $value[$k]->{$value2}=$this->relField->getModel()->{$key2};
                    $value[$k]->save();
                }break;
            case "value":
                {
                    $instance=\lib\model\BaseModel::getModelInstance($this->relField->getRelationModelName);
                    $instance->setId($value[$k]);
                    $instance->unserialize();
                    $locFields=$this->relField->getLocalMapping();
                    foreach($locFields as $key2=>$value2)                    
                        $value[$k]->{$value2}=$this->relField->getModel()->{$key2};
                    $value[$k]->save();
                }break;
            }
        }
           
    }
    function recognizeType($value,$allowArray=true)
    {
        if(is_object($value))
        {
            $type=get_class($value);
            $relName=$this->relField->getRemoteModelName()->getNamespaced();
            // El valor es una instancia del objeto relacion.Lo que necesitamos son sus campos indices.
            if($type==substr($relName,1))
            {
                return "remote";
            }
            $remName=$this->relField->getRelationModelName()->getNamespaced(); 
            if($type==substr($remName,1))
            {
                return "relation";
            }            
        }
        if(is_array($value))
        {
            if($allowArray==false)
            {
                // TODO: lanzar excepcion.
                return;
            }
            foreach($value as $p)
            {
                $results[]=$this->recognizeType($p,false);
            }
            return $results;
        }
        return "value";                
    }
    // $extra son campos extra (ademas de los exclusivamente referidos a la relacion) que hay que asignar a los objetos de
    // relacion.
    function set($srcValues,$extra=null)
    {
        
        // Set solo tiene sentido en caso de que las relaciones sean unicas.
        // Ademas, set tiene que estar expresado en terminos de los campos relacionados, no segun los id's del modelo
        // intermedio.
        // Por lo tanto, los campos van a ser un array de id's de la tabla remota.
        // Esto a su vez tiene el problema de que si las tablas relacion tienen que ser manualmente definidas, y tienen un id
        // propio, y ese id no es autonumerico (ej, UUID), hay que generarlo tambien, para aquellos elementos que haya que crear.
        // Porque ya no podemos borrar todo lo existente, y luego insertar lo que nos haya llegado, 
        // ya que si hay (por algun motivo) campos propios, habriamos perdido sus valores.Hay que hacer primero un delete where not in
        // y un insert de las nuevas relaciones.

        // Asi que, primero obtenemos el tipo de indice de la tabla relacion.
        $relInstance=$this->relField->getRelationModelInstance();
        $serializer=$relInstance->__getSerializer();
        $def=$relInstance->getDefinition();
        $index=$def["INDEXFIELDS"][0];
        $type=\lib\model\types\TypeFactory::getType($relInstance,$def["FIELDS"][$index]);

        // se obtiene el valor serializado de esta relacion.Este valor va a ser siempre fijo.
        $local=$this->relField->getLocalMapping();

        $localKeys=array_keys($local);
        $localMap=$local[$localKeys[0]];
        $curValueSer=$this->relField->serialize($serializer);
        
        $curValue=$curValueSer[$localMap]=$curValueSer[$localKeys[0]];

        // Se crea un array de serializadores, para el resto de los campos.
        // Primero, para el indice remoto.
        $serType=$serializer->getSerializerType();
        $remoteMapping=$this->relField->getRemoteMapping();
        $remoteName=$this->relField->getRemoteModelName();
        foreach($remoteMapping as $key=>$value)
        {
            $types[$value]=\lib\model\types\TypeFactory::getFieldTypeInstance($remoteName,$key);
            $serializers[$value]=\lib\model\types\TypeFactory::getSerializer($types[$value],$serType);
        }
        $relationName=$this->relField->getRelationModelName();
        if($extra!=null && is_array($extra))
        {
            $extraIsAssoc=false;
            // Se mira si los campos extra son un array asociativo, o normal.
            // Si es un array asociativo, lo que haya en el, sera reutilizado para todos los registros que se hayan pasado en $values.
            if(\lib\php\ArrayTools::isAssociative($extra))
            {
                $serRow=$extra;
                $extraIsAssoc=true;
            }
            else
            {
                $serRow=$extra[0];
            }
            foreach($serRow as $key=>$value)
            {
                $types[$key]=\lib\model\types\TypeFactory::getFieldTypeInstance($relationName,$key);
                $serializers[$key]=\lib\model\types\TypeFactory::getSerializer($types[$key],$serType);
            }
            // Si es asociativo, lo serializamos ahora.
            if($extraIsAssoc)
            {
                foreach($extra as $eKey=>$eValue)
                {
                    $types[$eKey]->__rawSet($eValue);
                    $eVals[$eKey]=$serializers[$eKey]->serialize($types[$eKey]);
                }
            }
        }        
        $isUUID=false;
        // Vemos si es un UUID.
        if($type->getFlags() & \lib\model\types\BaseType::TYPE_SET_ON_ACCESS) 
        {
            $isUUID=true;
            $uuidSerializer=new \lib\model\types\UUIDMYSQLSerializer();
        }

        $nVals=count($srcValues);        
        for($k=0;$k<$nVals;$k++)
        {
            $curVal=$srcValues[$k];
            $results[$localMap][]=$curValue;
            // En su caso, se introduce el UUID
            if($isUUID)
            {
                $newUUID=new \lib\model\types\UUID($type->getDefinition());
                $results[$index][]=$uuidSerializer->serialize($newUUID);
            }
            foreach($remoteMapping as $key=>$value)
            {
                $types[$value]->__rawSet($curVal[$value]);
                $results[$value][]=$serializers[$value]->serialize($types[$value]);
            }
            if($extra!=null)
            {
                if($extraIsAssoc)
                {
                    foreach($extra as $eKey=>$eValue)
                    {
                        $results[$eKey][]=$eVals[$eKey];
                    }
                }
                else
                {
                    $curExtra=$extra[$k];
                    foreach($curExtra as $eKey=>$eValue)
                    {
                        $types[$eKey]->__rawSet($eValue);
                        $results[$eKey][]=$serializers[$eKey]->serialize($types[$eKey]);
                    }
                }
            }
        }
        $serializer->setRelation($this->relField->getRelationModelInstance()->__getTableName(),
                                 array($localMap=>$curValue),
                                 array_values($remoteMapping),
                                 $results
                                 );        
     /*   DELETE FROM xx WHERE id NOT IN (SELECT id FROM zz WHERE a=b AND c IN (.....))
        1) T*/
    }
}



?>
