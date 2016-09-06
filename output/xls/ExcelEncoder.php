<?php
namespace lib\output\xls;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class XlsEncoder
{
    function encodeIterator($iterator,$isChildIterator)
    {
        $count=$iterator->count();
        $arr=array("nRows"=>$count,
                   "totalRows"=>$iterator->fullCount()                   
                  );
        $data=$iterator->getData();
        $subDs=$iterator->getSubDatasources();
        if(!$subDs)
        {
            $arr["DATA"]=$data;
            return $arr;
        }
        
        foreach($subDs as $key=>$value)
        {
            $this->encodeIterator($iterator->{$key},1);            
        }
            
    }
    function encodeDataSource($dsObject,$dsName,$param,$role)
    {        
         $obj=  \lib\datasource\DataSourceFactory::getDataSource($dsObject, $dsName);
         
         $obj->setParameters($param);
         $obj->fetchAll();
         $iterator=$obj->getIterator();
         $this->encodeIterator($iterator,0);
                             
         if($role=='view')
                $res["DATA"]=$obj->getIterator()->getRow();
         else
         {
                $res["DATA"]=$obj->getIterator()->getData();
         }
    }
}
?>
