<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 15:25
 */

namespace lib\test\storageEngine\Mongo;
use lib\storageEngine\StorageConnectionFactory;
use lib\storageEngine\Mongo\MongoDBStorageEngine;
use lib\storageEngine\Mongo\MongoDBConnection;
use lib\storageEngine\Mongo\MongoDBQuery;
use lib\storageEngine\Mongo\MongoDBStorageParams;
use lib\storageEngine\Mongo\MongoDBConnectionParams;
use \lib\storageEngine\StorageEngineException;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;

include_once(PROJECTPATH."/lib/storageEngine/StorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/Mongo/MongoDBStorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/Mongo/MongoDBConnection.php");

class MongoDBStorageEngineTest extends \PHPUnit_Framework_TestCase {
    var $storage;
    var $engine;
    var $defConn;
    function SetUp()
    {
        $connParams=new \lib\storageEngine\Mongo\MongoDBConnectionParams(
            array(
                "host"=>MONGODB_HOST,
                "port"=> MONGODB_PORT,
                "username"=>MONGODB_USERNAME,
                "password"=>MONGODB_PASSWORD,
                "database"=>MONGODB_DB
            )
        );

        $defConn=new \lib\storageEngine\Mongo\MongoDBConnection($connParams);
        StorageConnectionFactory::addConnection("MongoDefault",$defConn);
        $param=new MongoDBStorageParams(
            array(
                "connectionName"=>"MongoDefault",
                "queries"=>array(
                    /**************************************************
                     *
                     *   QUERY DE SAVE
                     **************************************************/
                    "save1"=>array(
                        "type"=>MongoDBQuery::INSERT,
                        "parameters"=>array(
                            "field1"=>array("TYPE"=>"Integer"),
                            "field2"=>array("TYPE"=>"String"),
                        ),
                        "conditions"=>array(
                            "field1_cond"=>array(
                                "FILTER"=>array("field1"=>array('$eq'=>"[%field1%]")),
                                "TRIGGER_VAR"=>"field1",
                                "DISABLE_IF"=>"0"
                            ),
                            "field2_cond"=>array(
                                "FILTER"=>array("field2"=>array('$eq'=>"[%field2%]")),
                                "TRIGGER_VAR"=>"field2",
                                "DISABLE_IF"=>"0"
                            )
                        ),
                        "mapAllValues"=>true,
                        "query"=>array(
                            "collection"=>"testCol",
                            "base"=>array(
                            "filter"=>array('$and'=>array("[%field2_cond%]","[%field1_cond%]"))
                            )
                        )
                    ),
                    /**************************************************
                     *
                     *   QUERY DE UPDATE1
                     **************************************************/
                    "update1"=>array(
                        "type"=>MongoDBQuery::UPDATE,
                        "parameters"=>array(
                            "field1"=>array("TYPE"=>"Integer")
                        ),
                        "conditions"=>array(
                            "field1_cond"=>array(
                                "FILTER"=>array("field1"=>array('$eq'=>"[%field1%]")),
                                "TRIGGER_VAR"=>"field1",
                                "DISABLE_IF"=>"0"
                            )
                        ),
                        "query"=>array(
                            "collection"=>"test.testCol",
                            "base"=>array(
                            "filter"=>array("[%field1_cond%]")
                            )
                        ),
                        "values"=>array(
                            "field2"=>"[%field2%]"
                        )
                    ),
                    /**************************************************
                     *
                     *   QUERY DE DELETE
                     **************************************************/

                    "delete"=>array(
                        "type"=>MongoDBQuery::DELETE,
                        "parameters"=>array(
                            "field1"=>array("TYPE"=>"Integer")
                        ),
                        "conditions"=>array(
                            "field1_cond"=>array(
                                "FILTER"=>array("field1"=>array('$eq'=>"[%field1%]")),
                                "TRIGGER_VAR"=>"field1",
                                "DISABLE_IF"=>"0"
                            )
                        ),
                        "query"=>array(
                            "collection"=>"testCol",
                            "base"=>array(
                            "filter"=>array("[%field1_cond%]")
                            )
                        )
                    ),
                    "count"=>array(
                        "command"=>array(
                            "collection"=>"testCol",
                            "count"=>"testCol"
                        )
                    ),
                    /**************************************************
                     *
                     *   QUERY DE GET.
                     *   Es basicamente la misma que la de update.
                     **************************************************/
                    "get"=>array(
                        "parameters"=>array(
                            "field1"=>array("TYPE"=>"Integer"),
                            "field2"=>array("TYPE"=>"String"),
                        ),
                        "conditions"=>array(
                            "field1_cond"=>array(
                                "FILTER"=>array("field1"=>array('$eq'=>"[%field1%]")),
                                "TRIGGER_VAR"=>"field1",
                                "DISABLE_IF"=>"0"
                            ),
                            "field2_cond"=>array(
                                "FILTER"=>array("field2"=>array('$eq'=>"[%field2%]")),
                                "TRIGGER_VAR"=>"field2",
                                "DISABLE_IF"=>"0"
                            )
                        ),
                        "query"=>array(
                            "collection"=>"testCol",
                            "base"=>array(
                                "filter"=>array('$and'=>array("[%field2_cond%]","[%field1_cond%]"))
                            )
                        )
                    ),
                    "mapReduce"=>array(
                        "parameters"=>array(
                            "field2"=>array("TYPE"=>"String"),
                        ),
                        "command"=>array(
                            "collection"=>"testCol",
                            'mapreduce' => 'testCol',
                            'map'=>'if(this.field2 > [%field2%]){emit(0,{"field2":this.field2});}',
                            'reduce'=>'function(key,values){return values.length; }',
                            'out' => array("inline" => 1)
                        )
                    ),
                    "mapReduce2"=>array(
                        "parameters"=>array(
                            "field2"=>array("TYPE"=>"String"),
                        ),
                        "command"=>array(
                            "collection"=>"testCol",
                            'mapreduce' => 'testCol',
                            'map'=>'if(this.field2 > [%field2%]){emit(parseInt(this.field2/10),{"field2":this.field2});}',
                            'reduce'=>'function(key,values){return values.length; }',
                            'out' => array("inline" => 1)
                        )
                    )
                )
            )
        );
        $this->defConn=$defConn;
        $this->engine=new MongoDBStorageEngine($param);
    }
    function insertSimpleElement($field2=45)
    {
        $writer=new StorageEngineSetParams(
            array("query"=>"save1","values"=>array("field1"=>"43","field2"=>$field2)));
        $this->engine->set($writer);
    }
    function getTestColCount()
    {
        $count = new StorageEngineGetparams(
            array("query" => "count")
        );
        $result = $this->engine->get($count);
        return $result->result[0]->n;
    }
    function testSimpleWriteRead()
    {
        $this->testDeleteAll();
        $this->insertSimpleElement();

        $reader=new StorageEngineGetParams(
            array("query"=>"get","params"=>array("field1"=>"43")));
        $result=$this->engine->get($reader);
        $this->assertEquals(45,$result->result[0]["field2"]);
    }
    function testDelete()
    {
        $this->insertSimpleElement();
        $nElems = $this->getTestColCount();
        $delete = new StorageEngineSetParams(
            array("query" => "delete", "pageStart" => 0, "nElems" => 1)
        );
        $this->engine->set($delete);
        $nElems2 = $this->getTestColCount();
        $this->assertEquals($nElems - $nElems2, 1);
    }
    function testDeleteAll()
    {
        $delete=new StorageEngineSetParams(
            array("query"=>"delete")
        );
        // Se introducen unos cuantos registros mas
        $this->insertSimpleElement();
        $this->insertSimpleElement();
        $this->insertSimpleElement();
        $this->engine->set($delete);
        $nElems2=$this->getTestColCount();
        $this->assertEquals($nElems2,0);
    }
    function testUpdateAll()
    {
        $this->insertSimpleElement();
        $this->insertSimpleElement();
        $upd=new StorageEngineSetParams(
          array("query"=>"update1","values"=>array("field1"=>"54"))
        );
        $this->engine->set($upd);
        $n=$this->getTestColCount();
        $reader=new StorageEngineGetParams(
            array("query"=>"get","params"=>array("field1"=>"54")));
        $result=$this->engine->get($reader);
        $p=count($result->result);
        $this->assertEquals($n,$p);
    }
    function testPaging()
    {
        $this->testDeleteAll();
        for($k=0;$k<30;$k++)
            $this->insertSimpleElement($k);
        $reader=new StorageEngineGetParams(
            array("query"=>"get","pageStart"=>3,"nElems"=>5,"sorting"=>array("field2"=>"ASC")));
        $result=$this->engine->get($reader);
        $p=count($result->result);
        $this->assertEquals(3,$result->result[0]["field2"]);
        $this->assertEquals($p,5);
    }
    function testMapReduce()
    {
        $delete=new StorageEngineSetParams(
            array("query"=>"delete")
        );
        $this->engine->set($delete);

        for($k=0;$k<30;$k++)
            $this->insertSimpleElement(intval($k/10)*10);
        $p=new StorageEngineGetParams(
            array("query"=>"mapReduce","params"=>array("field2"=>0))
        );
        $result=$this->engine->get($p);
        $h=$result->result;
        $this->assertEquals($h[0]->value,20);
    }
    function testMapReduce2()
    {
        $delete=new StorageEngineSetParams(
            array("query"=>"delete")
        );
        $this->engine->set($delete);

        for($k=0;$k<30;$k++)
            $this->insertSimpleElement(intval($k/10)*10);
        $p=new StorageEngineGetParams(
            array("query"=>"mapReduce2","params"=>array("field2"=>0))
        );
        $result=$this->engine->get($p);
        $h=$result->result;
        $this->assertEquals($h[0]->value,10);
        $this->assertEquals($h[0]->_id,1);
        $this->assertEquals($h[1]->value,10);
        $this->assertEquals($h[1]->_id,2);
    }

} 