<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 22/09/15
 * Time: 10:42
 */

namespace Vocento\PublicationBundle\Tests\Service;
use Vocento\PublicationBundle\Service\Service;
use Vocento\PublicationBundle\Service\ServiceException;
use Vocento\PublicationBundle\Service\ServiceParam;
use Vocento\PublicationBundle\Service\Resources\Resource;
use Vocento\PublicationBundle\Service\Resources\ResourceException;
use Vocento\PublicationBundle\Service\Resources\JSONResource;
use Vocento\PublicationBundle\Lib\StorageEngine\StorageEngineResult;
$bundlePath=__DIR__."/../../../Lib/";
include_once($bundlePath."Service/Service.php");
include_once($bundlePath."StorageEngine/StorageEngine.php");
include_once($bundlePath."StorageEngine/MemcacheStorageEngine.php");
include_once($bundlePath."StorageEngine/Connection/MemcacheConnection.php");
include_once($bundlePath."StorageEngine/FileStorageEngine.php");

class SampleService extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            "storageDefinitions"=>array(
                /*
                 *  Declaraciones de storage engines
                 */
                "SimpleFile"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array(
                            "checkFile"=>array(
                                "basePath"=>__DIR__."/serviceFiles/sample_[%param%].txt"
                            )
                        )
                    )
                )
            ),
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleFile",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}
class SampleService2 extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            "storageDefinitions"=>array(
                /*
                 *  Declaraciones de storage engines
                 */
                "SimpleFile2"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array(
                            "checkFile"=>array(
                                "basePath"=>__DIR__."/serviceFiles/sample_[%param%].txt"
                            )
                        )
                    )
                ),
                "SimpleStacked2"=>array(

                    "engine"=>"Stacked",
                    "params"=>array(
                        "engines"=>array("mySimpleFile"=>"SimpleFile2"),
                        "queries"=>array(
                            "checkFile"=>array(
                                array("engine"=>"mySimpleFile","query"=>"checkFile")
                            )
                        )
                    )
                )
            ),
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleStacked2",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}
class SampleService3 extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            "storageDefinitions"=>array(
                /*
                 *  Declaraciones de storage engines
                 */
                // Un storage engine de tipo FILE donde reside el valor source.
                "SimpleFile3"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array(
                            "checkFile"=>array(
                                "basePath"=>__DIR__."/serviceFiles/sample_[%param%].txt"
                            )
                        )
                    )
                ),
                // Un storage engine de tipo FILE donde guardar una cache del valor source.
                // En este caso no tiene mucho sentido, ya que el origen es tambien de fichero.Si el
                // origen fuera un webservice o algun sistema pesado, podria tener mas sentido.
                "SimpleFileCache3"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array(
                            "checkFile"=>array(
                                "basePath"=>__DIR__."/serviceFiles/cached_sample_[%param%].txt"
                            )
                        )
                    )
                ),
                "SimpleStacked3"=>array(
                    "engine"=>"Stacked",
                    "params"=>array(
                        // Se declaran los engines usados, con los nombres internos usados en las queries.
                        "engines"=>array("mySimpleFile"=>"SimpleFile3","mySimpleCache"=>"SimpleFileCache3"),
                        "queries"=>array(
                            // Para esta query, se indican que engines y en que orden se van a usar
                            "checkFile"=>array(
                                // Primero se busca en la cache.
                                array("engine"=>"mySimpleCache","query"=>"checkFile","role"=>Resource::ORIGIN_CACHE),
                                // Despues, en el source (a notar, que se omite el nombre de query ya que es el mismo)
                                array("engine"=>"mySimpleFile","role"=>Resource::ORIGIN_SOURCE),

                            )
                        )
                    )
                )
            ),
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleStacked3",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}
class SampleService4 extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            "storageDefinitions"=>array(
                /*
                 *  Declaraciones de storage engines
                 */
                // Un storage engine de tipo FILE donde reside el valor source.
                "SimpleFile4"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array("checkFile"=>array("basePath"=>__DIR__."/serviceFiles/sample_[%param%].txt"))
                    )
                ),
                // Un storage engine de tipo FILE donde guardar una cache del valor source.
                // En este caso no tiene mucho sentido, ya que el origen es tambien de fichero.Si el
                // origen fuera un webservice o algun sistema pesado, podria tener mas sentido.
                "SimpleFileCache4"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array("checkFile"=>array("basePath"=>__DIR__."/serviceFiles/cached_sample_[%param%].txt")
                        )
                    )
                ),
                // Storage engine para datos "normalized"
                "SimpleFileNormalized4"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array("checkFile"=>array("basePath"=>__DIR__."/serviceFiles/normalized_sample_[%param%].txt")
                        )
                    )
                ),
                // Storage engine para datos procesados.
                "SimpleFileProcessedCache4"=>array(
                    "engine"=>"File",
                    "params"=>array(
                        "queries"=>array("checkFile"=>array("basePath"=>__DIR__."/serviceFiles/processed_sample_[%param%].txt")
                        )
                    )
                ),
                "SimpleStacked4"=>array(
                    "engine"=>"Stacked",
                    "params"=>array(
                        // Se declaran los engines usados, con los nombres internos usados en las queries.
                        // Se aniaden los dos requeridos para datos normalizados y procesados.
                        "engines"=>array("mySimpleFile"=>"SimpleFile4",
                            "mySimpleCache"=>"SimpleFileCache4",
                            "mySimpleNormalized"=>"SimpleFileNormalized4",
                            "mySimpleProcessed"=>"SimpleFileProcessedCache4"
                        ),
                        "queries"=>array(
                            // Para esta query, se indican que engines y en que orden se van a usar
                            "checkFile"=>array(
                                // Primero, la cache procesada.
                                array("engine"=>"mySimpleProcessed","role"=>Resource::ORIGIN_PROCESSED),
                                array("engine"=>"mySimpleNormalized","role"=>Resource::ORIGIN_NORMALIZED),
                                array("engine"=>"mySimpleCache","query"=>"checkFile","role"=>Resource::ORIGIN_CACHE),
                                array("engine"=>"mySimpleFile","role"=>Resource::ORIGIN_SOURCE)
                            )
                        )
                    )
                )
            ),
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleStacked4",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}
class SampleService5 extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "storageDefinitions"=>array(),
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleFile",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
    // EN SERVICIOS REALES, CADA CASE DEBERIA RESOLVERSE EN UN METODO.
    // LA RECOMENDACION SERIA QUE EL NOMBRE DEL METODO FUERA DEL TIPO validateParams_<queryName>
    function validateParams($queryDetails,$params)
    {
        switch($queryDetails["query"])
        {
            case "checkFile":
            {
                return $params["param"]==45;
            }

        }
        return true;
    }
    // EN SERVICIOS REALES, CADA CASE DEBERIA RESOLVERSE EN UN METODO.
    // LA RECOMENDACION SERIA QUE EL NOMBRE DEL METODO FUERA DEL TIPO normalizeParams_<queryName>
    function normalizeParams($queryDetails,$params)
    {
        switch($queryDetails["query"])
        {
            case "checkFile":{
                $params["param"]++;
            }break;
        }
        return $params;
    }
    // EN SERVICIOS REALES, CADA CASE DEBERIA RESOLVERSE EN UN METODO.
    // LA RECOMENDACION SERIA QUE EL NOMBRE DEL METODO FUERA DEL TIPO validateResponse_<queryName>
    function validateResponse($response,$details,$params)
    {
        switch($details["query"])
        {
            case "checkFile":{
                return $response->result=='{"a":2,"b":[{"c":1},{"c":2},{"c":3}]}';
            }break;
        }
    }
}

