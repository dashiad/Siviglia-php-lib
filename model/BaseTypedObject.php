<?php namespace lib\model;
use Payment\BaseLogImporter;

class PathObjectException extends \lib\model\BaseException {
    const ERR_PATH_NOT_FOUND=1;
    const ERR_NO_CONTEXT=2;
}

class PathObject {
    var $tempContext;
   function getPath($path, $context)
   {
       if($path[0]!='/')
           $path='/'.$path;
       if($context)
           $context->setCaller($this);

       $parts=explode("/",$path);
       $pathLength=count($parts);
       return PathObject::_getPath($this,$parts,0,$context,$path,$pathLength);
   }
   private function psCallback($match)
   {
       return $this->getPath($match[1],$this->tempContext);
   }
    // La siguiente funcion gestiona paths dentro de paths.
   function parseString($str,$context)
   {
       $this->tempContext=$context;       
       return preg_replace_callback("/{\%([^%]*)\%}/",array($this,"psCallback"),$str);
   }

   static function _getPath(& $obj,$path,$index,$context,& $origPath,$pathLength=-1)
   {              
       if(!isset($obj))
           throw new PathObjectException(PathObjectException::ERR_PATH_NOT_FOUND,array("path"=>$origPath,"index"=>$index));       
      if($index+1==$pathLength)
            return $obj;
       
      if(is_string($path[$index+1]))
      {
        $c=$path[$index+1][0];
        if($c=="@")
        {
            if(!$context)
            {
                throw new PathObjectException(PathObjectException::ERR_NO_CONTEXT);
            }
            $variable=$path[$index+1];
            $onListener=null;

            $caller=$context->getCaller();
            $path[$index+1]=substr($path[$index+1],1);
            $onListener=$caller->{$path[$index+1]};
            
            if($onListener)
                $tempObj=$caller;
            else
                $tempObj=$context;

            

            if(is_array($tempObj))
            {
                $val=$tempObj[$path[$index+1]];
                if(!isset($val))
                    throw new PathObjectException(PathObjectException::ERR_PATH_NOT_FOUND,array("path"=>$origPath,"index"=>$index+1));
            }
            else
            {
                if(is_object($tempObj))
                {
                    $val=$tempObj->{$path[$index+1]};
                    if(!isset($val) || $val===null)
                    if($tempObj instanceof ArrayAccess)
                    {
                        $val=$tempObj[$path[$index+1]];
                        if(!isset($val))
                            throw new PathObjectException(PathObjectException::ERR_PATH_NOT_FOUND,array("path"=>$origPath,"index"=>$index+1));
                    }
                    else
                        throw new PathObjectException(PathObjectException::ERR_PATH_NOT_FOUND,array("path"=>$origPath,"index"=>$index+1));
                }
            }
            if(is_object($val) || is_array($val))
            {
                
                 $index++;
                 $obj=$val;
            }
            else
            {
                $path[$index+1]=$val;
            }            
            return PathObject::_getPath($obj,$path,$index,$context,$origPath,$pathLength);
        }
    }
    if(is_array($obj) || is_a($obj,"ArrayAccess"))
    {
        
        if(isset($obj[$path[$index+1]]))
        {
            return PathObject::_getPath($obj[$path[$index+1]],$path,$index+1,$context,$origPath,$pathLength);
        }
        throw new PathObjectException(PathObjectException::ERR_PATH_NOT_FOUND,array("path"=>$origPath,"index"=>$index+1));
    }
    
    if(is_object($obj))
    {   
             
        $propName=$path[$index+1];
        if($propName=="")
        {
            echo "BREAKING";
        }
        $val=$obj->{$propName};
        if(!(is_object($val) || is_array($val)))
        {
            if(method_exists($obj,$val))
            {
                $result=$obj->{$val}();                
                return PathObject::_getPath($result,$path,$index+1,$context,$origPath,$pathLength);
            }
            return $val;        
        }
        else
             return PathObject::_getPath($val,$path,$index+1,$context,$origPath,$pathLength);
    }
     else
        return $obj;
    
   
        
    //return  Ecija.Dom.getPath(obj[path[index+1]],path,index+1,context,currentObject,listener);
}

}

 class SimpleContext  
 {
        var $caller;
        function setCaller($obj){$this->caller=$obj;}  
        function getCaller(){return $this->caller;}
 }

 class SimplePathObject extends \lib\model\PathObject
 {
        function addPath($nodeName,& $objectInstance)
        {
            $this->{$nodeName}=& $objectInstance;
        }
 }

