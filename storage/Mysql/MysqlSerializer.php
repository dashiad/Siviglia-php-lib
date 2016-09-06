<?php

namespace lib\storage\Mysql;

class MysqlSerializerException extends \lib\model\BaseException
{

    const NO_ID_FOR_OBJECT = 1;
    const ERR_NO_CONNECTION_DETAILS = 2;
    const ERR_MULTIPLE_JOIN = 3;
    const ERR_NO_SUCH_OBJECT = 4;

}

class MysqlSerializer extends \lib\storage\StorageSerializer
{

    var $conn;
    var $currentDataSpace;
    var $storageManager;
    function __construct($definition,$useDataSpace=true)
    {

        if (!$definition["ADDRESS"])
            throw new MysqlSerializerException(MysqlSerializerException::ERR_NO_CONNECTION_DETAILS);
        $this->storageManager = new Mysql($definition["ADDRESS"]);
        $this->conn = $this->storageManager;
        $this->conn->connect();
        if($useDataSpace)
            $this->useDataSpace($definition["ADDRESS"]["database"]["NAME"]);

        \lib\storage\StorageSerializer::__construct($definition, "MYSQL");

    }

    function unserialize($object, $queryDef = null, $filterValues = null)
    {        
        $object->__setSerializer($this);
        if ($queryDef)
        {
            $object->__setSerializerFilters("MYSQL",array("DEF" => $queryDef, "VALS" => $filterValues));
            //$object->filters["MYSQL"] = array("DEF" => $queryDef, "VALS" => $filterValues);
            $queryDef["BASE"] = "SELECT * FROM " . $object->__getTableName();
            if(isset($queryDef["CONDITIONS"]))
            {
                $condKeys=array_keys($queryDef["CONDITIONS"]);
                $queryDef["BASE"].=" WHERE [%".implode("%] AND [%",$condKeys)."%]";
            }
        }
        else
        {
            //$q="SELECT * FROM ".$object->__getTableName()." WHERE ";	
            $queryDef = array("BASE" => array("*"),
                "TABLE" => $object->__getTableName(),
                "CONDITIONS" => $this->getIndexExpression($object));

            if (!$object->__getKeys())
                return false;
            $filterValues = null;
        }
        $qb = new QueryBuilder($queryDef, $filterValues);
        $q = $qb->build();

        $arr = $this->conn->select($q);

        if ($arr[0])
        {
            $fieldList = $object->__getFields();            
            foreach ($fieldList as $key => $value) 
            {
                $value->unserialize($arr[0],"MYSQL");            
            }
        }
        else
            throw new MysqlSerializerException(MysqlSerializerException::ERR_NO_SUCH_OBJECT, array("OBJECT" => $object->__getTableName()));
    }

    function getIndexExpression($object)
    {

        $keys = $object->__getKeys();
        if (!$keys)
            return null;
        $fields = $keys->serialize($this->getSerializerType());

        $expr = "";

        foreach ($fields as $key => $value)
        {
            $conditions[] = array(
                "FILTER" => array(
                    "F" => $key,
                    "V" => $value,
                    "OP" => "="
                )
            );
        }
        return $conditions;
    }

