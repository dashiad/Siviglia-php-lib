<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 22/09/15
 * Time: 8:38
 */

namespace lib\test\storageEngine\URI;
use \lib\storageEngine\StorageConnectionFactory;
use \lib\storageEngine\URI\URIStorageEngine;
use \lib\storageEngine\URI\URIStorageParams;
use \lib\storageEngine\URI\URIStorageEngineException;
use \lib\storageEngine\StorageEngineException;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;

$bundlePath=__DIR__."/../../../Lib/StorageEngine/";
include_once($bundlePath."StorageEngine.php");
include_once($bundlePath."URIStorageEngine.php");

/**
 * Creacion del objeto de parametros.Una sola query, con su identificador.El parametro recibido es param1
 */
;



class URIStorageEngineTest extends \PHPUnit_Framework_TestCase {
    const NORTH=44.1;
    const SOUTH=-9.9;
    const EAST=-22.4;
    const WEST=55.2;
    const ICAO="LSZH";
    function testConnection()
    {
        $param=new URIStorageParams(
            array(
                "queries"=>array(
                    "weather"=>array(
                        "baseUrl"=>"http://api.geonames.org/weatherJSON",
                        "parameters"=>array(
                            "north"=>"[%north%]",
                            "south"=>"[%south%]",
                            "east"=>"[%east%]",
                            "west"=>"[%west%]",
                            "username"=>"demo"
                        )
                    ),
                    "weatherByStation"=>array(
                        "baseUrl"=>"http://api.geonames.org/weatherJSON?ICAO=[%ICAO%]&username=demo",
                    )
                )
            )
        );
        /**
         * Creacion del objeto URI, con el parametro anterior.
         */
        $engine=new URIStorageEngine($param);
        $reader=new StorageEngineGetParams(
            array("query"=>"weather",
                "params"=>array(
                    "north"=>URIStorageEngineTest::NORTH,
                    "south"=>URIStorageEngineTest::SOUTH,
                    "east"=>URIStorageEngineTest::EAST,
                    "west"=>URIStorageEngineTest::WEST)));
            $result=$engine->get($reader);
    }
    // Se ha metido un error en el baseUrl (httq)
    function testException()
    {
        $this->setExpectedException('\lib\storageEngine\URIStorageEngineException',
            '',
            URIStorageEngineException::ERR_CURL_ERROR);
        $param=new URIStorageParams(
            array(
                "queries"=>array(
                    "weather"=>array(
                        "baseUrl"=>"httq://api.geonames.org/weatherJSON",
                        "parameters"=>array(
                            "north"=>"[%north%]",
                            "south"=>"[%south%]",
                            "east"=>"[%east%]",
                            "west"=>"[%west%]",
                            "username"=>"demo"
                        )
                    ),
                    "weatherByStation"=>array(
                        "baseUrl"=>"http://api.geonames.org/weatherJSON?ICAO=[%ICAO%]&username=demo",
                    )
                )
            )
        );
        /**
         * Creacion del objeto URI, con el parametro anterior.
         */
        $engine=new URIStorageEngine($param);
        $reader=new StorageEngineGetParams(
            array("query"=>"weather",
                "params"=>array(
                    "north"=>URIStorageEngineTest::NORTH,
                    "south"=>URIStorageEngineTest::SOUTH,
                    "east"=>URIStorageEngineTest::EAST,
                    "west"=>URIStorageEngineTest::WEST)));
        $result=$engine->get($reader);
    }
} 