<?php namespace lib\model;
class RelationMxN extends ModelBaseRelation
{
    protected $hasOwnTable=false;
    protected $interTable=null;
    protected $remoteRelations=null;
    protected $prefix;
    protected $origDefinition;
    protected $targetObject;
    protected $remoteDefinition;
    protected $localFields;
    protected $relationTableRemoteFields;
	// Normaliza el formato de relacion de campos.
	// El formato es <campo remoto> => <campo local>

    function __construct($name,& $model, $definition, $value=null,$origDefinition=null)
	{   

        if($definition["TABLE"])
        {
            $this->interTable=$definition["TABLE"];
            $definition=$this->setupInterTable($name,$model,$definition,$origDefinition);        
            $this->hasOwnTable=true;
            $fields=$this->definition["FIELDS"];            
        }
        else
        {
            if($definition["RELATION_MODEL"])
            {                
                $origDefinition=$definition;
                $definition=$this->parseFromModel($name,$model,$definition);                
                $fields=$this->localFields;
            }
        }
        foreach($fields as $key=>$value)
        {
            $cFields[$key]=$model->{$key};            
        }
        $this->origDefinition=$origDefinition;        
        ModelBaseRelation::__construct($name,$model,$definition,null);
        $this->relation->subSet($cFields);
    }
    function getPrefix()
    {
        return $this->prefix;
    }

