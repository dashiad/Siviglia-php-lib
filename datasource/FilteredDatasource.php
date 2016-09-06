<?php
namespace lib\datasource;

class FilteredDatasourceIterator
{

}

class FilteredDatasource {
    var $definition;
    var $params;
    var $filteringDatasources;
    var $pagingParameters;
    var $filteringParameters;
    var $objName;
    var $dsName;

    function __construct($objName,$dsName,$definition)
    {
        $this->objName = $objName;
        $this->dsName = $dsName;
        $this->definition=$definition;
        $this->params=null;
        $this->filteringParameters=array();

        $pagingParams=array(
            "__start"=>array("TYPE"=>"Integer"),
            "__count"=>array("TYPE"=>"Integer"),
            "__sort"=>array("TYPE"=>"String"),
            "__sortDir"=>array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC"),
            "__sort1"=>array("TYPE"=>"String"),
            "__sortDir1"=>array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC"),
            "__group"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupParam"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupMin"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupMax"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__accumulated"=>array("TYPE"=>"Boolean"),
            "__partialAccumul"=>array("TYPE"=>"Boolean"),
            "__autoInclude"=>array("TYPE"=>"String")
        );

        if(!isset($this->originalDefinition["PARAMS"]))
            $this->originalDefinition["PARAMS"]=$pagingParams;
        else
            $this->originalDefinition["PARAMS"]=array_merge_recursive($this->originalDefinition["PARAMS"],$pagingParams);

        foreach($this->originalDefinition["PARAMS"] as $key=>& $value)
        {
            if(!isset($value["DEFAULT"]))
                $value["DEFAULT"]=null;
        }
        $this->pagingParameters=new \lib\model\BaseTypedObject(array(
            "FIELDS"=>$pagingParams
        ));
    }

    function setParameters($params, $extraParams)
    {
        $this->params=$params;

        //Vemos si se setean filtering datasources desde la URL
        if ($extraParams['filtering_datasources']) {
            $this->setFilteringDatasources($extraParams['filtering_datasources']);
        }
    }

    function setFilteringDatasources($fd)
    {
        foreach($fd as $key=>$value) {
            $this->filteringDatasources[$key]=array(
                'DATASOURCE'=>\lib\datasource\DataSourceFactory::getDataSource($value["OBJECT"],$value["DATASOURCE"]),
                'DEFINITION'=>$fd[$key]
            );
        }
    }

    function fetchAll()
    {
        $base = $this->definition['STORAGE']['MYSQL']['DEFINITION']['BASE'];
        if (!$base) {
            throw new \RuntimeException('Filtered Datasource, BASE not found!');
        }

        if ($this->filteringDatasources) {
            foreach($this->filteringDatasources as $fd=>$fdDef) {
                $fds = $fdDef['DATASOURCE'];
                if ($this->filteringParameters) {
                    foreach($this->filteringParameters as $fpk => $fpv) {
                        $fds->{$fpk}=$this->filteringParameters[$fpk];
                    }
                }

                $pagingFields=$this->pagingParameters->__getFields();
                $pagingKeys=array_keys($pagingFields);
                foreach($pagingKeys as $pk) {
                    $key = $pk;
                    try {
                        $value = $this->params->{$pk};
                    }
                    catch(\Exception $e) {
                        $value = $this->pagingParameters->{$pk};
                    }
                    $fds->{$pk}=$value;
                }
                $fQuery = $fds->getBuiltQuery(false);
                $base = str_replace('{$'.$fd.'$}', $fQuery, $base);
            }
        }
        $this->definition['STORAGE']['MYSQL']['DEFINITION']['BASE'] = $base;

        //Si queda algún fitering datasource sin sustituir tenemos que dar error,
        //ya que no es sencillo devolver algo inocuo para que no falle la consulta
        //teniendo en cuenta que el join está definido en la query externa
        if (preg_match('/{\$.*\$}/', $base)) {
            throw new \RuntimeException('There is at least one filtering datasource without substitution');
        }

        $mainDef=$this->definition;
        $storageKeys = array_keys($mainDef['STORAGE']);
        $serType = ucfirst(strtolower($storageKeys[0]));

        include_once(LIBPATH."/storage/".$serType."/".$serType."DataSource.php");

        $dsN='\\lib\\storage\\'.$serType.'\\'.$serType.'DataSource';
        $mainDs=new $dsN($this->objName,$this->dsName,$mainDef,null,$mainDef["STORAGE"][strtoupper($serType)]);

        $pFields = $this->params->__getFields();
        $pKeys = array_keys($pFields);
        foreach($pKeys as $pk) {
            $mainDs->{$pk}=$this->params->{$pk};
        }

        return $mainDs->doFetch();
    }

    function count()
    {
        return 0;
    }

    function getOriginalDefinition()
    {
        return $this->originalDefinition;
    }
    function getDefinition()
    {
        return $this->definition;
    }

    function getStartingRow()
    {
        return 0;
    }
    function __set($varName,$varValue)
    {
        if(!$this->params)
            $this->createParamsObject();

        try {
            $this->params->{$varName}=$varValue;

            //Lo metemos también en filteringParameters para tener todos los parámetros, tanto
            //los propios como los internos centralizados en un único sitio.
            $this->filteringParameters[$varName]=$varValue;
        }
        catch(\Exception $e)
        {
            //Si es un parámetro de paginación se mete, si no debe ser
            //un parámetro para el datasource interno
            $pagingFields=$this->pagingParameters->__getFields();
            $pagingKeys=array_keys($pagingFields);
            if (in_array($varName, $pagingKeys)) {
                $this->pagingParameters->{$varName}=$varValue;
            }
            else {
                $this->filteringParameters[$varName]=$varValue;
            }
        }
    }

    function createParamsObject()
    {
        $paramDef=array("FIELDS"=>(isset($this->definition["PARAMS"])?$this->definition["PARAMS"]:array()));
        $this->params=new \lib\model\BaseTypedObject($paramDef);
    }
}