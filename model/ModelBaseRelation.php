<?php namespace lib\model;
abstract class ModelBaseRelation extends \lib\datasource\DataSource implements \ArrayAccess
{

    const UN_SET=0;
    const SET=1;
    const FUTURE_SET=2;
    const DIRTY=3;
    const PENDING_REMOTE_SAVE=4;

    protected $name;
    protected $model;

    protected $localFields;
    protected $remoteObject;
    protected $remoteTable;
    protected $remoteFields;
    protected $types;
    protected $isAlias=false;
    protected $nFields;
    protected $nResults=null;
    protected $relation;
    protected $definition;
    
    protected $serializerData=array();
    protected $relationValues;
    protected $normalizedName;
	function __construct($name,& $model, & $definition, $value=null)
	{                       
        $this->name=$name;
        $this->normalizedName=str_replace("_","",$name);
        $this->model=& $model;
        $this->definition= $definition;
        $this->remoteObject=$definition["OBJECT"];
        if(!$this->definition["TABLE"] && !$this->definition["REMOTE_MODEL"])
        {
            // Solo se carga la definicion.
            $remoteDef=\lib\model\types\TypeFactory::getObjectDefinition($this->remoteObject);
            if($remoteDef["TABLE"])
                $this->definition["TABLE"]=$remoteDef["TABLE"];
            else
            {
                $parts=explode("\\",$this->remoteObject);
                $this->definition["TABLE"]=$parts[count($parts)-1];
            }
        }
        $this->relation=$this->createRelationFields();
        $this->relationValues=$this->createRelationValues();
        if($value)
        {
            $this->relation->set($value);
        }
	} 
       
    abstract function createRelationValues();

    function createRelationFields()
    {
        return new RelationFields($this,$this->definition);
    }
    function getRemoteObject()
    {
        return $this->remoteObject;
    }
    function getRelation()
    {
        return $this->relation;
    }
    
    function reset()
    {
        $this->relationValues->reset();
        $this->isLoaded=false;
    }
    function getRemoteTable()
    {		
		return $this->remoteTable;
    }
  
    function setAlias($alias)
    {
        $this-> isAlias=$alias;
    }
    function isAlias()
    {
        return $this->isAlias;
    }
    abstract function set($value);
    abstract function get();    
    //abstract function loadRemote();
    abstract function loadCount();

    function getRaw()
    {
        return $this->relation->getRawVal();
    }
    function getValue()
    {
        return $this->getRaw();
    }
    
    function getModel()
    {
        return $this->model;
    }
    function getName()
    {
        return $this->name;
    }

    function cleanState()
    {
        $this->relation->cleanState();
        
    }
    // Implementacion de metodos de DataSource
    function fetchAll()
    {
        $this->loadRemote(null);
        return $this->relationValues;
    }
    function count()
    {
        return $this->relationValues->count();
    }

    function getIterator($rowInfo=null)
    {
        return $this->relationValues;
    }
    function isLoaded()
    {
        return $this->relationValues->isLoaded();
    }
    function countColumns()
    {
        return $this->relationValues->countColumns();
    }
    function getMetaData()
    {
        return $this->relationValues->getMetaData();
    }    

    function isDirty()
    {
        return $this->relation->isDirty() || $this->relationValues->isDirty();        
    }
    function onDirty()
    {
        $this->model->addDirtyField($this->name);
    }

    function setSerializer($ser)
    {
        $this->serializer=$ser;
    }

    function getSerializer()
	{
		return $this->model->__getSerializer();
	}
	
    public function offsetExists($offset)
    {		
        if(!is_numeric($offset))
        {
            return $this->relationValues->count()>0;
        }
        return $this->relationValues->offsetExists($offset);
    }
	
    public function offsetGet( $offset )
    {	
        if(!is_numeric($offset))
        {
            $val=$this->relationValues->offsetGet(0);
            return $val->{$offset};
        }
        return $this->relationValues->offsetGet($offset);
        	
    }
			