    function parseFromModel($name,$model,$def)
    {
        
            $remObject=new \lib\reflection\model\ObjectDefinition($def["OBJECT"]);
            $this->targetObject=$def["OBJECT"];
            $relObject=\lib\model\BaseModel::getModelInstance($def["RELATION_MODEL"]);
            $this->interTable=$relObject->__getTableName();
            $relDef=$relObject->getDefinition();
            $relfields=$relDef["MULTIPLE_RELATION"]["FIELDS"];
            $defFields=array();
            
            foreach($relfields as $value)
            {
                $relFieldDef=$relDef["FIELDS"][$value];
                $pointedField=$relFieldDef["FIELDS"][$value];
                if($model->__getObjectNameObj()->equals($relFieldDef["OBJECT"]))
                {
                    $this->localFieldNames[]=$pointedField;
                    $this->localFields[$pointedField]=$value;
                    $def["FIELDS"]["LOCAL"][]=$pointedField;
                    $srcFields[$pointedField]=$value;
                    $defFields[$value]=$relDef["FIELDS"][$value];

                }
                else
                {
                    $this->remoteFieldNames[]=$pointedField;
                    // TODO : Aqui hay muchos datos repetidos..
                    $this->remoteRelations[$pointedField]=$value;
                    $this->relationTableRemoteFields[$pointedField]=$value;
                    $def["FIELDS"]["REMOTE"][]=$pointedField;
                    $defFields["remote"]=$relDef["FIELDS"][$value];
                    // Se obtiene la tabla remota.
                    $remmodel=\lib\model\BaseModel::getModelInstance($relDef["FIELDS"][$value]["OBJECT"]);
                    $this->remoteTable=$remmodel->__getTableName();
                }
            }    
            
            // Se copia la definicion del id de la tabla intermedia, a nuestra definicion.
            $interTableId=$relDef["INDEXFIELDS"][0];            
            $defFields[$interTableId]=$relDef["FIELDS"][$interTableId];
        $this->remoteDefinition=array(
                    
                    "TABLE"=>$this->interTable,
                    "INDEXFIELDS"=>$relDef["INDEXFIELDS"],
                    "FIELDS"=>$defFields
                );

        $newDef=array(
                      "OBJECT"=>$def["OBJECT"],
                      "TYPE"=>"Relationship",
                      "TABLE"=>$this->interTable,
                      "FIELDS"=>$srcFields,
                      "REMOTEDEF"=>$this->remoteDefinition
                     );     
        return $newDef;   
    }
    function setupInterTable($name,$model,$def,$origDef)
    {
        // Se miran los campos involucrados.
        // Si existen los campos FIELDS[LOCAL] o FIELDS[REMOTE], se usan esos campos para la tabla intermedia.
        // Si no existe alguno de ellos, se usan las claves del modelo local o remoto.
        if($origDef!=null)
            $targetObject=$origDef["OBJECT"];
        else
            $targetObject=$def["OBJECT"];
        
        $this->targetObject=$targetObject; 
        $remObject=new \lib\reflection\model\ObjectDefinition($targetObject);
        
        $remClassName=$remObject->className;
        if($remClassName > $model->__getObjectName())
        {
            $localP="t1";
            $remoteP="t2";
        }
        else
        {
            $localP="t2";
            $remoteP="t1";
        }
        $this->prefix=$localP;

        $localTemp=$def["FIELDS"]["LOCAL"];
        
        $localDefTemp=$model->getDefinition();

        if(!$localTemp)
            $localTemp=$localDefTemp["INDEXFIELDS"];

        $remoteTemp=$def["FIELDS"]["REMOTE"];
        $remoteDefTemp=\lib\model\types\TypeFactory::getObjectDefinition($targetObject);
        

        if(!$remoteTemp)
        {
            $remoteTemp=$remoteDefTemp["INDEXFIELDS"];
        }
        
        if(!$isInverse)
        {
            $local=& $localTemp;
            $localDef=& $localDefTemp;
            $remote=& $remoteTemp;
            $remoteDef=& $remoteDefTemp;
        }
        else
        {
            $local=& $remoteTemp;
            $localDef=& $remoteDefTemp;
            $remote=& $localTemp;
            $remoteDef=& $localDefTemp;
        }
        $this->remoteFields=$remoteDef;

        $this->remoteTable=BaseModel::getTableName($targetObject,$remoteDef);
        
        foreach($local as $key=>$value)
        {
            $fieldName=$localP.$value;
            $fields[$fieldName]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($model->__getFullObjectName(),$value)->getDefinition();
            $localFields[$value]=$fieldName;
        }
        $this->localFields=$localFields;
        foreach($remote as $key=>$value)
        {
            $fieldName=$remoteP.$value;
                        
            //$fields[$fieldName]=array("TYPE"=>"Relationship","OBJECT"=>$def["OBJECT"],"FIELDS"=>array($fieldName=>$value));
            $fields["remote"]=array("TYPE"=>"Relationship","OBJECT"=>$targetObject,"FIELDS"=>array($fieldName=>$value));
            $this->remoteRelations[$fieldName]=$value;
            //$remoteFields[]=$fieldName;
            $remoteFields[]="remote";
        }            
        // Primero, hay que ver si las relaciones son unicas o no.Es decir,
        // si dadas las tablas A(a1) y B(b1), la interTable seria A_B(a1,b1).
        // Hay que ver si esas relaciones son unicas o no.
        // En caso de que no lo sean, hay que aniadir una clave unica a la tabla A_B,
        // para identificar univocamente a los objetos.
        if($def["RELATIONS_ARE_UNIQUE"])        
        {
            $indexes=array_merge(array_values($localFields),$remoteFields); 
        }
        else
        {
            $indexField=$def["TABLE"]."idx";
            $indexes=array(str_replace("_","",$indexField));
            $fields[$indexField]=array("TYPE"=>"UUID");

        }        
        $this->remoteDefinition=array(
                    "TABLE"=>$def["TABLE"],
                    "INDEXFIELDS"=>$indexes,
                    "FIELDS"=>$fields
                );

        $newDef=array(
                      "TYPE"=>"Relationship",
                      "TABLE"=>$def["TABLE"],
                      "FIELDS"=>$localFields,
                      "REMOTEDEF"=>$this->remoteDefinition
                     );
        if( $def["LOAD"] )
        {
            $newDef["LOAD"]=$def["LOAD"];
        }
        $this->localFields=$localFields;
        $this->remoteFields=$remoteFields;        
        return $newDef;
    }
    function copyField($type)
    {        
        $this->model->{$this->name}=$type->getValue(); 
        $this->setDirty();            
     }


    function createRelationValues()
    {
        return new RelationValues($this,$this->definition["LOAD"]?$this->definition["LOAD"]:"LAZY");
    }
    
    function createRelationFields()
    {
        return new MultipleRelationFields($this,$this->definition);
    }
    function createRemoteInstance()
    {
/*            if($this->origDefinition["RELATION_MODEL"])
            {                
                return \lib\model\BaseModel::getModelInstance($this->origDefinition["RELATION_MODEL"]);
            }
            else
            {*/
                if($this->hasOwnTable)
                    return new RelationInstance($this,$this->model->__getSerializer(),$this->remoteDefinition);
                else
                    return ModelBaseRelation::createRemoteInstance();
            //}
    }

	

    function get()
    {        
        return $this;
    }
    function __get($varName)
    {
        return $this->relationValues[0]->{$varName};
    }
	
	function save()
	{
        $nSaved=$this->relationValues->save();
        //exit();
        /*if($nSaved==1)
            $this->relation->setFromModel($this->relationValues[0]); */
	}	

	function count()
    {
        return $this->relationValues->count();
    }

