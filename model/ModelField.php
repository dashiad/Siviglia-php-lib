<?php 

namespace lib\model;
class ModelField
{
    const UN_SET=0;
    const SET=1;
    const FUTURE_SET=2;
    const DIRTY=3;

    protected $type;
    protected $definition;
    protected $model;
    protected $state; 
    protected $listeners;
    protected $isAlias;
    protected $isComposite;

    protected $compositeFields;
    protected $normalizedName;
    protected $name;
    function __construct($name,& $model, $definition,$value=false)
    {   
        
          $this->name=$name;
          $this->model=& $model;
          // Linea especial para los objetos de tipo "estado".La definicion hay que completarla con datos contenidos en el modelo,
          // por lo que hay que controlarlo aqui.
          if($definition["TYPE"]=="State")
          {
              $definition=$this->getStateDefinition($model,$definition);
          }

          $this->definition=$definition;

          $this->type=types\TypeFactory::getType($this->model,$definition);

          if(is_subclass_of($this->type,'\lib\model\types\Composite'))
          {
              $this->isComposite=true;
              $this->compositeFields=$this->type->getFields();
          }


          $this->isAlias=false;
          if($value!==false)
          {
              $this->set($value);
              $this->state=ModelField::SET;
          }
          else
              $this->state=ModelField::UN_SET;
    }
    static function getModelField($name,& $model, & $definition,$value=false)
    {
        switch($definition["TYPE"])
        {
        
        case 'TreeAlias':
            {
                return new TreeAlias($name,$model,$definition);
            }break;
        case 'Relationship':
            {
                switch($definition["MULTIPLICITY"])
                {
                case "M:N":
                    {
                        return new RelationMxN($name,$model,$definition,$value);
                    }break;
                case "1:1":
                case "1:N":
                default:
                    {
                        return new Relation1x1($name,$model,$definition,$value);
                    }
                }
            }break;
        case 'RelationMxN':
            {
                 return new RelationMxN($name,$model,$definition,$value);
            }break;
        case 'InverseRelation':
            {                
                $obj=new InverseRelation1x1($name,$model,$definition,$value);
                return $obj;
            }break;
        case 'State':
            {
                if(!$definition["VALUES"])
                {
                    $states=$model->getStateDef()->getStates();
                    $originalDefinition["VALUES"]=$states;                    
                    $originalDefinition["TYPE"]=$definition["TYPE"];
                }
                else
                    $originalDefinition=$definition;

                if(!$originalDefinition["DEFAULT"])
                    $originalDefinition["DEFAULT"]=$model->getStateDef()->getDefaultState();
                return new ModelField($name,$model,$originalDefinition,$value);
            }break;
        default:
            {
                return new ModelField($name,$model,$definition,$value);
            }break;
        }
    }

    function getStateDefinition($model,$originalDefinition)
    {
        
        return $originalDefinition;
    }

    function getDefinition()
    {
        return $this->definition;
    }
    function getName()
    {
        return $this->name;
    }
    function load(& $rawModelData,$unserializeType=null)
    {
        if($this->type->isComposite)
        {
            \lib\model\types\TypeFactory::unserializeType($this->type,$rawModelData,$unserializeType);
        }
        else
        {
            if ($this->isMultiple) {
                \lib\model\types\TypeFactory::unserializeType($this->type,$rawModelData,$unserializeType);
            }
            else {
                \lib\model\types\TypeFactory::unserializeType($this->type,$rawModelData[$this->name],$unserializeType);
            }
        }
        $this->state=ModelField::SET;
    }

    function setAlias($alias)
    {
        $this->isAlias=$alias;
    }
    function isAlias()
    {
        return $this->isAlias;
    }
    function getModel()
    {
        return $this->model;
    }
    function set($value)
    {        
        if(is_object($value))
        {            
            $h=new \ReflectionClass($value);
            $parent=$h->getParentClass();
            
            if(is_a($value,'lib\model\types\BaseType'))    
            {
                
                $typeObj=$value;            
            }
            else
            {

                if(method_exists($value,"getType"))
                    $typeObj=$value->getType();                
            }            
            
            if($typeObj)
            {
                $val=$typeObj;     
                $value=$typeObj->getValue();
            }
            else
            {
                throw new BaseModelException(BaseModelException::ERR_INVALID_VALUE,array("field"=>$this->name,"value"=>$value));
            }
        }
        else
        {
            $val=$value;
        }

        if(!is_object($this->type))
        {
            echo "SIN OBJETO::".$this->type;
            $h=30;
        }
        if($this->type->equals($value))
        {
            return;
        }
        
        $this->setDirty();        
        $this->type->set($val);
        $this->model->__setRaw($this->name,$this->type->get());
        $this->notifyListeners();
    }
    function __rawSet($value)
    {
        $this->setDirty();
        $this->type->__rawSet($value);
        
    }
    function is_set()
    {
        return $this->type->hasValue();
    }
    function hasOwnValue()
    {
        return $this->type->hasOwnValue();
    }
    function clear()
    {
        $this->setDirty(); 
        $this->type->clear();
        $this->notifyListeners();
    }

