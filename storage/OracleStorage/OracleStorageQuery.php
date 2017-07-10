<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 28/06/2017
 * Time: 1:45
 */

namespace lib\storage\OracleStorage;
include_once(LIBPATH."/storage/Base/StorageEngine.php");

use lib\storage\Base\StorageEngineGetParams;
use lib\storage\Base\StorageEngineParams;

class OracleStorageQueryException extends \lib\storage\Base\Query\QueryException
{
    const ERR_MISSING_QUERY=10;
    const ERR_INVALID_QUERY=11;
    const TXT_MISSING_QUERY="Missing the query field in query specification";
    const TXT_INVALID_QUERY="Invalid Query";
}
class OracleStorageQueryFactory extends \lib\storage\Base\Query\QueryFactory
{
    function getInstance($arr)
    {
        if(!isset($arr["query"]))
            throw new OracleStorageQueryException(OracleStorageQueryException::ERR_MISSING_QUERY);
        $q=$arr["query"];
    }
}
class OracleStorageQuery extends \lib\storage\Base\Query\Query
{
    function parse(StorageEngineParams $params)
    {
        $filter = null;
        if ($params->nElems !== null) {
            if ($params->pageStart !== null)
                $filter["[[%limit%]]"] = "LIMIT " . $params->pageStart . "," . $params->nElems;
            else
                $filter["[[%limit%]]"] = "LIMIT " . $params->nElems;
        } else
            $filter["[[%limit%]]"] = "";

        if ($params->sorting) {
            $sortParts = [];
            foreach ($params->sorting as $key => $value)
                $sortParts[] = $key . " " . $value;
            $filter["[[%sort%]]"] = "ORDER BY " . implode(",", $sortParts);
        } else
            $filter["[[%sort%]]"] = "";


        return $this->getBaseQuery($params, $filter);
    }
    function getUnusedParameterReplacement()
    {
        return "";
    }
    function setConnection(\lib\storage\Base\Connectors\Connector $conn)
    {
        // TODO: Implement setConnection() method.
    }
    function getCursor($newQuery, StorageEngineParams $params)
    {
        // TODO: Implement getCursor() method.
    }
}