class BaseTypedException extends BaseException {
    const ERR_REQUIRED_FIELD=1;
    const ERR_NOT_A_FIELD=2;
    const ERR_INVALID_STATE=3;
    const ERR_INVALID_STATE_TRANSITION=4;
    const ERR_INVALID_PATH=5;
    const ERR_DOUBLESTATECHANGE=6;
    const ERR_INVALID_STATE_CALLBACK=7;
    const ERR_CANT_CHANGE_FINAL_STATE=8;
    const ERR_NO_STATE_DEFINITION=9;
    const ERR_CANT_CHANGE_STATE=10;
    const ERR_CANT_CHANGE_STATE_TO=11;
    const ERR_REJECTED_CHANGE_STATE=12;
}

class BaseTypedObject extends PathObject
{
        protected $__fieldDef;
        protected $__fieldInstances;
        protected $__data;
        protected $__fields;
        protected $__objectDef;        
        protected $__loaded=0;
        protected $__serializer=null;
        protected $__isDirty=false;
        protected $__dirtyFields=array();
        protected $__stateDef;
        protected $__oldState=null;
        protected $__newState=null;
        function __construct($definition)
        {
            
                $this->__objectDef=$definition;
                $this->__fieldDef=& $this->__objectDef["FIELDS"];
                $this->__stateDef=new \lib\model\states\StatedDefinition($this);
        }
        
        function getDefinition() {
                return $this->__objectDef;
        }

        function __getFields()
        {

               foreach($this->__fieldDef as $key=>$value)    
                    $this->__getField($key);
               return $this->__fields;
        }
        

        function __getField($fieldName)
        {
            if(!isset($this->__fields[$fieldName]))
            {
                if(isset($this->__fieldDef[$fieldName]))
                {

                    $this->__fields[$fieldName]=\lib\model\ModelField::getModelField($fieldName,$this,$this->__fieldDef[$fieldName]);
                }
                else
                {
                    // Caso de "path"
                    if(strpos($fieldName,"/")>=0)
                    {
                        $remField=$this->__findRemoteField($fieldName);
                        if($remField)
                            return $remField;
                    }

                    include_once(PROJECTPATH."/lib/model/BaseModel.php");
                    throw new \lib\model\BaseTypedException(BaseTypedException::ERR_NOT_A_FIELD,array("name"=>$fieldName));
                }
            }
            return $this->__fields[$fieldName];            
        }
        function & __getFieldDefinition($fieldName)
        {
            if(isset($this->__fieldDef[$fieldName]))
                return $this->__fieldDef[$fieldName];

            throw new BaseTypedException(BaseTypedException::ERR_NOT_A_FIELD,array("name"=>$fieldName));

        }
        // Usado para los aliases de BaseModel
        function & __addField($fieldName,$definition)
        {
            $this->__fields[$fieldName]=\lib\model\ModelField::getModelField($fieldName,$this,$definition);
            return $this->__fields[$fieldName];

        }
        function __setSerializer($serializer)
        {
            $this->__serializer=$serializer;
        }
        // Si raw es true, el valor se asigna incluso si la validacion da un error.
        function loadFromArray($data,$serializer,$raw=false)
        {   
            $fields=$this->__getFields();     
            foreach($fields as $key=>$value) 
            {                                
                try{
                    $value->unserialize($data,$serializer);
                }
                catch(\Exception $e)
                {
                    if($raw==false)
                        throw $e;
                    $value->getType()->__rawSet($data[$key]);
                }
            }
            $this->__data=$data;
            
            $this->__loaded=true;

        }
        function load($data) 
        {            
             if(is_object($data))
             {
                 $this->unserialize($data);
                 return;
             }
             $this->__loaded=true;
        }