    function _store($object, $isNew, $dirtyFields)
    {
        $results = array();
        if($isNew)
            $tFields=$object->__getFields();
        else
            $tFields=$dirtyFields;
        foreach ($tFields as $key => $value)
        {
            if(!$value->is_set())
            {
                if(!isset($dirtyFields[$key]))
                {
                    continue;
                }
                if($isNew && $value->getType()->hasDefaultValue())
                    $value->getType()->setValue($value->getType()->getValue());
            }
            if(($isNew && ($value->getType()->getFlags() & \lib\model\types\BaseType::TYPE_SET_ON_SAVE)) || $value->isAlias())
                continue;

            $subVals = $value->serialize($this->getSerializerType());

            // Los tipos compuestos pueden devolver un array
            if (is_array($subVals))
            {
                if(count($subVals)==0)
                    $results[$key]='NULL';
                else
                {
                foreach($subVals as $resKey=>$resValue)
                    $results[$resKey]=($resValue===null?'NULL':$resValue);
                }
            }
            else
                 $results[$key] = ($subVals===null?'NULL':$subVals);            
        }        

       // Aunque $results sea cero, si es nuevo, se guarda.
        if(count($results)==0 && !$isNew)
            return;
        
        if ($isNew)
        {
            $id = $this->conn->insertFromAssociative($object->__getTableName(), $results);
            // Se busca algun campo que sea de tipo AutoIncrement, y se le asigna el valor.	
            // Se va a suponer que forma parte de la key..
            if ($id)
            {
                $key = $object->__getKeys();
                $key->assignAutoincrement($id);
            }
        }
        else
        {
            $conds = $this->getIndexExpression($object);
            
            if (!$conds)
                $conds = array();
            
            $filters = $object->__getFilter($this->getSerializerType());
            if ($filters)
            {
                $conds = array_merge($conds, $filters["DEF"]["CONDITIONS"]);
            }

            if (count($conds) == 0)
            {

                throw new MysqlSerializerException(MysqlSerializerException::NO_ID_FOR_OBJECT);
            }

            $builder = new QueryBuilder(array("BASE"=>array("*"),"CONDITIONS" => $conds), null);
            $q=$builder->build(true);
            if(strpos($q," WHERE ")===0)
                $q=substr($q,strlen(" WHERE "));

            if (is_string($results['value']) && $results['value'][0]!="'") {
                $results['value'] = "'".$results['value']."'";
            }
            $this->conn->updateFromAssociative($object->__getTableName(), $results, $q, false);
        }

        foreach ($dirtyFields as $key => $value)
            $value->onModelSaved();
    }

    function delete($table, $keyValues=null)
    {
        if (is_object($table))
        {
            $destTable = $table->__getTableName();
            $keyValues=array($table->__getKeys()->get());
        }
        else
            $destTable=$table;

        $q = "DELETE FROM $destTable WHERE ";
        $nVals = count($keyValues);
        for ($k = 0; $k < $nVals; $k++)
        {
            $parts = array();
            foreach ($keyValues[$k] as $key => $value)
            {                
                $parts[] = $key . "=" .$value;
            }
            $subParts[] = "(" . implode(" AND ", $parts) . ")";
        }
        $q.=implode(" OR ", $subParts);

        $this->conn->delete($q);
    }

    function add($table, $keyValues, $extraValues = null)
    {         
        if (is_object($table))
            $table = $table->__getTableName();

        $q = "INSERT INTO $table ";
        $nVals = count($keyValues);
        $inserts = array();
        for ($k = 0; $k < $nVals; $k++)
        {

            $vals = array();
            foreach ($keyValues[$k] as $key => $value)
            {
                if ($k == 0)
                    $fieldNames[] = $key;

                $vals[] = $value;
            }
            $inserts[] = "(" . implode(",", $vals) . ")";
        }

        $q.="(" . implode(",", $fieldNames) . ") VALUES " . implode(",", $inserts);
        
        $this->conn->insert($q);
    }

    function update($table, $keyValues, $fields)
    {
        $q = "UPDATE $table SET ";
        foreach ($fields as $key => $value)
        {
            // TODO : Eliminar el mysql_escape_string, cambiarlo por serializado
            $parts[] = $key . "='" . mysql_escape_string($value) . "'";
        }
        $q.=(implode(",", $parts) . " WHERE ");
        $parts = array();
        foreach ($keyValues as $key => $value)
        {
            $parts[] = $key . "='" . mysql_escape_string($value) . "'";
        }
        $q.=implode(" AND ", $parts);
        $this->conn->update($q);
    }
    // El primer parametro es la tabla
    // El segundo, es un array asociativo de tipo {clave_fija=>valor}.Son las columnas que indican la parte de la relacion fija, con su valor.
    // El tercero, es un array simple que indican los nombres de campo de la parte de relacion que estamos editando.
    // El cuarto, es un array con los valores a establecer.Este array es asociativo, y dentro de cada key, hay un array de valores.
    function setRelation($table,$fixedSide,$variableSides,$srcValues)
    {
        // Se tiene que crear una query de "DELETE" y otra de "INSERT IGNORE"
        $q="DELETE FROM ".$table." WHERE ";
        $n=0;
        foreach($fixedSide as $key=>$value)
        {
            if($n>0)$q.=" AND ";
            $q.=$key."=".$value;
        }
        if(count($variableSides)==1)
        {            
            $variableSideName=$variableSides[0];
            if(count($srcValues[$variableSideName])>0)
                $q.=" AND ".$variableSides[0]." NOT IN (".implode(",",$srcValues[$variableSideName]).')';
        }
        else
        {
            // TODO : Para relaciones multiples donde la relacion con uno de los objetos, es a traves de mas de 1 campo.
        }
        $this->conn->doQ($q);
        $k=0;
        if(!$srcValues)
            return;
        $keys=array_keys($srcValues);
        $insExpr="INSERT IGNORE INTO ".$table." (".implode(",",$keys).") VALUES ";
        $doInsert=false;
        while(isset($srcValues[$variableSideName][$k]))
        {           
            $parts=array();
            foreach($keys as $value)
            {
                $parts[]=$srcValues[$value][$k];
            }
            $insExpr.=($k>0?",":"")."(".implode(",",$parts).")";            
            $k++;
            $doInsert=true;
        }
        if($doInsert)
            $this->conn->doQ($insExpr);
    }
    function subLoad($definition, & $relationColumn)
    {
        $objectName = $relationColumn->getRemoteObject();
        $builder = new QueryBuilder($definition);
        $q = $builder->build();
        $results = $this->conn->select($q);
        $nResults = count($results);

        $models = array();
        for ($k = 0; $k < $nResults; $k++)
        {
            $newInstance=\lib\model\BaseModel::getModelInstance($objectName);
            $newInstance->__setSerializer($this);
            $newInstance->loadFromArray($results[$k], $this);
            $normalized=\lib\model\ModelCache::store($newInstance);
            $models[] = $normalized;
        }

        return $models;
    }

