<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 11:17
 */

namespace lib\storageEngine\Mongo;

use lib\php\ArrayMappedParameters;
use lib\model\BaseException;


class MongoDBConnectionException extends BaseException
{
    const ERR_CANT_CONNECT = 1;
    const ERR_WRITE_ERROR = 2;
    const ERR_GET_ERROR = 3;
    const ERR_COMMAND_ERROR = 3;
}

class MongoDBConnectionParams extends ArrayMappedParameters
{
    var $host;
    var $port;
    var $database;
    var $username;
    var $password;
    var $options;
    var $driverOptions;
    var $context = array();
    var $compressed = false;
    static $__definition=array(
      "fields"=>array(
          "port"=>array("default"=>27017),
          "username"=>array("required"=>false),
          "password"=>array("required"=>false),
          "options"=>array("default"=>array()),
          "driverOptions"=>array("default"=>array())
      )
    );
}

class MongoDBConnection
{
    var $connected = false;
    var $params;
    var $manager;

    function __construct(MongoDBConnectionParams $params)
    {
        $this->params = $params;
        $connecting_string =  sprintf('mongodb://%s:%d/%s', $params->host, $params->port,$params->database);
        $auth=array();
        if($params->username!='')
        {
            $auth=array('username'=>$params->username,'password'=>$params->password);
        }
        $this->manager =  new \MongoDB\Driver\Manager($connecting_string,$auth);
    }
    function insert($collection,$values)
    {
        $w = new \MongoDB\Driver\BulkWrite(['ordered' => true]);
        $w->insert($values);
        $res=$this->execWrite($collection,$w);
        $inserted=$res->getInsertedCount();
        $matched=$res->getMatchedCount();
    }
    function getManager()
    {
        return $this->manager;
    }
    function execWrite($collection,$bulk)
    {
        $collection=$this->params->database.".".$collection;
        $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000);
        try {
            $result = $this->manager->executeBulkWrite($collection, $bulk, $writeConcern);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {

            $result = $e->getWriteResult();
            // Check if the write concern could not be fulfilled
            $errMessage="";
            $errCode="";
            if ($writeConcernError = $result->getWriteConcernError()) {
                    $errMessage=$writeConcernError->getMessage();
                    $errCode=$writeConcernError->getCode();
            }

            throw new MongoDBConnectionException(MongoDBConnectionException::ERR_WRITE_ERROR,array("code"=>$errCode,"message"=>$errMessage));
        }
        catch(\MongoDB\Driver\Exception\InvalidArgumentException $e)
        {
            $errMessage=$e->getMessage();
            $errCode=$e->getCode();
            throw new MongoDBConnectionException(MongoDBConnectionException::ERR_WRITE_ERROR,array("code"=>$errCode,"message"=>$errMessage));
        }
        return $result;
    }
    function update($collection,$params,$values,$isUpsert=false)
    {
        $w = new \MongoDB\Driver\BulkWrite(['ordered'=>true]);
        $w->update($params,array('$set'=>$values),["upsert"=>$isUpsert]);
        $res= $this->execWrite($collection,$w);
        $updatedCount=$res->getModifiedCount();
        $matchedCount=$res->getMatchedCount();
    }
    // Filter contiene el filtro, $deleteOptions es un array que, como mucho, tiene un elemento "limit"
    function delete($collection,$filter,$deleteOptions)
    {
        $w = new \MongoDB\Driver\BulkWrite(['ordered'=>true]);
        $w->delete($filter,$deleteOptions);
        $result=$this->execWrite($collection,$w);
        $deletedCount=$result->getDeletedCount();
        $matchedCount=$result->getMatchedCount();

    }
    function get($query)
    {
        $collection=$this->params->database.".".$query->getCollection();

        //if($query),$q->getMongoQuery()
        if(!$query->isCommand()) {
            $q=$query->getMongoQuery();
            $intVals=array("batchSize","limit","skip");
            foreach($intVals as $key=>$value)
            {

                if(isset($q["options"][$value]))
                    $q["options"][$value]=intval($q["options"][$value]);
            }
            $objQuery=new \MongoDB\Driver\Query($q["filter"]===null?array():$q["filter"],
                                                isset($q["options"])?$q["options"]:array());
            $cursor = $this->manager->executeQuery($collection, $objQuery);
            $cursor->setTypeMap(array("root" => "array"));
            return $cursor->toArray();
        }
        $jsObjects=array("map","reduce");
        $command=$query->getMongoCommand();
        unset($command["collection"]);
        foreach($jsObjects as $key=>$value)
        {
            if(isset($command[$value]))
                $command[$value]=new \MongoDB\BSON\Javascript($command[$value]);
        }
        try {
            $objCommand = new \MongoDB\Driver\Command($command);
            $cursor=$this->manager->executeCommand($this->params->database,$objCommand);
            $data= $cursor->toArray();
            if(isset($data[0]) && isset($data[0]->results))
                return $data[0]->results;
            return $data;
        }catch(\Exception $e)
        {
            throw new MongoDBConnectionException(MongoDBConnectionException::ERR_COMMAND_ERROR,array("message"=>$e->getMessage()));
        }

    }

}
