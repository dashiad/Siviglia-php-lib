<?php
namespace lib\model\types;

class RowDataSet extends \lib\model\BaseTypedObject{
    var $tableDataSet;
    function __construct($definition,$dataSet)
    {
        $this->tableDataSet=$dataSet;
        parent::__construct($definition);
    }
    function __get($varName)
    {
                
        if(!$this->__fieldDef[$varName])
        {
            return $this->tableDataSet->getField($varName);
        }
        return parent::__get($varName);
    }
    function getRow()
    {
        return $this->tableDataSet->getRow();
    }

    function count()
    {
        return $this->tableDataSet->count();
    }
}

// Extiende BaseType solo nominalmente...En realidad, es una clase distinta.
/*
    Definicion de un dataset:
    array(
        "FIELDS"=>array($key=>array(...))
    )    
 
 
*/
class DataSetType extends BaseType implements \ArrayAccess // TableDataSet
{
    var $data;
    var $definition;
    var $parentDs;
    var $count;
    var $fullCount;
    var $subDs;
    var $reIndexParams;
    var $reIndexData;
    var $currentIndex=0;
    var $currentOffset=0;
    var $mappedOffset;
    var $rowSet;
    var $rangeStart=0;
    var $rangeEnd=0;

    function __construct($definition,$value=null,$count=-1,$fullCount=-1,$parentDs=null,$reIndexField=null)    
    {
        $this->definition=$definition;      
        $this->parentDs=$parentDs;    
        
        if($count == -1)
        {
           $this->valueSet=false;
           return;
        }
        
        $this->initialize($value,$count,$fullCount,$reIndexField);
        $this->rowSet=new RowDataSet($definition,$this);
        $this->setFlags(BaseType::TYPE_NOT_EDITABLE);        
    }        
    function initialize($value,$count,$fullCount,$reIndexField=null)
    {
        $this->value=$value;
        $this->count=$count;
            $this->valueSet=true;
        $this->fullCount=$fullCount;
        $this->reIndexField=$reIndexField;
        $this->currentIndex=0;
        $this->currentOffset=0;
        if($reIndexField)
        {
            $this->rebuildIndexes();
        }

    }
    function getDataSource()
    {
        return $this->parentDs;
    }
    function setValue($value)
    {
     
           
        if(!is_array($value))
        {
            if($value==null)
                $this->value=null;
            else
                throw new BaseTypeException(BaseTypeException::ERR_INVALID);
        }
        else
        {
            $nValues=count($value);
            $this->initialize($value,$nValues,$nValues,null);
        }
        $this->valueSet=true;
    }      

    function validate($value)
    {                       
            return true;                                            
    }
    function postValidate($value)
    {
            return true;
    }

    function hasValue()
    {
          return $this->valueSet;
    }
    function hasOwnValue()
    {
          return $this->valueSet;
    }
    function copy($type)
    {
        $this->value=$type->value;
        $this->count=$type->count;
        $this->valueSet=true;
        $this->fullCount=$type->fullCount;
        $this->parentDs=$type->parentDs;        
        $this->reIndexField=$type->reIndexField;
        $this->definition=$type->definition;
    }

    function getDiff($value)
    {          
        if($value->count!=$this->count || $value->fullCount!=$this->fullCount)
            return false;

        $localDef=$this->definition;
        $remoteDef=$value->definition;
        if($localDef!=$remoteDef)
            return false;
        if($localDef)
        {
                if(count(array_diff($localDef,$remoteDef)))
                    return false;
        }
        for($k=0;$k<$this->count;$k++)
            $checkSum[implode("#@#",array_values($this->value[$k]))]=$k;
        foreach($value->value as $curRow)
        {
            $curCad=implode("#@#",array_values($curRow));
            if(!isset($checkSum[$curCad]))
                return false;
            unset($checkSum[$curCad]);
        }
        return $checkSum;
    }

    function equals($value)
    {
        return count($this->getDiff($value))==0;
    }

    function is_set()
    {         
          return true;
    }

    function clear()
    {
        $this->valueSet=true;
        $this->initialize(array(),0,0,null);
    }
        
    function getValue()
    {
          if($this->valueSet)
            return $this->value; 
          if($this->hasDefaultValue())
            return $this->getDefaultValue();
          return null;          
    }
    function __toString()
    {
          
          if(!$this->valueSet)
          {
              return "";
          }
          return (string)$this->value;
    }
    function hasDefaultValue()
    {
          return isset($this->definition["DEFAULT"]);
    }
    function getDefaultValue()
    {
          return $this->definition["DEFAULT"];
    }
    function getRelationshipType()
    {
        // TODO : Esto no tiene sentido (no tiene relationshiptype).
          return $this;
    }
    function getDefinition()
    {
          if(!$this->definition["TYPE"])
          {
              $parts=explode("\\",get_class($this));
              $this->definition["TYPE"]=$parts[count($parts)-1];
          }
          return $this->definition;
    }
    function isEmpty()
    {
          return $this->count <=0;
    }