    public function offsetSet( $offset , $value )
    {
        if(!is_numeric($offset))
        {
            $val=$this->relationValues->offsetGet(0);
            $val->{$offset}=$value;
            return;
        }
        return false;
    }
    public function offsetUnset($offset)
    {	
		
    }
    function getTypeSerializer($serializerType)
    {
        $results=array();
        foreach($this->types as $key=>$value)
        {
            $results[$key]=\lib\model\types\TypeFactory::getSerializer($value,$serializerType);
        }
        return $results;
    }
    function getType($typeName=null)
    {
        $types=$this->relation->getTypes();
        if($typeName)
            return $types[$typeName];
        // Solo se retornal el primero!
        foreach($types as $key=>$value)
            return $value;
    }
    function getTypes()
    {
        return $this->relation->getTypes();
    }
	
    function getLocalModel()
    {
        return $this->model;
    }
    function isInverseRelation()
    {
        return false;
    }
    function setSerializerData($serializerName,$data)
    {
        $this->serializerData[$serializerName]=$data;
    }
    function getSerializerData($serializerName)
    {
        return $this->serializerData[$serializerName];
    }
    function getDefinition()
    {
        return $this->definition;
    } 
    function getRemoteTableQuery()
    {
        $table=$this->definition["TABLE"];
        if(!$table)
        {
            $table=$this->remoteTable;                   
        }

        $q=array(
            "TABLE"=>$table,
            "BASE"=>"SELECT * FROM ".$table			
        );

        $dconds=$this->definition["CONDITION"];
        if($dconds)
        {
            if(is_array($dconds[0]))
                $conditions=$dconds;
            else
                $conditions=array($dconds);
        }
        else
            $conditions=array();

        $q["CONDITIONS"]=$conditions;

        if($this->definition["ORDERBY"])
        {
            $q["ORDERBY"]=$this->definition["ORDERBY"];
            if($this->definition["ORDERTYPE"])
                 $q["ORDERTYPE"]=$this->definition["ORDERTYPE"];
        }
        
        return $q;
    }
    function getRelationQueryConditions($dontUseIndexes=false)
    {

        $q=$this->getRemoteTableQuery();
        $serializer=$this->getSerializer();
        $serType=$serializer->getSerializerType();
        if($dontUseIndexes==false)
        {
            $this->relation->getQueryConditions($q,$serType);        
        }

        return $q;	
    }
    function getExtraConditions()
    {
        if(isset($this->definition["CONDITIONS"]))
            return $this->definition["CONDITIONS"];
        return null;
    }
    function setExtraConditions($conditions)
    {
        $this->definition["CONDITIONS"]=$conditions;
        // Para permitir encadenado
        return $this;
    }


    function loadRemote($itemIndex=0,$dontUseIndexes=false)
    {        
        
        if($this->definition["LOAD"]=="LAZY" && $itemIndex===null)
        {
            $this->relationValues->setLoaded();
            return true;
        }    
        // Si el modelo padre es nuevo, simplemente, se crea una instancia del objeto remoto.   
        
        if(!$this->relation->is_set()) // && !$this->isInverseRelation())
        {            
        
            $this->relationValues->setLoaded();
            $count=$this->relationValues->count();
            if($itemIndex >= $count)
            {

                $remoteModel=$this->createRemoteInstance();
                $this->relationValues->loadItem($remoteModel,$itemIndex);
                $dummy= $this->relationValues[$itemIndex]; // Y se recoge, para marcarlo como accedido
                // Como estamos accediendo a campos, tenemos que decir al modelo padre que este campo puede estar sucio.
                // Por ello, lo establecemos a dirty.
                $this->relation->state=ModelBaseRelation::PENDING_REMOTE_SAVE;
                $this->setDirty();
            }
            return 1;
        }
        // Se compone una query 
        $q=$this->getRelationQueryConditions($dontUseIndexes);
        if($itemIndex!=null)
        {
            $q["STARTINGROW"]=$itemIndex;
            $q["PAGESIZE"]=1;            
        }
        $inst=$this->createRemoteInstance();
        $serializer=$inst->__getSerializer();
        $objects=$serializer->subLoad($q,$this);        

        $nObjects=count($objects);
        if($nObjects > 0)
        {
            if($itemIndex===null)
                $this->relationValues->load($objects);
            else
                $this->relationValues->loadItem($objects[0],$itemIndex);
        }
                               
        return $nObjects;
    }

