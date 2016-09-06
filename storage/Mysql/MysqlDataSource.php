<?php
namespace lib\storage\Mysql;
include_once(LIBPATH."/datasource/DataSource.php");
    class MysqlDataSource extends \lib\datasource\StorageDataSource
    {
        var $nRows=0;
        var $matchingRows=0;
        var $reindexArray=array();
        var $joinBy;
        var $usingParsed=false;

        function __construct($objName,$dsName,$definition,$serializer,$serializerDefinition=null)
        {

            if($serializerDefinition)
                $this->serializerDefinition=$serializerDefinition;
            $this->serializer=$serializer;
            \lib\datasource\StorageDataSource::__construct($objName,$dsName,$definition,$serializer);
            // Se parsean los "using"



        }
        function parseUsing()
        {
            if($this->usingParsed)
                return;
            $this->usingParsed=true;
	    if(!isset($this->serializerDefinition["DEFINITION"]["USING"]))
		return;
            foreach($this->serializerDefinition["DEFINITION"]["USING"] as $key=>$value)
            {
                $subDs=\getDataSource($value["MODEL"],$value["DATASOURCE"]);
                if(isset($value["PARAMS"]))
                {
                    foreach($value["PARAMS"] as $key1=>$value1)
                    {
                        $subDs->{$key1}=$this->parseString($value1,null);
                    }
                }
                $subQ=$subDs->getBuiltQuery(false);
                // reemplazamos sobre la marcha.
                $this->serializerDefinition["DEFINITION"]["BASE"]=str_replace("[%".$key."%]",$subQ,$this->serializerDefinition["DEFINITION"]["BASE"]);
            }
        }
        function addConditions($conds)
        {
            $conds=array_map(function($it){return array("FILTER"=>$it);},$conds);
            if(!$this->serializerDefinition["DEFINITION"]["CONDITIONS"])
                $this->serializerDefinition["DEFINITION"]["CONDITIONS"]=$conds;
            else
                $this->serializerDefinition["DEFINITION"]["CONDITIONS"]=array_merge($this->serializerDefinition["DEFINITION"]["CONDITIONS"],$conds);
        }
        function getBuiltQuery($getRows=true)
        {
            return $this->serializer->buildQuery($this->serializerDefinition["DEFINITION"], $this->parameters?$this->parameters:$this, $this->pagingParameters,$getRows);
        }

        function doFetch()
        {
            
            // Chequear aqui la cache.
            if($this->isLoaded())
                return;
            $this->parseUsing();
            $this->serializer->fetchAll($this->serializerDefinition["DEFINITION"],$this->data,$this->nRows,$this->matchingRows,$this->parameters?$this->parameters:$this,$this->pagingParameters);
            $this->matchingRows=intval($this->matchingRows);
            // TODO: Faltan columns y metadata            
            $this->iterator=new \lib\model\types\DataSet(array("FIELDS"=>$this->__returnedFields),$this->data,$this->nRows, $this->matchingRows,$this,$this->mapField);
            //$this->iterator=new \lib\datasource\StorageTableDataSet($this, $this->data,null,null,$this->nRows,$this->matchingRows,$cols?$cols[0]:null);
            $this->__loaded=true;            
            return $this->iterator;            
        }
        function fetchCursor()
        {
            // Chequear aqui la cache.
            if($this->isLoaded())
                return;
            $this->parseUsing();
            $this->__loaded=true;
            return $this->serializer->fetchCursor($this->serializerDefinition["DEFINITION"],$this->data,$this->nRows,$this->matchingRows,$this->parameters?$this->parameters:$this,$this->pagingParameters);
        }
        function next()
        {
            return $this->serializer->next();
        }
        function setEmpty()
        {
            $this->__loaded=true;
            $this->iterator=new \lib\model\types\DataSet(array("FIELDS"=>$this->__returnedFields),array(),0, 0,$this,$this->mapField);
        }
        function fetchGrouped($groupingField=null,$groupingParam=null)
        {
            // Para crear agrupaciones,basadas en 1 campo, necesitamos tanto el campo,como el modo de agrupacion.
            // Si el tipo de agrupacion es DISCRETE, es,teoricamente, el caso simple.
            // Si es discreto, y el campo agrupado NO es una relacion, no hay que hacer nada.Se agrupa en la query base, y listo.
            // Si es discreto, y el campo base es una relacion, hay que hacer un LEFT JOIN con la tabla remota, teniendo en cuenta
            // que hay que derivar la etiqueta a mostrar en las agrupaciones de la tabla remota (no agrupar con los id's que son la
            // relacion.
            // Si es continuo, hay que agrupar segun el rango (via division), y luego relacionar con la tabla helper entre el minimo y el maximo.
            // Si es DATETIME, hay que agrupar segun HOUR,DAY,WEEK,MONTH,YEAR
            // Por ahora vamos a hacer la burrada de seleccionar, y sobre la seleccion, agrupar.No es optimo, pero por ahora va a ser asi.
            // Atencion, no se incluyen paging parameters.
            $baseQuery=$this->serializer->buildQuery($this->serializerDefinition["DEFINITION"],$this->parameters?$this->parameters:$this,null,false);
            $groupedQuery="SELECT COUNT(*) as N,";
            $groups=array();
            $iteratorDefinition=array(
                "N"=>array("TYPE"=>"Integer")
            );
            if($groupingField)
            {
                $value=$this->originalDefinition["FIELDS"][$groupingField];
                if(!$groupingParam)
                    $range=$value["DEFAULT_GROUPING"];
                else
                    $range=$groupingParam;

                switch($value["GROUPING"])
                {
                    case "CONTINUOUS":
                    {
                        $groups[]=$range."*($groupingField DIV $range) as $groupingField";
                        $groupExpression=" GROUP BY ($groupingField DIV $range)";
                    }break;
                    case "DISCRETE":
                    {
                        $groups[]=$groupingField;
                        $groupExpression=" GROUP BY $groupingField";
                    }break;
                    case "DATETIME":
                    {
                        switch($range)
                        {
                            case "MONTHYEAR":
                            {
                                $groups[]="STR_TO_DATE(CONCAT('01-',MONTH($groupingField),'-',YEAR($groupingField)),'%d-%m-%Y') as $groupingField";
                                $groupExpression=" GROUP BY YEAR($groupingField),MONTH($groupingField)";
                            }break;
                            case "DATE":
                            {
                                $groups[]="DATE($groupingField) as $groupingField";
                                $groupExpression=" GROUP BY DATE($groupingField)";
                            }break;

                            default:
                            {
                                $func=$range;
                                $groups[]=$func."(".$groupingField.") as $groupingField";
                                $groupExpression=" GROUP BY $func($groupingField)";
                            }
                        }
                    }break;
                }
                $groupingDef=$value;
                $iteratorDefinition[$groupingField]=$value;

            }

                foreach($this->originalDefinition["FIELDS"] as $key=>$value)
                {
                    if($key!=$groupingField)
                    {
                        if($value["ALLOW_SUM"])
                        {
                            $groups[]="SUM(".$key.") as $key";
                            $iteratorDefinition[$key]=$value;
                        }
                    }
                }


            $nR=0;
            $fullQuery=$groupedQuery.implode(",",$groups)." FROM (".$baseQuery.") q ";
            //echo $fullQuery." ".$groupExpression;
            if(!$groupingField)
            {
                // Si no hay agrupacion, simplemente se ejecuta la query, y se devuelven los resultados.
                $data=$this->serializer->getConnection()->selectAll($fullQuery, $nR);
                return new \lib\model\types\DataSet(array("FIELDS"=>$iteratorDefinition),$data,$nR, $nR,$this,null);

            }
            // Si hay agrupacion, hay que ver con que rango hay que comparar la query inicial, con la tabla de rangos.
            $this->serializer->getConnection()->doQ("DROP TABLE IF EXISTS GroupedData");
            $fullQuery="CREATE TABLE GroupedData AS ".$fullQuery.$groupExpression;
            $this->serializer->getConnection()->doQ($fullQuery);
            $q="SELECT MIN($groupingField) as ming,MAX($groupingField) as maxg FROM GroupedData";

            $info=$this->serializer->getConnection()->selectAll($q,$nR);
            $min=$info[0]["ming"];
            $max=$info[0]["maxg"];

            $n=0;
            $fieldExpr="";
            foreach($iteratorDefinition as $key=>$value)
            {
                if($n>0)
                    $fieldExpr.=",";
                $n++;
                $fieldExpr.="IF($key IS NULL,0,$key) as $key";
            }


            // Se tiene que calcular cuantos datos hay que obtener de la tabla numerica.Es decir, cuantos
            // subrangos hay entre el minimo y el maximo.
            switch($groupingDef["GROUPING"])
            {
                case "CONTINUOUS":
                {
                    if($groupingDef["GROUP_FROM"])
                        $min=max($min,$groupingDef["GROUP_FROM"]);
                    if($groupingDef["GROUP_UNTIL"])
                        $max=min($max,$groupingDef["GROUP_UNTIL"]);

                    $start=floor($min/$range);
                    $end=ceil($max/$range);


                    $distance=$end-$start;
                    $q="SELECT $range*idx as x,$fieldExpr FROM indexHelper LEFT JOIN GroupedData on $range*idx=$groupingField WHERE idx>=$start AND idx <= $distance";
                    $iteratorDefinition["x"]=$iteratorDefinition[$groupingField];
                }break;
                case "DISCRETE":
                {
                    $q="SELECT $groupingField as x,$fieldExpr FROM GroupedData";
                    $iteratorDefinition["x"]=$iteratorDefinition[$groupingField];
                }break;
                case "DATETIME":
                {
                    switch($range)
                    {
                        case "MONTHYEAR":
                        {
                            $q="SELECT UNIX_TIMESTAMP(DATE_ADD('$min',INTERVAL idx MONTH)) as x, $fieldExpr FROM indexHelper LEFT JOIN GroupedData ON DATE_ADD('$min',INTERVAL idx MONTH)=$groupingField WHERE DATE_ADD('$min',INTERVAL idx MONTH)<='$max'";
                            $iteratorDefinition["x"]=array("TYPE"=>"Date");
                        }break;
                        case 'DATE':
                        {
                            $q="SELECT UNIX_TIMESTAMP(DATE_ADD('$min',INTERVAL idx DAY)) as x, $fieldExpr FROM indexHelper LEFT JOIN GroupedData ON DATE_ADD('$min',INTERVAL idx DAY)=$groupingField WHERE DATE_ADD('$min',INTERVAL idx DAY)<='$max'";
                            $iteratorDefinition["x"]=array("TYPE"=>"Date");
                        }break;
                        default:
                            {
                                switch($range)
                                {
                                    case "HOUR":{$min=0;$max=23;}break;
                                    case "DAY":{$min=1;$max=31;}break;
                                }
                            $q="SELECT idx as x, $fieldExpr FROM indexHelper LEFT JOIN GroupedData ON idx=$groupingField WHERE idx<=$max";
                            $iteratorDefinition["x"]=array("TYPE"=>"Integer");
                            }
                    }
                    unset($iteratorDefinition[$groupingField]);
                }break;
            }
            $this->grouped=true;
            $data=$this->serializer->getConnection()->selectAll($q,$nR);
            $this->originalDefinition["FIELDS"]=$iteratorDefinition;
            $it=new \lib\model\types\DataSet(array("FIELDS"=>$iteratorDefinition),$data,$nR, $nR,$this,null);
            $it->setRange($min,$max);
            $this->iterator=$it;
            return $it;
        }

        function getIterator($rowInfo=null)
        {            
            if(!$this->iterator)
            {                                
                $this->iterator=$this->fetchAll();
            }            
            if($this->mapField)
            {            
                // TODO : Solo permite hacer join por el primer campo del joinBy
                //$keys=array_keys($this->joinBy);
                $this->iterator->setIndex($rowInfo[$this->parentField]);
            }
            return $this->iterator;            
        }
        function count(){
            return $this->matchingRows;
        }
        function countColumns(){}        
        function getMetaData(){}

        static function getRecursiveDsDescendantDsDefinition($tableName,$idField,$treeField,$treeValue,$idValue,$fieldList,$separator)
        {
            $q="SELECT ".implode(",",$fieldList)." FROM ".$tableName." WHERE ".$treeField." LIKE '".($treeValue?$treeValue:$separator).$idValue.$separator."%'";
            return array("DEFINITION"=>array("BASE"=>$q));
        }
        static function getRecursiveParentsDsDefinition($tableName,$idField,$treeField,$treeValue,$idValue,$fieldList,$separator)
        {
            if(!$treeValue)
                return array("DEFINITION"=>array("BASE"=>"SELECT * FROM ".$tableName." WHERE 1=-1"));

            $parts=explode($separator,trim($treeValue,$separator));
            $q="SELECT ".implode(",",$fieldList)." FROM ".$tableName." WHERE $idField IN (".implode(",",$parts).")";
            return array("DEFINITION"=>array("BASE"=>$q));
        }
        static function getRecursiveChildDsDefinition($tableName,$idField,$treeField,$treeValue,$idValue,$fieldList,$separator)
        {
            $q="SELECT ".implode(",",$fieldList)." FROM ".$tableName." WHERE ".$treeField."='".($treeValue?$treeValue:$separator).$idValue.$separator."'";
            return array("DEFINITION"=>array("BASE"=>$q));
        }
        static function getRecursiveRootsDsDefinition($tableName,$idField,$treeField,$treeValue,$idValue,$fieldList,$separator)
        {
            $q="SELECT ".implode(",",$fieldList)." FROM ".$tableName." WHERE ".$treeField." IS NULL";
            return array("DEFINITION"=>array("BASE"=>$q));
        }
        function setSerializer($serializer)
        {
            $this->serializer=$serializer;
        }
    }

