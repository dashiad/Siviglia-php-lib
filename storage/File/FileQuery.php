<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 28/06/2017
 * Time: 1:45
 */

namespace lib\storage\File;
include_once(LIBPATH."/storage/Base/StorageEngine.php");
include_once(LIBPATH."/storage/Base/Query/Query.php");

use lib\storage\Base\StorageEngineParams;

class FileQueryException extends \lib\storage\Base\Query\QueryException
{
    const ERR_MISSING_QUERY=10;
    const ERR_INVALID_QUERY=11;
    const TXT_MISSING_QUERY="Missing the query field in query specification";
    const TXT_INVALID_QUERY="Invalid Query";
}
class FileQueryFactory extends \lib\storage\Base\Query\QueryFactory
{
    function getInstance($arr)
    {
        if(!isset($arr["query"]))
            throw new FileQueryException(FileQueryException::ERR_MISSING_QUERY);
        $q=$arr["query"];
        $pos=strpos($q,"://");
        if($pos===false)
            throw new FileQueryException(FileQueryException::ERR_INVALID_QUERY);
        $type=substr($q,0,$pos);
        switch($type)
        {
            case "file":{
                return new FileFileQuery($arr);
            }break;
            case "dir":{
                return new FileDirQuery($arr);
            }break;
        }
    }
}
abstract class FileQuery extends \lib\storage\Base\Query\Query
{
    var $query;
    static $__definition=array(
        "fields"=>array(
            "query"=>array("required"=>true)
        )
    );
    function parse(StorageEngineParams $params)
    {
        return \lib\php\ParametrizableString::getParametrizedString($this->query,$params->params);
    }
    function getUnusedParameterReplacement()
    {
        return "";
    }

    function setConnection(\lib\storage\Base\Connectors\Connector $conn)
    {
        $this->connector=$conn;
    }
}

class FileFileQuery extends FileQuery
{
    var $cursorType;
    static $__definition=array(
        "fields"=>array(
            "cursorType"=>array("default"=>"FileLine","TYPE"=>"Enum","VALUES"=>array("FileLine","FullFile"))
        )
    );
    function getCursor($query,StorageEngineParams $params)
    {
        $prefix="file://";
        $path=substr($query,strlen($prefix));
        switch($this->cursorType)
        {
            case "FileLine":{

            }break;
            case "FullFile":{}break;
        }
    }
}

class FileDirQuery extends FileQuery
{
    function getCursor($query,StorageEngineParams $params)
    {

    }

}