    function getData()
    {
        return $this->value;
    }
    function getFullData()
    {

        if($this->reIndexField)
        {
            $n=count($this->reIndexData[$this->currentIndex]);
            for($k=0;$k<$n;$k++)
            {
                $data[]=$this->value[$this->reIndexData[$this->currentIndex][$k]];
            }
        }
        else
            $data=$this->getData();

        if(!$data)
            return array();
        $ds=$this->getDataSource();
        if(!$ds)
            return $data;
        $params=$ds->getPagingParameters();
        $autoInclude="";
        if($params)
        {
            $autoInclude=$ds->getPagingParameters()->__autoInclude;
        }
        if($autoInclude=="")
            return $data;

        $includes=explode(",",$autoInclude);

        foreach($data as $key=>$value)
        {
            for($k=0;$k<count($includes);$k++)
            {

                $cI=$includes[$k];
                $data[$key][$cI]=array();
                $n=$this[$k]->{$cI}->count();
                $q=$this[$k]->{$cI};
                for($j=0;$j<$n;$j++)
                {
                    $row=$this[$key]->{$cI}->getRow($j);
                    $data[$key][$cI][]=$row;
                }
            }
        }
        return $data;
    }

    function rebuildIndexes()
    {       
        for($k=0;$k<$this->count;$k++)
        {
            $val=$this->value[$k][$this->reIndexField];
            $this->reIndexData[$val][]=$k;
        }
    }
    function setIndex($index)
    {
        if($this->reIndexField && $this->currentIndex!==$index)
        {            
            $this->currentIndex=$index;
            $this->currentOffset=0;
            $this->mappedOffset=$this->reIndexData[$index][0];
        }
    }
    function count()
    {
        if($this->reIndexField==null)
            return $this->count;
        if($this->currentIndex)
            return count($this->reIndexData[$this->currentIndex]);
        return 0;
    }
    function fullCount()
    {
        return $this->fullCount;
    }
    function getRow($index=null)
    {
        if($index)
        {
            $this->offsetGet($index);
        }
        if($this->reIndexField==null)
            return $this->value[$this->currentOffset];
        return $this->value[$this->mappedOffset];
    }
    function getFullRow()
    {
       return $this->getRow();       
    }
    function getSubDataSources()
    {
        return $this->subDs;
    }
    
    function getField($varName)
    {
        
       if($this->reIndexField)
             $offset=$this->mappedOffset;
       else
           $offset=$this->currentOffset;       
       
        if(array_key_exists($varName,$this->value[$offset]))
        {
                return $this->value[$offset][$varName];
        }

        if($this->subDs[$varName])
        {
            $it=$this->subDs[$varName]->getIterator($this->value[$offset]);
            return $it;
        }
        $sDs=$this->parentDs->getSubDataSource($varName);
        if($sDs)
        {
            $this->subDs[$varName]=$sDs;
        }
        return $sDs->getIterator($this->value[$offset]);
    }


    function getColumn($col)
    {
        $results=array();
        for($k=0;$k<$this->count;$k++)
            $results[]=$this->value[$k][$col];
        return $results;
    }
    function offsetExists($index)
    {
        if($this->reIndexField)
        {
            return $index < count($this->reIndexData[$this->currentIndex]);
        }
        return $index < $this->count;
    }
    function offsetGet($index)
    {        
        if($this->reIndexField)
        {            
            $this->mappedOffset=$this->reIndexData[$this->currentIndex][$index];
            $this->rowSet->loadFromArray($this->value[$this->mappedOffset],$this->parentDs->getSerializer()->getSerializerType());
        }
        else
        {
            $this->currentOffset=$index;                        
            //if($this->parentDs)
            //{
                $this->rowSet->loadFromArray($this->value[$this->currentOffset],$this->parentDs->getSerializer()->getSerializerType());
            //}
        }
        return $this->rowSet;
    }
    function offsetSet($index,$newVal)
    {
    }
    function offsetUnset($index)
    {

    }
    function setRange($min,$max)
    {
        $this->rangeStart=$min;
        $this->rangeEnd=$max;
    }
    function getRange(&$min,&$max)
    {
        $min=$this->rangeStart;
        $max=$this->rangeEnd;
    }
}