class MyJSONResource extends JSONResource
{
    static $shouldValidate=true;

    function normalizeValue(StorageEngineResult $val)
    {
        $n1=parent::normalizeValue($val);
        // Se hace cualquier procesado.En este caso, incrementar el valor de un dato.
        $n1["a"]=5;
        return $n1;
    }
    function validateRaw(StorageEngineResult $res)
    {
        return MyJSONResource::$shouldValidate;
    }
}

// Se cambia el tipo de recurso devuelto por el servicio
class SampleService6 extends Service
{
    function __construct($context=null)
    {
        $definition=array(
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "storageDefinitions"=>array(),
            "queries"=>array(
                "checkFile"=>array(
                    "source"=>"SimpleFile",
                    "query"=>"checkFile",
                    "resource"=>'runtime\Lib\Model\Resources\JSONResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}


class ServiceTest extends \PHPUnit_Framework_TestCase{

    var $jsonExample='{"a":2,"b":[{"c":1},{"c":2},{"c":3}]}';
    static function SetUpBeforeClass()
    {
        \Vocento\PublicationBundle\Lib\StorageEngine\StorageEngineFactory::clearCache();

    }
    function SetUp()
    {
        if(!is_dir(__DIR__."/serviceFiles"))
            mkdir(__DIR__."/serviceFiles",0777);
        file_put_contents(__DIR__."/serviceFiles/sample_46.txt",$this->jsonExample);
    }
    function tearDown()
    {
        if(is_dir(__DIR__."/serviceFiles"))
        {
            chmod(__DIR__."/serviceFiles",0777);
            \Vocento\PublicationBundle\Lib\PHP\FileTools::delTree(__DIR__."/serviceFiles");
        }
    }
    function testSimple()
    {
        $ins=new SampleService();

        $resource=$ins->query("checkFile",array("param"=>46));
        var_dump($resource);
        $this->assertEquals($resource->getSourceValue()->result,$this->jsonExample);
    }
    function testNotExistingQuery()
    {
        $this->setExpectedException('\Vocento\PublicationBundle\Service\ServiceException',
            '',
            ServiceException::ERR_UNKNOWN_QUERY);
        $ins=new SampleService();
        $resource=$ins->query("nonExisting",array("param"=>46));
        $this->assertEquals($resource->getSourceValue()->result,$this->jsonExample);
    }
    function testSimple2()
    {
        $ins=new SampleService2();
        // Se realiza la query
        $resource=$ins->query("checkFile",array("param"=>46));
        $this->assertEquals($resource->getSourceValue()->result,$this->jsonExample);
    }
    function testSimple3()
    {
        $ins=new SampleService3();
// Se realiza la query
        $resource=$ins->query("checkFile",array("param"=>46));
        $sourceVal=$resource->getSourceValue();
        $this->assertEquals($sourceVal->result,$this->jsonExample);

        $this->assertEquals($sourceVal->sourceRole,Resource::ORIGIN_SOURCE);

        $resource=$ins->query("checkFile",array("param"=>46));
        $sourceVal=$resource->getSourceValue();
        $this->assertEquals($sourceVal->result,$this->jsonExample);
        $sourceRole=$sourceVal->sourceRole;

        $this->assertEquals($sourceRole,Resource::ORIGIN_CACHE);
    }
    function testFull()
    {
        $ins=new SampleService4();
// Se realiza la query
        $resource=$ins->query("checkFile",array("param"=>46));
        $this->assertEquals($resource->getSourceValue()->sourceRole,Resource::ORIGIN_SOURCE);
        $resource->setProcessedValue("Soy el valor procesado");
        $resource=$ins->query("checkFile",array("param"=>46));
        $this->assertEquals($resource->isProcessed(),true);
        // Ya tendremos que tener el resultado procesado.
        $processed=$resource->getProcessedValue();
        $this->assertEquals($processed->result,"Soy el valor procesado");
    }
    function testOverridenMissingParams()
    {
        $ins=new SampleService5();
        // Se realiza la query (correcta)
        $this->setExpectedException('\Vocento\PublicationBundle\Service\ServiceException',
            '',
            ServiceException::ERR_INVALID_PARAMS);

        // Se fuerza un error de parametros incorrectos
        $resource=$ins->query("checkFile",array("param"=>44));

    }
    function testOverridenInvalidResult()
    {
        $ins=new SampleService5();
        // Se realiza la query (correcta)
        $resource=$ins->query("checkFile",array("param"=>45));
        $jsonExample='{"a":2,"b":[{"c":1},{"c":2},{"c":5}]}';
        file_put_contents(__DIR__."/serviceFiles/sample_46.txt",$jsonExample);

        $this->setExpectedException('\Vocento\PublicationBundle\Service\ServiceException',
            '',
            ServiceException::ERR_INVALID_RESULT);

        $resource=$ins->query("checkFile",array("param"=>45));

    }
    function testCustomResource()
    {
        $ins=new SampleService6();
        // Se realiza la query (correcta)
        $resource=$ins->query("checkFile",array("param"=>46));
        // En la normalizacion del resource, hemos cambiado el valor.
        $normalized=$resource->getNormalizedValue();
        $this->assertEquals($normalized->result["a"],5);
        $this->setExpectedException('\Vocento\PublicationBundle\Service\Resources\ResourceException',
            '',
            ResourceException::ERR_INVALID_RESPONSE);

        MyJSONResource::$shouldValidate=false;
        $resource=$ins->query("checkFile",array("param"=>46));
    }

}
