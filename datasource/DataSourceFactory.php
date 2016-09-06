<?php
namespace {
include_once(PROJECTPATH."/lib/datasource/DataSource.php");

function getDataSource($objName,$dsName,$serializer=null)
{
    return \lib\datasource\DataSourceFactory::getDataSource($objName,$dsName,$serializer);
}
function applyDataSource($objName,$dsName,$params,$callable,$dsParams=null,$serializer=null)
{
    return \lib\datasource\DataSourceFactory::applyDataSource($objName,$dsName,$params,$callable,$dsParams,$serializer);
}

}
namespace lib\datasource {
class DataSourceFactory
{
        static function getDataSource($objName,$dsName,&$serializer=null)
        {
                /*
                if(!$serializer)
                {
                    $instance=\lib\model\BaseModel::getModelInstance($objName);
                    $serializer=$instance->__getSerializer();
                }
                $serType=ucfirst(strtolower($serializer->getSerializerType()));
                */

                $objNameClass=new \lib\reflection\model\ObjectDefinition($objName);
            if($dsName=="")
            {
                $a=10;
                $b=20;
            }
                require_once($objNameClass->getDataSourceFileName($dsName));
                $objLayer=$objNameClass->layer;
                $objName=$objNameClass->getNamespaced();
                $csN=$objName.'\datasources\\'.$dsName;
                
                if(!class_exists($csN))
                {
                    throw new DataSourceException(DataSourceException::ERR_NO_SUCH_DATASOURCE,array("object"=>$objName,"datasource"=>$dsName));
                }
                $instance=new $csN();
                if(is_a($instance,'\lib\datasource\MultipleDatasource'))
                    return $instance;
                if(is_a($instance,'\lib\datasource\FilteredDatasource'))
                    return $instance;
                $mainDef=$csN::$definition;
                if(!isset($mainDef["STORAGE"]))
                    return $instance;

                $storageKeys = array_keys($mainDef['STORAGE']);
                if(!$serializer && count($storageKeys) !== 1) {
                    $instance=\lib\model\BaseModel::getModelInstance($objName);
                    $serializer=$instance->__getSerializer();
                    $serType=ucfirst(strtolower($serializer->getSerializerType()));
                }
                else {
                    $serType = ucfirst(strtolower($storageKeys[0]));
                }

                include_once(LIBPATH."/storage/".$serType."/".$serType."DataSource.php");

                $dsN='\\lib\\storage\\'.$serType.'\\'.$serType.'DataSource';
                $mainDs=new $dsN($objName,$dsName,$mainDef,null,$mainDef["STORAGE"][strtoupper($serType)]);
                return $mainDs;
        }
        function applyDataSource($objName,$dsName,$params,$callable,$dsParams=null,$serializer=null)
        {
            $ds=\getDataSource($objName,$dsName);
            if(is_array($params))
            {
                foreach($params as $key=>$value)
                    $ds->{$key}=$value;
            }
            if($dsParams)
            {
                $pagingParameters=$ds->getPagingParameters();
                foreach($dsParams as $key=>$value)
                    $pagingParameters->{$key}=$value;
            }
            $ds->fetchAll();
            $n=$ds->count();
            $it=$ds->getIterator();
            for($k=0;$k<$n;$k++)
                $callable($it[$k],$k,$n,$ds);
        }
}
}