	function loadCount()
	{
        
        if($this->relationValues->isLoaded())
        {
            return $this->relationValues->count();
        }
        if($this->relation->state==ModelBaseRelation::UN_SET)
        {
            return 0;
        }        
        if($this->definition["LOAD"]=="LAZY")   
        {
            
            $this->relationValues->setCount($this->getSerializer()->count($this->getRelationQueryConditions(),$this->model));
        }
        
        else
        {
            $this->loadRemote();
        }
	}	

	function __toString()
	{
       return $this->relation->__toString();
	}

    function onModelSaved()
    {
        $this->relation->cleanState();        
    }
    

    function getReverseTableQuery()
    {        
        
         $q=$this->getRemoteTableQuery();
         $serializer=$this->getSerializer();
         $serType=$serializer->getSerializerType();
         $q["BASE"]="SELECT rem.*,rel.* FROM ".$this->remoteTable." rem LEFT JOIN ".$this->interTable." rel ON ";
         
         $leftconds=array();
         foreach($this->remoteRelations as $key=>$value)
             $leftconds[]=$key."=".$value;
         foreach($this->localFields as $key=>$value)
             $leftconds[]=$value."=".$this->model->{$key};
         
         $q["BASE"].=implode(" AND ",$leftconds);

         return $q;        
    }
    function getInverseDatasource()
    {
        $modelName=$this->model->__getObjectName();
       
        $dsName="Not".ucfirst($this->name).ucfirst($this->origDefinition["FIELD"]);
        $ds= \lib\datasource\DataSourceFactory::getDataSource($modelName,
                                                         $dsName,
                                                         $this->model->__getSerializer());
        $ds->setParameters($this->model);
        return $ds;
    }
    function getRelationQueryConditions($dontUseIndexes=false)
    {        
        if( $this->interTable)
        {                
            if( $this->definition["LOAD"]!="LAZY" )
            {
                $q=$this->getRemoteTableQuery();
                $serializer=$this->getSerializer();
                $serType=$serializer->getSerializerType();

                $q["BASE"]="SELECT rem.*,rel.* FROM ".$this->interTable." rel $joinType JOIN ".$this->remoteTable." rem ON ";
                $subRels=array();
                foreach($this->remoteRelations as $key=>$value)
                    $subRels[]=$key."=".$value."";
                $q["BASE"].=implode(" AND ",$subRels);
                $this->relation->getQueryConditions($q,$serType);
                return $q;
            }
        }
        return ModelBaseRelation::getRelationQueryConditions($dontUseIndexes);        
    }

    function delete($value)
    {
        $this->relation->delete($value);
        $this->relationValues->reset();
    }

    function add($value)
    {
        $this->relation->add($value);
        $this->relationValues->reset();
    }

    function set($values)
    {
        $this->relation->set($values);
        $this->relationValues->reset();
    }
    function getLocalValues()
    {
        $def=$this->definition;
        if(isset($def["FIELDS"]["LOCAL"]))
            $fields=$def["FIELDS"]["LOCAL"];
        else
        {
            $fields=$this->model->__getKeys()->getKeyNames();
        }
        foreach($fields as $curField)
            $results[$curField]=$this->model->{$curField};
            
        return $results;
    }
    function getLocalFields()
    {
        return $this->localFields;
    }
    function getRemoteFields()
    {
        return $this->remoteFields;
    }
    function getRemoteModelName()
    {
        return $this->targetObject;
    }    
}

class RelationInstance extends BaseModel{

    var $remInstance;
    var $parentRelation;
    var $isLazy;
    var $remote;
    

    function __construct($parentRelation,$serializer)
    {        
        $this->parentRelation=$parentRelation;
        $definition=$this->parentRelation->getDefinition();
        $this->isLazy=($definition["LOAD"]=="LAZY");
        BaseModel::__construct($serializer,$definition["REMOTEDEF"]);
    }

    function loadFromArray($data,$serializer)
    {        
        BaseModel::loadFromArray($data,$serializer);
        if( !$this->isLazy )
        {            
            $this->remote=BaseModel::getModelInstance($this->__objectDef["FIELDS"]["remote"]["OBJECT"]);
            $this->remote->loadFromArray($data,$serializer);
            $this->__fields["remote"]->getRelationValues()->load(array($this->remote),1);
        }        
    }
    function __get($varName)
    {                
        $val=$this->__fields["remote"][0]->{$varName};
        return $val;
    }
    function __set($varName,$value)
    {
        
        $this->__fields["remote"][0]->{$varName}=$value;
        $this->setDirty($this->__fields["remote"]->isDirty());        
    }  
    function isDirty()
    {                
        return $this->__fields["remote"][0]->isDirty();
    }
    function save()
    {
        $this->setDirty(false);
        $this->__fields["remote"]->relationValues->save();      
    }
    