    function getRemoteTableIterator()
    {
        $oldLoad=$this->definition["LOAD"];
        $this->definition["LOAD"]="FULL";
        $oldValues=$this->relationValues;
        $this->relationValues=$this->createRelationValues();
        $this->loadRemote(null,true);
        $newVals=$this->relationValues;
        $this->relationValues=$oldValues;
        $this->definition["LOAD"]=$oldLoad;
        return $newVals;
    }

    function createRemoteInstance()
    {

       $ins=\lib\model\BaseModel::getModelInstance($this->remoteObject);
       $srcConds=$this->getExtraConditions();

       if($srcConds)
       {
            $nSrcConds=count($srcConds);
            for($j=0;$j<$nSrcConds;$j++)
            {
                $ccond=$srcConds[$j]["FILTER"];
                if(is_array($ccond))
                {
                    if(isset($ccond["F"]) && $ccond["OP"]=="=")
                        $ins->{$ccond["F"]}=$ccond["V"];
                }
            }
       }

       if($this->isInverseRelation())
       {
            $fields=$this->definition["FIELDS"];
            foreach($fields as $key=>$value)
            {
                $cField=$this->model->__getField($key);
                if($cField->is_set())
                    $ins->{$value}=$this->model->{$key};
            }
       }

       return $ins;
    }

    function unserialize($data)
    {
        $this->relation->load($data);
    }
    function setDirty($dirty=true)
    {        
        
        $this->model->addDirtyField($this->name);
    }
    function is_set()
    {
        return $this->relation->is_set() || $this->relation->state==ModelBaseRelation::PENDING_REMOTE_SAVE;
    }
    function isRelation()
    {
        return true;
    }
    function __toString()
    {
        return $this->getRaw();
    }
    function is_valid()
    {
        if($this->model->isRequired($this->name))
        {
            if(!$this->type || !$this->type->is_set())
                return false;
        }
        return true;
    }
    function requiresUpdateOnNew()
    {
        // Para las relaciones "normales", es decir , A tiene una relacion con B, y estoy guardando A, siempre hay
        // que guardar primero B, obtener su valor, y copiarlo en A.
        // No es posible primero guardar A y luego hacer update de B.
        // Sin embargo, en las relaciones inversas y multiples, si que es necesario primero guardar A, y luego hacer update en B.
        // En la clase de relacion inversa, este metodo se sobreescribe, devolviendo siempre true.
        return $this->isInverseRelation();
        //return false;
    }

}




/**
 *  Class RelationFields 
 *  
 *  Campos que relacionan el modelo actual con el remoto.
 *  
 *  
 */


class RelationFields
{

    var $relObject;
    var $definition;
    var $state;
    var $nFields=0;
    var $types;
    var $fieldKey;
    var $waitingRemoteSave=false;
    var $is_set=false;
    var $rawVal=null;
    function __construct(& $relObject,$definition)
    {        
        $this->relObject=$relObject;
        $this->definition=$definition;
        $fields=$definition["FIELDS"]?$definition["FIELDS"]:(array)$definition["FIELD"];
        if(!\lib\php\ArrayTools::isAssociative($fields))        
        {
            $fields=array($this->relObject->getName()=>$fields[0]);            
        }
        foreach($fields as $key=>$value)
        {   
            $this->fieldKey=$key;            
            $this->nFields++;        
            if($definition["REMOTEDEF"])
            {
                $this->types[$key]=\lib\model\types\TypeFactory::getType($relObject->getModel(),$definition["REMOTEDEF"]["FIELDS"][$value]);
            }
            else
            {
                $this->types[$key]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($this->relObject->getRemoteObject(),$value);
            }
            if(isset($definition["DEFAULT"]))
            {
                $this->types[$key]->setDefaultValue($definition["DEFAULT"]);
            }

        }

        
        $this->definition["FIELDS"]=$fields;
        $this->state=ModelBaseRelation::UN_SET;
        
    }
    // Devuelve el valor raw del primer campo de la relacion.
    function getRawVal()
    {
        return $this->rawVal;
    }
    function getTypes()
    {
        return $this->types;
    }
    function copyField($type)
    {
        
        $hv2=$type->hasOwnValue();
        foreach($this->types as $curType)
        {
            
            $hv1=$curType->hasOwnValue();       
            if(!$hv1 && !$hv2) // Ninguno de los dos is_set
                return;
            if($hv1 && $hv2)
            {
                $val=$type->getValue();
                if($curType->equals($val))
                {
                    return;
                }
                $this->relObject->getModel()->{$this->relObject->getName()}=$val;

            }
            else
            {
                if(!$hv1 && $hv2)
                {                
                    $val=$type->getValue();            
                    // El valor se copia a traves del padre, ya que hay algunos tipos de campo (por ejemplo,
                    // los campos STATE), que al cambiar de valor, tienen repercusiones en el padre.
                    $myModel=$this->relObject->getModel();
                    $myModel->{$this->relObject->getName()}=$val;
                }
            else
            {
                foreach($this->types as $key=>$value) 
                {
                    
                         $value->clear();
                }
            }                
        }
        }
        $this->setDirty();            
    }