        function isLoaded() 
        {        
            return $this->__loaded;
        }
        function __get($varName) 
        {
            if($varName[0]=="*")
            {
                $varName=substr($varName,1);
                
                return $this->__getField($varName)->getType();
            }
            if(isset($this->__fieldDef[$varName]))
            {
                $gMethod="get_".$varName;
                if(method_exists($this,$gMethod)) {
                    return $this->$gMethod();
                }
                return $this->__getField($varName)->get();
            }
            $remoteField=$this->__findRemoteField($varName);
            if($remoteField)
                return $remoteField->get();

            throw new BaseTypedException(BaseTypedException::ERR_NOT_A_FIELD,array("field"=>$varName));
        }
        function __findRemoteField($varName)
        {
            $parts=explode("/",$varName);
            if($parts[0]=="")
                array_splice($parts,0,1);
            $nParts=count($parts);
            if($nParts==1)
                return false;
            $context=new SimpleContext();
            // Si el path es, por ejemplo, a/b/c, queremos encontrar a/b , y pedirle el campo c.
            // Por eso se extrae y se guarda el ultimo elemento.
            $lastField=array_splice($parts,-1,1);
            $result=$this->getPath("/".implode("/",$parts),$context);
            if(!is_object($result))
            {
                throw new BaseTypedException(BaseTypedException::ERR_INVALID_PATH,array("path"=>$varName));
            }
            // Por fuerza, el objeto $result tiene que ser un objeto relacion.Por lo tanto, hay que obtener el remote object, y a este, pedirle
            // el ultimo campo que hemos guardado previamente.
            if(is_a($result,'\lib\model\ModelBaseRelation'))
                return $result->offsetGet(0)->__getField($lastField[0]);
            return $result->__getField($lastField[0]);

        }
        function setFields($fields)
        {
            foreach($fields as $key => $value)
            {
                $t=11;
                $this->__set($key,$value);
            }

        }
        function __set($varName,$value) {


            if(isset($this->__fieldDef[$varName]))
            {
                // Se comprueba primero que el valor del campo es diferente del que tenemos actualmente.

                if($this->{"*".$varName}->equals($value))
                    return;

                if($this->__stateDef->hasState && $this->isLoaded())
                {
                    if(!$this->__stateDef->isEditable($varName) && $value!=$this->{$varName})
                    {

                        throw new BaseTypedException(BaseTypedException::ERR_INVALID_STATE,array("field"=>$varName,"state"=>$this->__stateDef->getCurrentState()));
                    }
                }
                $checkMethod="check_".$varName;
                if(method_exists($this,$checkMethod))
                    $this->$checkMethod($value);

                $processName="process_".$varName;
                $existsProcess=method_exists($this,$processName);

                if($existsProcess)
                    $value=$this->$processName($value);
            }
            else
            {
                $remField=$this->__findRemoteField($varName);
                if($remField)
                {
                    return $remField->getModel()->__set($remField->getName(),$value);
                }
            }

            // Ahora hay que tener cuidado.Si lo que se esta estableciendo es el campo que define el estado
            // de este objeto, no hay que copiarlo.Hay que meterlo en una variable temporal, hasta que se haga SAVE
            // del objeto.El nuevo estado aplicará a partir del SAVE.Asi, podemos cambiar otros campos que era posible
            // cambiar en el estado actual del objeto.

            $targetField=$this->__getField($varName);
            if($this->__stateDef->hasState)
            {
                if($varName==$this->__stateDef->getStateField())
                {
                    if ($this->__dirtyFields[$varName])
                        throw(new BaseModelException(BaseTypedException::ERR_DOUBLESTATECHANGE));
                    $this->__stateDef->setOldState($this->__getField($varName)->get());
                    $this->__stateDef->changeState($value);
                    // El cambiar el estado en si, lo hace la definicion de estados, en el metodo changeState
                }
                else
                    $targetField->set($value);

            }
            else
                $targetField->set($value);

            $targetField->getModel()->addDirtyField($varName);
        }