    function count($definition, & $model)
    {
        $definition["BASE"] = array("COUNT(*) AS NELEMS");
        $builder = new QueryBuilder($definition);
        $q = $builder->build();
        $result = $this->conn->select($q);
        return $result[0]["NELEMS"];
    }

    function createStorage($modelDef, $extraDef = null)
    {
        if (!$extraDef)
        {
            $mysqlDesc = \lib\reflection\storage\options\MysqlOptionsDefinition::createDefault($modelDef);
            $extraDef = $mysqlDesc->getDefinition();
        }
        $extraDefinition = $extraDef;
        $definition = $modelDef->getDefinition();

        if ($extraDefinition["FIELDS"])
            $fields = array_merge($definition["FIELDS"], $extraDefinition["FIELDS"]);
        else
            $fields = $definition["FIELDS"];

        // Los objetos privados tienen como prefijo el objeto publico.        
        $tableName = str_replace('\\','_',$modelDef->getTableName());
        $fields = $modelDef->fields;

        $keys = (array) ($extraDefinition["KEY"] ? $extraDefinition["KEY"] : $definition["INDEXFIELDS"]);

        $indexes = array_merge($keys, (array) $extraDefinition["INDEXES"]);
        if (!$indexes)
            $indexes = array();


        include_once(LIBPATH . "/php/ArrayTools.php");
        foreach ($fields as $key => $value)
        {

            $types = $value->getRawType();
            $serializers = array();
            $serType = $this->getSerializerType();

            foreach ($types as $typeKey => $typeValue)
            {
                $serializers[$typeKey] = \lib\model\types\TypeFactory::getSerializer($typeValue, $serType);
            }

            $def = $value->getDefinition();

            if ($def["REQUIRED"])
                $notNullExpr = " NOT NULL";
            else
                $notNullExpr = "";

            foreach ($serializers as $type => $typeSerializer)
            {
                $columnDef = $typeSerializer->getSQLDefinition($type, $types[$type]->getDefinition());

                if (\lib\php\ArrayTools::isAssociative($columnDef))
                    $columnDef = array($columnDef);

                for ($k = 0; $k < count($columnDef); $k++)
                {
                    $fieldColumns[$key][] = $columnDef[$k]["NAME"];
                    $sqlFields[] = "`" . $columnDef[$k]["NAME"] . "` " . $columnDef[$k]["TYPE"] . " " . $notNullExpr;
                }
            }
        }
        $tableOptionsText = "";
        if ($extraDefinition["TABLE_OPTIONS"])
        {

            foreach ($extraDefinition["TABLE_OPTIONS"] as $key => $value)
                $tableOptionsText.=" $key $value";
        }
        $engine = $extraDefinition["ENGINE"];
        if (!$engine)
            $engine = "InnoDB";

        $pKey = (array) $definition["INDEXFIELDS"];
        if ($pKey)
            $pKeyCad = " PRIMARY KEY (`" . implode("`,`", $pKey) . "`)";

        $extraIndexes = null;
        if ($extraDefinition["INDEXES"])
        {
            $extraIndexes = array();
            for ($k = 0; $k < count($extraDefinition["INDEXES"]); $k++)
            {
                $curIndex = $extraDefinition["INDEXES"][$k];
                $indexFields = $curIndex["FIELDS"];


                $isUnique = ($curIndex["UNIQUE"] && $curIndex["UNIQUE"]!="false") ? "UNIQUE " : "";
                $isFullText = $curIndex["FULLTEXT"] ? "FULLTEXT " : "";
                $indexType = $curIndex["TYPE"];
                $extraIndexes[] = $isFullText . $isUnique . " KEY " . $tableName . "_i" . $k . " (" . implode(",", $indexFields) . ")";
            }
        }


        $createTableQuery = "CREATE TABLE " . $tableName . " (" . implode(",", $sqlFields);
        $createTableQuery.=($pKeyCad ? "," . $pKeyCad : "") . ($extraIndexes ? "," . implode(",", $extraIndexes) : "");
        $createTableQuery.=")";


        $collation = $extraDefinition["COLLATE"];
        if (!$collation)
            $collation = "utf8_general_ci";
        $characterSet = $extraDefinition["CHARACTER SET"];
        if (!$characterSet)
            $characterSet = "utf8";

        $createTableQuery.="DEFAULT CHARACTER SET " . $characterSet . " COLLATE " . $collation;
        $createTableQuery.=" ENGINE " . $engine;
        //echo $createTableQuery."<br>";
        $this->conn->update($createTableQuery);
    }