    function load($rawModelData)
    {
        // Aqui hay dos cosas conflictivas.is_set se refiere a si esta relacion tiene realmente un valor asociado.O sea, si no es null.
        // ModelBaseRelation::SET se refiere a si a este objeto se le han cargado valores o no.
        $this->state=ModelBaseRelation::UN_SET;
        $k=0;
        foreach($this->types as $key=>$value)
        {
            
            if(is_subclass_of($value,'\lib\model\types\Composite'))
              {
                $normName=$this->relObject->normalizedName;

                $prefix=$this->normalizedName."_";
                $prefixLen=strlen($prefix);
                $setVal=null;

                foreach($rawModelData as $key2=>$value2)
                {                
                    if(strpos($key2,$prefix)===0)
                        $setVal[substr($key2,$prefixLen)]=$value2;                    
                }
                if(!$setVal)
                {
                    return;
                }
                $value->set($setVal);                  
              }
            else
              {
                if($rawModelData[$key])
                    $value->set($rawModelData[$key]);
                else
                    return;                
              }
            if($k==0)
                $this->rawVal=$rawModelData[$key];
            $k++;
        }
        if($this->relationValues)
            $this->relationValues->reset();
        
        $this->state=ModelBaseRelation::SET;
    }

    function setFieldFromType($field,$targetType)
    {
        $typeName=get_class($targetType);
        $relType=$this->types[$field];
        $r=true;
        //if($typeName!=get_class($relType))
        //    throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->relObject->model->__getObjectName()));
        if($targetType->hasValue())
        {               
             if($this->types[$field]->equals($targetType->getValue()))                              
                 $r=true;
             else
             {

                $this->types[$field]->set($targetType->getValue());
             }
        }
        else
        {
            if($targetType->getFlags() & (\lib\model\types\BaseType::TYPE_SET_ON_SAVE | \lib\model\types\BaseType::TYPE_SET_ON_ACCESS))
                 $this->waitingRemoteSave=true;
            else
            {
                     foreach($this->types as $key=>$value)                     
                         $value->clear();
                     $r=false;
                     //throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->relObject->model->__getObjectName()));
            }
        }
        if($this->rawVal==null)
            $this->rawVal=$targetType->getValue();

        $v=$this->types[$field]->getValue();
        $this->relObject->getModel()->__setRaw($this->relObject->getName(),$v);
        $this->setDirty();
        return true;
    }
    function setFromModel($value)
    {    
        foreach($this->types as $field=>$type)
        {
            $targetField=$this->definition["FIELDS"][$field];
            $targetField=$value->__getField($targetField);
            $targetType=$targetField->getType();
            if(!$this->setFieldFromType($field,$targetType))
                return false;            
        }        
    }
    function setToModel($remObject)
    {
        foreach($this->types as $field=>$type)
        {
            $remObject->{$field}=$type->getValue();
        }
    }

