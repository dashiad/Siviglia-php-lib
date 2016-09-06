<?php namespace lib\model;
class ModelKey
{
        var $indexFieldNames=array();
        var $nIndexes;
        var $indexFields=array();        
        var $model;
        function __construct(& $model,& $definition)
        {
            
            $this->model=$model;
            $this->indexFieldNames=(array)$definition["INDEXFIELDS"];
            $this->nIndexes=count($this->indexFieldNames);                
            for($k=0;$k<$this->nIndexes;$k++)
            {
                $cField=$this->indexFieldNames[$k];
                $this->indexFields[$cField]= $model->__getField($cField);
            }                
        }
        function getKeyNames()
        {
            return $this->indexFieldNames;
        }
        function getHash()
        {
            $hash=$this->model->__getFullObjectName();
            foreach($this->indexFields as $key=>$value)
            {
                $hash.=($key.($value->getValue()));
            }
            return $hash;
        }
        function set($id)
        {            
            if(is_array($id))
            {
                $vals=array_values($id);
                if(count($vals)!=$this->nIndexes)
                    throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->model->__getFullObjectName()));
                foreach($id as $key=>$value)
                {
                    if(!$this->indexFields[$key])
                    {
                        throw new BaseModelException(BaseModelException::ERR_UNKNOWN_KEY_FIELD,array("keyField"=>$key));
                    }
                    $this->indexFields[$key]->set($value);
                    // We dont want those fields to think they're dirty
                    $this->indexFields[$key]->cleanState();
                }
            }
            else
            {
                
                if($this->nIndexes != 1)
                    throw new BaseModelException(BaseModelException::ERR_INCOMPLETE_KEY,array("model"=>$this->model->__getFullObjectName()));
                
                $this->indexFields[$this->indexFieldNames[0]]->set($id);                
                // We dont want those fields to think they're dirty
                $this->indexFields[$this->indexFieldNames[0]]->cleanState();
            }            
        }
        
        function is_set()
        {
            foreach($this->indexFields as $key=>$value)
            {
                if(!$value->hasOwnValue())
                    return false;
            }
            return true;
        }
        function get()
        {
            foreach($this->indexFields as $key=>$value)
            {
                $result[$key]=$value->get();
            }
            return $result;
        }        
        function serialize($serializer)
        {
            foreach($this->indexFields as $key=>$value)
            {
                
                $val=$value->serialize($serializer);
                
                if(is_array($val))
                {
                    foreach($val as $key2=>$value2)
                        $results[$key2]=$value2;
                }
                else
                    $results[$key]=$val;
            }
            
            return $results;
        }
        // Horrible way to do this, but not a lot of alternatives
        function assignAutoincrement($value)
        {
            foreach($this->indexFields as $key=>$field)
            {
                $fType=$field->getType();
                if(is_a($fType,'\lib\model\types\AutoIncrement'))
                {
                    // Important to note: This is done accessing the underlying type directly, on purpose, to avoid
                    // the Field to become dirty.
                    $fType->set($value);
                    $this->model->__setRaw($key,$value);
                }
            }
        }
        function __toString()
        {
            $k=0;
            $result="";
            foreach($this->indexFields as $key=>$field)
            {
                if($k>0)
                    $result.="#";
                $fType=$field->getType();
                if($fType->hasValue())
                    $result.=$fType->getValue($value);
                $k++;
            }
            return $result;
        }
        function isDirty()
        {
             foreach($this->indexFields as $key=>$val)
             {
                 debug_plain($this->model->__getObjectName()."-----".$key.":::".$val->isDirty());
                 if($val->isDirty())return true;
             }
             return false;
        }

}