    function destroyStorage($object)
    {

        $instance = \lib\model\BaseModel::getModelInstance($object);
        $tableName = $instance->__getTableName();
        $q = "DROP TABLE " . $tableName;
        $this->conn->update($q);
    }

    function createDataSpace($spaceDef)
    {
        $q = "CREATE DATABASE IF NOT EXISTS " . $spaceDef["NAME"];
        $this->conn->update($q);
    }

    function existsDataSpace($spaceDef)
    {
        $q = "SHOW DATABASES";

        $res = $this->conn->select($q, "Database");

        $names = array_map("strtolower", array_keys($res));
        return in_array(strtolower($spaceDef["NAME"]), $names);
    }

    function destroyDataSpace($spaceDef)
    {
        $q = "DROP DATABASE IF EXISTS " . $spaceDef["NAME"];
        $this->conn->update($q);
        $this->currentDataSpace=null;
    }

    function useDataSpace($dataSpace)
    {
        if ($this->currentDataSpace != $dataSpace)
        {            
            $this->conn->selectDb($dataSpace);
            $this->currentDataSpace = $dataSpace;
        }
    }
    function getCurrentDataSpace()
    {
        return $this->currentDataSpace;
    }

    function buildQuery($queryDef,$params,$pagingParams,$findRows=true)
    {
        $qB = new QueryBuilder($queryDef, $params,$pagingParams);
        $qB->findFoundRows($findRows);
        return  $qB->build();

    }
    function fetchAll($queryDef, & $data, & $nRows, & $matchingRows, $params,$pagingParams)
    {
        if(isset($queryDef["PRE_QUERIES"]))
        {
            foreach($queryDef["PRE_QUERIES"] as $cq)
                $this->conn->doQ($cq);
        }
        $q=$this->buildQuery($queryDef,$params,$pagingParams);
    //    echo $q."<br>";

        $data = $this->conn->selectAll($q, $nRows);

        $frows = $this->conn->select("SELECT FOUND_ROWS() AS NROWS");
        $matchingRows = $frows[0]["NROWS"];
    }

    function fetchCursor($queryDef, & $data, & $nRows, & $matchingRows, $params,$pagingParams)
    {
        if(isset($queryDef["PRE_QUERIES"]))
        {
            foreach($queryDef["PRE_QUERIES"] as $cq)
                $this->conn->doQ($cq);
        }
        $q=$this->buildQuery($queryDef,$params,$pagingParams,false);
        //echo $q."<br>";
        $this->currentCursor =  $this->conn->cursor($q);
        $nRows=0;
        $matchingRows=0;
    }

    function next()
    {
        if($this->currentCursor)
            return $this->conn->fetch($this->currentCursor);
        return null;
    }
    function getConnection()
    {
        return $this->conn;
    }

    function processAction($definition,$parameters)
    {
        $qB = new QueryBuilder($definition, $parameters);
        $q = $qB->build();
        $this->conn->doQ($q);
    }

}



?>