        function copy(& $remoteObject)
        {
                  
             $remFields=$remoteObject->__getFields();
             if($this->__stateDef->hasState)
                 $stateField=$this->getStateField();
             else
                 $stateField='';
             foreach($remFields as $key=>$value)
             {
                 
                 $types=$value->getTypes();                 
                 foreach($types as $tKey=>$tValue)
                 {

                     try{
                         $field=$this->__getField($tKey);
                         if($tKey==$stateField)
                             $this->newState=$tValue->get();
                         else
                            $field->copyField($tValue);
                     }catch(BaseTypedException $e)
                     {
                         if($e->getCode()==BaseTypedException::ERR_NOT_A_FIELD)
                         {
                             // El campo no existe.No se copia, pero se continua.
                             continue;
                         } // En cualquier otro caso, excepcion.
                         else
                             throw $e;
                     }
                 }                 
             }
             
             $this->__dirtyFields=$remoteObject->__dirtyFields;
                          
             $this->__isDirty=$remoteObject->__isDirty;             
         }
            /*
         function validate()
         {
             foreach($this->__fieldDef as $key=>$value)
             {
                 if($this->isRequired($key) && $this->__getField($key)->is_set())
                 {
                       throw new BaseTypedException(BaseTypedException::ERR_REQUIRED_FIELD,array("name"=>$key));
                 }
             }
         }*/
         function save()
         {
             $this->__saveState();
         }
         function checkTransition()
         {
             if(!$this->__stateDef->hasState)
                 return true;
             if($this->__newState!==null)
             {
                 if($this->__stateDef->canTranslateTo($this->__newState))
                     return true;
                 throw new BaseTypedException(BaseTypedException::ERR_INVALID_STATE_TRANSITION,array("current"=>$this->__stateDef->getCurrentState(),"next"=>$this->__newState));
             }
             return true;
         }
         function __checkState()
         {
             $this->__stateDef->checkState();
         }
         function __saveState()
         {
             $this->__stateDef->reset();
             /*
              *
              $this->__oldState=null;
              $this->__newState=null;
             */
         }

         function isDirty()
         {
             return $this->__isDirty;                
         }

         function setDirty($dirty)
         {
             $this->__isDirty=$dirty;
             if(!$dirty)
                 $this->__dirtyFields=array();
         }

         function addDirtyField($fieldName)
         {
             $this->__isDirty=true;
             $this->__dirtyFields[$fieldName]=1;
         }

         function cleanDirtyFields()
         {
             $this->__isDirty=false;
             $this->__dirtyFields=array();
         }
         function isRequired($fieldName)
         {
             $fieldDef=$this->__getField($fieldName)->getDefinition();
             // TODO: El modelo podria ser otro, no solo el actual.
             if(isset($fieldDef["MODEL"]) && isset($fieldDef["FIELD"]))
                 $fieldName=$fieldDef["FIELD"];
             return $this->__stateDef->isRequired($fieldName);
         }
         function isEditable($fieldName)
         {
             return $this->__stateDef->isEditable($fieldName);
         }
         function isFixed($fieldName)
         {
             return $this->__stateDef->isFixed($fieldName);
         }

    function disableStateChecks()
    {
        $this->__stateDef->disable();
    }
    function enableStateChecks()
    {
        $this->__stateDef->enable();
    }
    function getStateField()
    {
        return $this->__stateDef->getStateField();
    }
    function getStates()
    {
        return $this->__stateDef->getStates();
    }
    function getStateDef()
    {
        return $this->__stateDef;
    }

    function getStateId($stateName)
    {
        if (!$this->__objectDef["STATES"])
            return null;
        return array_search($stateName, array_keys($this->__objectDef["STATES"]["STATES"]));
    }

    function getStateLabel($stateId)
    {
        if (!$this->__objectDef["STATES"])
            return null;
        $statekeys = array_keys($this->__objectDef["STATES"]["STATES"]);
        //var_dump($statekeys[$stateId]);
        return $statekeys[$stateId];
    }

    function getState()
    {
        return $this->__stateDef->getCurrentState();
    }

    function __setRaw($fieldName,$data)
    {
        $this->__data[$fieldName]=$data;
    }
    function __getRaw()
    {
        return $this->__data;
    }
}