    function setFromType($type)
    {
        if($this->nFields!=1)
            throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->relObject->model->__getObjectName()));
        $this->setFieldFromType($this->fieldKey,$type);
    }

    function setFromTypeValue($typeField,$newVal)
    {   
        if($typeField->equals($newVal))
           return;        
        $this->rawVal=$newVal;
        // Solo establecemos la relacion como dirty, si no es una relacion inversa.
        // Si es una relacion inversa, realmente, no hemos puesto dirty a nada, ya que no estamos asignando un valor
        // a un campo real del objeto.
        if(!$this->relObject->isInverseRelation())
        {

            $this->setDirty();
        }
        else
        {
            // Si se establece una relacion a null, y es una relacion inversa, lo que se debe hacer es eliminar
            // los campos apuntados.
            if($newVal==null)
            {
                $q=$this->relObject->getRemoteTableQuery();
                $this->getQueryConditions($q,"MYSQL");
                $remoteObject=\lib\model\BaseModel::getModelInstance($this->relObject->getRemoteObject());
                $serializer=$remoteObject->__getSerializer("WRITE");
                $qB=new \lib\storage\Mysql\QueryBuilder($q);
                $conds=$qB->build(true);

                if($conds!="")
                {
                    $q="DELETE FROM ".$remoteObject->__getTableName().$conds;
                    $serializer->getConnection()->insert($q);
                }
                $this->relObject->reset();
            }
            $this->state=ModelBaseRelation::SET;
        }

        $typeField->set($newVal);
    }
    function is_set()
    {
       if($this->state==ModelBaseRelation::SET)
           return true;
        if(!$this->state==ModelBaseRelation::DIRTY)
            return false;
        // Si al menos 1 de los campos que define la relacion no esta a nulo, la relacion no es nula.
        foreach($this->types as $key=>$value)
        {
            if($value->is_set())
                return true;
        }
        return false;
    }

    function setFromValue($val)
    {       
        if($this->nFields==1)
        {            
            if(is_array($val))
                $val=$val[$this->fieldKey];
                        
            $relType=$this->types[$this->fieldKey];
            $this->setFromTypeValue($relType,$val);
        }
        else    
        {
            if(is_object($val))
            {
                // TODO : Adaptarlo para permitir asignar relaciones.
                throw new \lib\model\BaseModelException(\lib\model\BaseModelException::ERR_INVALID_VALUE);
                return;
            }
            foreach($this->types as $field=>$type)
            {                
                if(!isset($val[$field]))
                {
                    throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->relObject->model->__getObjectName(),"field"=>$key));
                }
                $this->setFromTypeValue($type,$val[$field]);
                
            }
        }
    }

    function set($value)
    {

        if($this->relationValues)
        {
            $this->relationValues->reset();
        }
        $this->waitingRemoteSave=false; 
        if(is_object($value) && is_subclass_of($value,"\\lib\\model\\BaseModel"))
        {
            $remObjName=new \lib\reflection\model\ObjectDefinition($this->relObject->getRemoteObject());            
            if($remObjName->className==$value->__getObjectName())
            {                            
                $this->setFromModel($value);
            }
            else
            {                            
                $this->setFromType($value);
            }
        }
        else
            $this->setFromValue($value);
    }

    function cleanState()
    {
        if($this->state==ModelBaseRelation::DIRTY)
            $this->state=ModelBaseRelation::SET;
        $this->waitingRemoteSave=false;
    }

    function isDirty()
    {
       return ($this->state==ModelBaseRelation::DIRTY || $this->waitingRemoteSave);
    }

    function __toString()
	{
        $cad="";
        foreach($this->types as $key=>$value)
        {
            $cad.=$value->getValue();
            reset($this->types);
            return $cad;
        }
	}

    function getQueryConditions(& $q,$serializerType)
    {
        $h=0;
        $extraConds="true";
        $extra=$this->relObject->getExtraConditions();
        if($extra)
        {
            if(is_array($extra))
            {
                for($k=0;$k<count($extra);$k++)
                {
                    $econditionKeys[]="[%ec".$k."%]";
                    $q["CONDITIONS"]["ec".$k]=$extra[$k];
                }
                $extraConds=implode(" AND ",$econditionKeys);
            }
            else
                $extraConds=$extra;
        }


        foreach($this->types as $key=>$value)
        {
            $curKey="[%".$h."%]";
            $curVal=\lib\model\types\TypeFactory::serializeType($value,$serializerType);
            if(is_array($curVal))
            {
                foreach($curVal as $key2=>$value2)
                    $q["CONDITIONS"][]=array("FILTER"=>array("F"=>$key."_".$key2,"OP"=>"=","V"=>$value2));
            }
            else
                $q["CONDITIONS"][]=array("FILTER"=>array("F"=>$this->definition["FIELDS"][$key],"OP"=>"=","V"=>$curVal));
            $h++;
            $conditionKeys[]=$curKey;
        }
        $q["BASE"].=" WHERE ".$extraConds." AND ".implode(" AND ",$conditionKeys);
    }

    function serialize($serializerType)
    {   
         
       if($this->state==ModelBaseRelation::UN_SET)
       {
           return array();
       }

       if($this->nFields==1)
           $prefix="";
       else
           $prefix=$this->name."_";     
       $results=array(); 


       $serializer=$this->relObject->getSerializer();
       
       

       foreach($this->types as $key=>$curType)
       {
            if($curType->is_set())
            {
                $data=\lib\model\types\TypeFactory::serializeType($curType,$serializerType);
                
                if(!is_array($data))
                {
                    $results[$prefix.$key]=$data;
                    continue;
                }
                
                
                foreach($data as $key2=>$value2)
                {                        
                     $results[$prefix.$key."_".$key2]=$data[$key2];
                }
                
            }
            
            
        }
        return $results;
    }
    function setDirty()
    {
        $this->state=ModelField::DIRTY;        
        $this->relObject->setDirty();
    }



}
/**
 * Class RelationValues: Campos obtenidos de una relacion, sea 
 * directa o indirecta. 
 *  
 *  
 */

