<?php
  namespace lib\datasource;
  include_once(LIBPATH."/datasource/DataSource.php");
  include_once(LIBPATH."/datasource/TableDataSet.php");
  class ArrayDataSource extends TableDataSource
  {
        protected $objName;
        protected $dsName;
        protected $originalDefinition=null;
        protected $value;
        protected $nItems;
        protected $dataSet;
        protected $iterator;
        protected $columnNames;
        protected $metaData;
        function __construct($objName,$dsName,$definition)
        {
            $this->objName = $objName;
            $this->dsName = $dsName;
            $this->originalDefinition=$definition;

            /*
            $this->columnNames=$columnNames;
            $this->value=$values;
            $this->nItems=count($values);
            $this->metaData=$metaData;
            */

        }
        function getOriginalDefinition()
        {
            return $this->originalDefinition;
        }
        function fetchAll(){ return $this->getIterator();}
        function getIterator($rowInfo=null)
        {
            if( !$this->iterator )
            {
                $this->iterator= new ArrayDataSet($this,$this->value,$this->columnNames,$this->nItems);
            }
            return $this->iterator;
                
        }
        function count()
        {
           return $this->nItems;     
        }
        function countColumns()
        {
                return count($this->columnNames);
        }
        function getMetaData()
        {
                return $this->metaData;
        }
  }

  class VectorDataSource extends ArrayDataSource
  {
      const KEYS_AS_COLUMNVALUES=1;
      const KEYS_AS_COLUMNNAMES=2;

       function __construct($vector,$type,$columnNames,$metaData=null)
       {

           if( $type==VectorDataSource::KEYS_AS_COLUMNNAMES )
           {
               ArrayDataSource::__construct(array(array_values($vector)),array_keys($vector),$metaData);
           }
           else
           {
               foreach($vector as $key=>$value)
                   $results[]=array($key,$value);

               ArrayDataSource::__construct($results,$columnNames,$metaData);

           }
          
       }       
  }
  
  
  class ArrayDataSet extends TableDataSet
  {
   
      var $parentDs;
      var $data;
      var $currentIndex=0;

      function __construct($parentDs,$data,$columns,$count)
      {
          $this->parentDs=$parentDs;
          $this->invColumns=array_flip($columns);
          $this->data=$data;
          $this->columns=$columns;
          $this->count=$count;
          $this->rowSet=new ArrayTableRowDataSet($this);
      }

    
      function setIndex($index)
      {
          $this->currentIndex=$index;

      }
      function count()
      {
          return $this->count;
      }
      function getRow()
      {
          return $this->data[$this->currentIndex];
      }
      function getField($varName)
      {
          $val=$this->data[$this->currentIndex][$this->invColumns[$varName]];
          return $val;
      }

      function getColumn($col)
      {
          for( $k=0;$k<$this->count;$k++)
          {
              $results[]=$this->data[$k][$this->invColumns[$col]];
          }
          return $results;
      }

      function offsetExists($index)
      {
         
        return $index < $this->count;
      }

      function offsetGet($index)
      {
            $this->currentIndex=$index;
            return $this->rowSet;
      }
      function offsetSet($index,$newVal)
      {
      }
      function offsetUnset($index)
      {

      }
      function __get($varName)
      {
          $this->currentIndex=0;
          return $this->getField($varName);
      }
    
}

  class ArrayTableRowDataSet {
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