    function getRemote()
    {
        return $this->__fields["remote"][0];
    }
}


class MultipleRelationFields extends RelationFields
{
    function __construct($relObject,$definition)
    {                       
        RelationFields::__construct($relObject,$definition);
        $keys=array_keys($this->types);
        $this->localIndexField=$keys[0];        
    }
    function load($rawModelData)
    {
    
        RelationFields::load($rawModelData);      
    }
    function subSet($data)
    {
        return RelationFields::set($data);
    }

    function getIndexesFromValues($value)
    {                
        $remFields=$this->definition["REMOTEDEF"]["FIELDS"]["remote"]["FIELDS"];        
        $serializer=$this->relObject->getSerializer();
        $serType=$serializer->getSerializerType();

        if( is_int($value) )
        {
            $value=$this->relObject->relationValues[$value]->getRemote(); 
        }
        
        if( !is_array($value) || \lib\php\ArrayTools::isAssociative($value))
            $value=array($value);

        $nVals=count($value);
        // Se hace que la key sea el campo en la tabla remota, y el value, sea el campo en la tabla relacion,
        // para hacer la conversion indicada mas abajo.
        $invertedRemote=array_flip($remFields);
        for($k=0;$k<$nVals;$k++)
        {
            $curVal=$value[$k];
            if( is_object($curVal) )            
            {
                //if($curVal->isDirty())
                    $curVal->save($this->relObject->getSerializer());

                $curFields=array();
                // Ojo a esto..Esto significa que, si dos tablas A y B, con claves Akey y Bkey, estan relacionadas usando
                // la tabla AyB, con clave AyBkeyA + AyBkeyB, cuando se asigna un valor a esta relacion multiple, no se usa
                // array("AyBKeyA"=>1) (campo que existe en la tabla relacion), sino array("Akey"=>1), campo de A.De esta
                // forma, los campos existentes en la tabla intermedia, son transparentes.
                // Por eso se usa el $value de $remFields, en vez del $key
                foreach($remFields as $key=>$value)
                {
                    $curFields[$value]=\lib\model\types\TypeFactory::serializeType($curVal->__getField($value)->getType(),$serType);
                }                
                $results[]=$curFields;
            }
            else
            {
                if(is_array($curVal))
                {
                    foreach($invertedRemote as $ikey=>$ivalue)
                    {
                        // Para los elementos del array con key el nombre de los campos remotos, se cambia por el nombre
                        // de los campos de la tabla relacion.
                        $curVal[$ivalue]=$curVal[$ikey];
                        unset($curVal[$ikey]);
                    }
                    $results[]=$curVal; 
                }
            }
        }
        return $results;
    }
    function getLocalIndexes(& $indexes)
    {
        
        $model=$this->relObject->getModel();        
        $field=$model->__getField($this->localIndexField);
        $types=$field->getTypes();
                
        foreach($this->types as $key=>$value)
        {
           $subTypes=$model->__getField($key)->getTypes();
           foreach($subTypes as $key2=>$value2)
           {
               $val=$value2->getValue();
               foreach($indexes as $ikey=>$ivalue)
               {
                   $indexes[$ikey][$this->definition["FIELDS"][$key]]=$val;
               }
           }
        }                        
    }

    function prepareCUDop($value)
    {
       // if($this->state!=ModelBaseRelation::SET)
       //     return; //TODO : lanzar excepcion
        $indexes=$this->getIndexesFromValues($value);
        $this->getLocalIndexes($indexes);
        return $indexes;
    }

    function delete($value)
    {
        $remoteIndexes=$this->prepareCUDop($value);
        $ser=$this->relObject->getSerializer();
        $ser->delete($this->definition["TABLE"],$remoteIndexes);
    }

    function add($value)
    {
        $remoteIndexes=$this->prepareCUDop($value);
        $ser=$this->relObject->getSerializer();
        $ser->add($this->definition["TABLE"],$remoteIndexes);
        
    }


    function set($values)
    {                
        $lIndex=array(array());
        $this->getLocalIndexes($lIndex);
        $ser=$this->relObject->getSerializer();
        $ser->delete($this->definition["TABLE"],$lIndex);
        if( !$values )
            return;        
        if(!is_array($values))
            $values=array($values);        
        $remoteIndexes=$this->prepareCUDop($values);
        // Values puede ser un array de ids, o un array de objetos.        
        $ser->add($this->definition["TABLE"],$remoteIndexes);
    }

}