class RelationValues extends \lib\datasource\TableDataSet
{
    protected  $relatedObjects;
    protected  $accessedIndexes=array();
    protected  $relField;
    protected  $loadMode;
    protected  $nResults;
    protected  $isLoaded;
    protected  $nColumns;
    protected $currentIndex;
    protected $isDirty;
    protected $newObjects;

    function __construct($relField,$loadMode)
    {
        $this->relField=$relField;
        $this->loadMode=$loadMode;
        $this->isLoaded=false;
        $this->nResults=null;
        $this->currentIndex=0;
        $this->newObjects=array();

    }
    public function load($values,$count=null)
    {        
        $this->isLoaded=true;
        $this->relatedObjects=$values;
        if(!$count)
            $this->nResults=count($values);  
    }

    public function loadItem($value,$index)
    {
        if($value==null)
            return;
        $this->relatedObjects[$index]=$value;
    }
    // Implementacion de metodos de TableDataSet
    function setIndex($idx)
    {
        $this->currentIndex=$idx;
    }
    function getField($field)
    {
        return $this[$this->currentIndex]->{$field};
    }

    function getColumn($colName)
    {
        $nItems=$this->count();
        for($k=0;$k<$nItems;$k++)
        {
            $results[]=$this[$k]->{$colName};
        }
        return $results;
    }
    function getRow()
    {
        return $this[$this->currentIndex];
    }

    public function offsetExists($offset)
    {		
        $nItems=$this->count();
        return $offset<$nItems;
    }
	
    public function offsetGet( $offset )
    {	
        
        if(!$this->isLoaded)	
        {
            $this->relField->loadRemote();
        }
        $errored=0;
        if(!isset($this->relatedObjects[$offset]))
        {
            if($this->loadMode=="LAZY")
            {                
                if($this->relField->loadRemote($offset)<=0)
                    $errored=1;
            }
            else
                $errored=1;

            if($errored)
            {
                if($offset==$this->nResults && $this->relField->isInverseRelation())
                {
                    $this->isLoaded=true;
                    $newInst=$this->relField->createRemoteInstance();
                    $this->newObjects[]=$newInst;
                    $this->relatedObjects[]=$newInst;
                    $this->nResults++;
                }
                else
                {
                    $h=11;
                    throw new BaseModelException(BaseModelException::ERR_INVALID_OFFSET,array("model"=>$this->relField->getModel()->__getObjectName(),"field"=>$this->relField->getName(),"offset"=>$offset));
                }
            }

        }
        else
            $this->isLoaded=true;
        $this->accessedIndexes[$offset]=1;
		return $this->relatedObjects[$offset];
    }