    function copyField($type)
    {    
        $hv1=$this->type->hasOwnValue();       
        $hv2=$type->hasOwnValue();       
        if(!$hv1 && !$hv2) // Ninguno de los dos is_set
            return;
        
        if($hv1 && $hv2)
        {
            
            $val=$type->getValue();
            
            if($this->type->equals($val))
            {
                return;
            }
            $this->model->{$this->name}=$type; //$val;
        }
        else
        {
            
            if(!$hv1 && $hv2)
            {                
                //$val=$type->getValue();            
                // El valor se copia a traves del padre, ya que hay algunos tipos de campo (por ejemplo,
                // los campos STATE), que al cambiar de valor, tienen repercusiones en el padre.
                $this->model->{$this->name}=$type; //val;
            }
            else
            {
                if($this->type->getFlags() & \lib\model\types\BaseType::TYPE_NOT_MODIFIED_ON_NULL)
                    return;
                $this->clear();
            }
        }
        $this->setDirty();            
     }
    function setDirty()
    {
       $this->state=ModelField::DIRTY;
       $this->model->addDirtyField($this->name);
    }
    
    function __get($varName)
    {
        return $this->type->{$varName};        
    }
    function __call($fName,$arguments)
    {
        return call_user_func_array(array($this->type,$fName),$arguments);
    }
    function getState()
    {
        return $this->state;
    }
    function getType($typeName=null)
    {
        return $this->type;
    }
    function getTypes()
    {
        return array($this->name=>$this->type);
    }
    function __setType($type)
    {
        $this->type=$type;
    }

    function get()
    {                       
        return $this->type->get();
        
    }
    function is_valid()
    {
        if($this->isRequired())
        {
            if(!$this->type || !$this->type->is_set())
                return false;
        }
        return true;
    }
    function isRequired()
    {
        return $this->model->isRequired($this->name);
    }
    function isRelation()
    {
        return false;
    }
    function isDefinedAsRequired()
    {
        return isset($this->definition["REQUIRED"]) && $this->definition["REQUIRED"];
    }
    function isDirty()
    {
        return $this->state==ModelField::DIRTY;
    }

    function addListener($obj,$method)
    {
        if(!$this->listeners)
            $this->listeners[]=array($obj,$method);
    }

    function serialize($serializer)
    {                     
        //if($this->type->hasValue())
        //{
            try
            {
                $ser=\lib\model\types\TypeFactory::getSerializer($this->type->definition["TYPE"],$serializer);
                $data= $ser->serialize($this->type);
                
            }catch(\lib\model\types\BaseTypeException $e)
            {
                _d($e);
                throw new BaseModelException(BaseModelException::ERR_INVALID_SERIALIZER,array("fieldName"=>$this->name,"model"=>$this->model->__getObjectName(),"serializer"=>$serializer));
            }
            
            if(!$this->isComposite)
            {
                return $data;
            }
            
            $results=array();
            foreach($this->compositeFields as $key=>$value)
            {
                foreach($data as $key2=>$value2)
                {
                    if(strpos($key2,$value)===0)
                        $results[$this->name."_".$key2]=$data[$key2];
                }
            }
            
            return $results;
        //}
        
        //return null;
    }
    function unserialize($data,$unserializer=null)
    {
        $this->load($data,$unserializer);
    }
    function save()
    {
        $flags=$this->type->getFlags();
        
        if($flags & \lib\model\types\BaseType::TYPE_REQUIRES_SAVE)
        {
            $this->type->save($this);
            $this->model->__setRaw($this->name,$this->type->getValue());
            return;
        }
        if($flags & \lib\model\types\BaseType::TYPE_SET_ON_ACCESS)
        {
            if(!$this->type->hasOwnValue())
            {
                
                $val=$this->type->getValue();
                $this->model->addDirtyField($this->name);
                $this->model->__setRaw($this->name,$val);
            }
            return;
        }
        
        if($flags & \lib\model\types\BaseType::TYPE_IS_FILE)
        {
            if($this->isDirty())
            {                
                $this->type->save();
                $this->model->__setRaw($this->name,$this->type->getValue());
            }
        }
       /* if(!$this->type->hasOwnValue() && $this->type->hasValue())
        {
            $this->model->addDirtyField($this->name);
            $this->model->__setRaw($this->name,$this->type->getValue());
        }*/
        
    }
    function getTypeSerializer($serializerType)
    {
        return array($this->name=>\lib\model\types\TypeFactory::getSerializer($this->type,$serializerType));
    }

    function notifyListeners()
    {
        $nListeners=count($this->listeners);
        for($k=0;$k<$nListeners;$k++)
        {
            list($obj,$method)=$this->listeners[$k];
            $obj->$method($this);
        }
    }
    function signal($field)
    {
        $this->set($field->get());
    }
    
    function onModelSaved()
    {
        $this->cleanState();
        
        if($this->type->getFlags() & \lib\model\types\BaseType::TYPE_REQUIRES_SAVE)
            $this->type->onSaved($this);
    }
    function cleanState()
    {
        $this->state=$this->type->hasValue()?ModelField::SET:ModelField::UN_SET;
    }
    function requiresUpdateOnNew()
    {
        return $this->type->getFlags() & \lib\model\types\BaseType::TYPE_REQUIRES_UPDATE_ON_NEW;
    }

}
