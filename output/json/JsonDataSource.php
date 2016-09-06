<?php
namespace lib\output\json;
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 22/07/13
 * Time: 18:24
 * To change this template use File | Settings | File Templates.
 */
class JsonDataSource  {
    var $object;
    var $name;
    var $params;
    var $extraParams;
    var $role;
    var $filteringDatasources;
    function __construct($object,$name,$params=null,$extraParams=null,$role=null)
    {
        $this->object=$object;
        $this->name=$name;
        $this->params=$params;
        $this->extraParams=$extraParams;
        $this->role=$role;
    }
    function setParameters($params)
    {
        $this->params=$params;
    }
    function setFilteringDatasources($fds)
    {
        $this->filteringDatasources=$fds;
    }
    function execute()
    {
        $result=array();
        $obj=\lib\datasource\DataSourceFactory::getDataSource($this->object,$this->name);
        $dsDefinition=$obj->getOriginalDefinition();

        if ($this->filteringDatasources) {
            $obj->setFilteringDatasources($this->filteringDatasources);
        }

        if($this->params)
        {
            if(is_array($this->params))
            {
                foreach($this->params as $key=>$value)
                {
                    if(isset($dsDefinition["PARAMS"][$key]))
                        $obj->{$key}=$value;
                }
            }
            else {
                $obj->setParameters($this->params, $this->extraParams);
            }
        }
        $it=$obj->fetchAll();
        $result["count"]=$obj->count();
        $result["result"]=1;
        $result["error"]=0;
        // Se obtiene la metadata
        include_once(LIBPATH."/reflection/Meta.php");
        $oMeta=new \DataSourceMetaData($this->object,$this->name);
        $result["definition"]=$oMeta->definition;
        if($this->role=="view")
        {
            $data=$it->getFullRow();
            if(!$data)
            {
                $result["data"]=null;
                $result["error"]=1;
                $result["result"]=0;
                $result["message"]="Object not found";
                $result["count"]=0;
            }
            else
                $result["data"]=$data;
        }
        else
        {
            $result["data"]=$it->getFullData();
        }
        if(!is_a($obj,'\lib\datasource\MultipleDataSource'))
        {
            $result["start"]=$obj->getStartingRow();
            $result["end"]=$result["start"]+$it->count();
        }
        return json_encode($result);
    }
}