    public function offsetSet( $offset , $value )
    {
       return false;
    }
    public function offsetUnset($offset)
    {	
		
    }
    public function add($value)
    {
        $this->relatedObjects[]=$value;
        // Cuando se añade un elemento a una relacion, se copian los datos de serializado del modelo padre, al añadido
        $lModel=$this->relField->getLocalModel();
        $defSerializer=$lModel->__getSerializer();
        $value->__setSerializerFilters($lModel->__getSerializerFilters($defSerializer),$defSerializer);
        $this->accessedIndexes[$this->nResults]=1;
        $this->nResults++;
    }

    function count()
    {
        if($this->relField->getRelation()->is_set())
        {
            if($this->nResults===null)
            {
               $this->nResults=$this->relField->loadCount();
            }
            return $this->nResults;
        }
        else
        {
            $this->nResults=count($this->relatedObjects);
            return $this->nResults;
        }
    }
    function setCount($nItems)
    {
        $this->nResults=$nItems;
    }
    function setLoaded()
    {
        $this->isLoaded=true;
    }

    
    public function save()
    {
        // Si esta relacion impone condiciones sobre el objeto remoto, por ejemplo, inverserelations,
        // las condiciones deben ser copiadas a los objetos modificados.
        $srcConds=$this->relField->getExtraConditions();
        if($srcConds)
            $nSrcConds=count($srcConds);
        else
            $nSrcConds=0;

         $accessed=array_keys($this->accessedIndexes);
         $nAccessed=count($accessed);
         if($nAccessed==0)
             return 0;
            // Se guardan todos los accedidos.
         $saved=0;

        $isInverse=$this->relField->isInverseRelation();
        if($isInverse)
        {
            $def=$this->relField->getDefinition();
            $relFields=$def["FIELDS"];
            $parentModel=$this->relField->getModel();
        }
         for($k=0;$k<$nAccessed;$k++)
         {
             $curObject=$this->relatedObjects[$accessed[$k]];

             if($curObject->isDirty())
             {
                if($isInverse)
                {
                    foreach($relFields as $key=>$value)
                    {
                        $f=$curObject->__getField($value);
                        if(!$f->is_set())
                            $curObject->{$value}=$parentModel->{$key};
                    }
                }

                $curObject->save(); //$this->relField->getSerializer());
                $saved++;
             }
         }
         $this->accessedIndexes=array();
        $this->newObjects=array();
        $this->relField->cleanState();
         return $saved;
    }

    public function isDirty()
    {        
        if($this->isDirty)
            return true;

         $accessed=array_keys($this->accessedIndexes);
         $nAccessed=count($accessed);
         if($nAccessed==0)
             return false;

         for($k=0;$k<$nAccessed;$k++)
         {
             $curObject=$this->relatedObjects[$accessed[$k]];
             if($curObject->isDirty())
                 return true;
         }
         return false;
    }

    public function reset()
    {
        $this->relatedObjects=array();
        $this->accessedIndexes=array();
        $this->isLoaded=false;
        $this->currentIndex=0;
        $this->isDirty=false;
        $this->nResults=null;

    }
    
    public function isLoaded()
    {
        return $this->isLoaded;
    }
    public function countColumns()
    {
        // Se obtienen los datos a partir de la definicion del objeto 
        if($this->nColumns!==null)
            return $this->nColumns;

        if($this->count()>0)	
        {
            $obj=$this[0];
            $this->nColumns=$obj->getFieldCount();
            return $this->nColumns;

        }
        return 0;        
        
    }

    public function getMetaData()
    {
        if($this->count()>0)	
        {
            $obj=$this[0];
            return $obj->getDefinition();
        }
        return null;        
    }
    
}

