<?php
namespace lib\datasource;
abstract class TableDataSet implements \ArrayAccess
{    
    abstract function setIndex($index);    
    abstract function count();    
    abstract function getRow();
    abstract function getField($varName);
    abstract function getColumn($col);
    
}

