<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 30/09/15
 * Time: 9:01
 */

namespace Vocento\PublicationBundle\Tests\Service\Methode;
use Vocento\PublicationBundle\Service\Methode\ArticleUnserializer;
use Vocento\PublicationBundle\Service\Service;
use Vocento\PublicationBundle\Service\ServiceParam;
use Vocento\PublicationBundle\Lib\RuntimeLoader;
use runtime\Model\Article;


class SampleService extends Service
{
    function __construct($mainDir,$context=null)
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
                                "basePath"=>$mainDir."/serviceFiles/article.xml"
                            )
                        )
                    )
                )
            ),
            /*
             *  Declaraciones de queries: sobre que engine se ejecutan, que query se le aplica, que recurso se espera.
             */
            "queries"=>array(
                "getArticle"=>array(
                    "source"=>"SimpleFile",
                    "query"=>"checkFile",
                    "resource"=>'Vocento\PublicationBundle\Service\Methode\MethodeResource'
                )
            )
        );
        parent::__construct(new ServiceParam($definition));
    }
}


class ArticleUnserializerTest extends \PHPUnit_Framework_TestCase {
    function getBaseArticle()
    {
        $oS=new SampleService(__DIR__);
        return $oS->query("getArticle",array());
    }

    static function SetUpBeforeClass()
    {
        \Vocento\PublicationBundle\Lib\StorageEngine\StorageEngineFactory::clearCache();

        spl_autoload_register(array('\Vocento\PublicationBundle\Lib\RuntimeLoader','autoload'));
        if(!defined("MEDIA"))
        {
            define("MEDIA","publication");
            define("EDITION","");
        }
        RuntimeLoader::includeClassFile('\runtime\Model\Publication\Components\Article\Article',"publication",null);
    }
    function testArticleLoad()
    {
        $base=$this->getBaseArticle();
        $this->assertEquals(true,$base->isLoaded());
        $sourceValue=$base->getSourceValue();
        $this->assertEquals(true,strlen($sourceValue->result)>1);
        $normalizedValue=$base->getNormalizedValue();
        $this->assertEquals(true,is_array($normalizedValue->result));
        $rootKeys=array_keys($normalizedValue->result);
        $this->assertEquals("noticia",$rootKeys[0]);
    }
    function testArticleDeserialize()
    {
        $base=$this->getBaseArticle();
        $unserializer=new \Vocento\PublicationBundle\Service\Methode\ArticleUnserializer($base);
        $article=$unserializer->getInstance();

    }
} 