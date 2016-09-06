<?php
namespace lib\datasource;


class StorageTableDataSet extends TableDataSet
{
    var $data;
    var $parentDs;
    var $count;
    var $fullCount;
    var $columns;
    var $subDs;
    var $metaData;
    var $reIndexParams;
    var $reIndexData;
    var $currentIndex=0;
    var $currentOffset=0;
    var $mappedOffset;
    var $rowSet;

    function __construct(& $parentDs, & $data,$columns,$metaData,$count,$fullCount=-1,$reIndexField=null)
    {
        $this->parentDs=$parentDs;
        $this->data=& $data;
        $this->columns=& $columns;
        $this->metaData=& $metaData;
        $this->count=$count;
        $this->fullCount=$fullCount;
        $this->reIndexField=$reIndexField;
        if($reIndexField)
        {
            $this->rebuildIndexes();
        }
       // $this->rowSet=new StorageTableRowDataSet($this);
        //$this->rowSet=new \lib\model\BaseTypedObject($metaData)
    }
    function getData()
    {
        return $this->data;
    }
    function getDataSource()
    {
        return $this->parentDs;
    }
    function getFullData()
    {
        return $this->getData();
    }
    function rebuildIndexes()
    {       
        for($k=0;$k<$this->count;$k++)
        {
            $val=$this->data[$k][$this->reIndexField];
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
    function getRow()
    {
        if($this->reIndexField==null)
            return $this->data[$this->currentOffset];
        return $this->data[$this->mappedOffset];
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
       
        if(array_key_exists($varName,$this->data[$offset]))
        {
                return $this->data[$offset][$varName];
        }

        if($this->subDs[$varName])
        {
            $it=$this->subDs[$varName]->getIterator($this->data[$offset]);
            return $it;
        }
        $sDs=$this->parentDs->getSubDataSource($varName);
        if($sDs)
        {
            $this->subDs[$varName]=$sDs;
        }
        return $sDs->getIterator($this->data[$offset]);
    }

    function getColumn($col)
    {
        $results=array();
        for($k=0;$k<$this->count;$k++)
            $results[]=$this->data[$k][$col];
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
        }
        else
            $this->currentOffset=$index;
        return $this->rowSet;
    }
    function offsetSet($index,$newVal)
    {
    }
    function offsetUnset($index)
    {

    }
}

class StorageTableRowDataSet {
    var $tableDataSet;
    function __construct($tableDataSet)
    {
        $this->tableDataSet=$tableDataSet;
    }
    function __get($varName)
    {
        return $this->tableDataSet->getField($varName);
    }
}
?>
