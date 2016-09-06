<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 02/07/2016
 * Time: 21:26
 */

namespace lib\test\storageEngine\Mysql;

include_once(LIBPATH."/storageEngine/Mysql/MysqlStorageEngine.php");
include_once(LIBPATH."/storageEngine/Mysql/MysqlConnection.php");
use \lib\storageEngine\Mysql\MysqlQueryFactory;

use \lib\storageEngine\Mysql\MysqlConnection;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;

class MysqlQueryTest extends \PHPUnit_Framework_TestCase
{
    var $connection;
    function SetUp()
    {
        $connParams=new \lib\storageEngine\Mysql\MysqlConnectionParams(
            array(
                "host"=>MYSQL_HOST,
                "port"=> MYSQL_PORT,
                "username"=>MYSQL_USERNAME,
                "password"=>MYSQL_PASSWORD,
                "database"=>MYSQL_DB
            )
        );
        $this->connection=new MysqlConnection($connParams);
    }
    function testSimpleQuery()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "base"=>array(
                "TABLE"=>"MiTest",
                "FIELDS"=>array("a","b","c")
            )
        ));
        $q1->setConnection($this->connection);
        $g=new StorageEngineGetParams(array("query"=>"test"));

        $composed=$q1->parse($g);
        $this->assertEquals("SELECT a,b,c FROM MiTest",trim($composed));

    }
    function testSimpleQuerySingleParam()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array(
                    "TYPE"=>"Integer"
                )
            ),
            "base"=>"SELECT * FROM MiTest WHERE [%filter1%]",
            "conditions"=>array(
                "filter1"=>array(
                    "TRIGGER_VAR"=>"simple",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"a=[%simple%]"
                )
            )
        ));
        $q1->setConnection($this->connection);
        // Primer test, no se especifica valor para el parametro "simple".
        $g=new StorageEngineGetParams(array("query"=>"test"));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE TRUE",trim($composed));
        // Segundo test, se especifica un valor para el parametro "simple":
        $g1=new StorageEngineGetParams(array("query"=>"test","params"=>array("simple"=>1)));
        $composed=$q1->parse($g1);
        $this->assertEquals("SELECT * FROM MiTest WHERE a=1",trim($composed));
    }
    function testSimpleQuerySingleString()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array(
                    "TYPE"=>"String"
                )
            ),
            "base"=>"SELECT * FROM MiTest WHERE [%filter1%]",
            "conditions"=>array(
                "filter1"=>array(
                    "TRIGGER_VAR"=>"simple",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"a='[%simple%]'"
                )
            )
        ));
        $q1->setConnection($this->connection);
        // Primer test, no se especifica valor para el parametro "simple".
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("simple"=>"Hola")));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE a='Hola'",trim($composed));
        // Segundo test, se especifica un valor para el parametro "simple":
        $g1=new StorageEngineGetParams(array("query"=>"test","params"=>array("simple"=>"Ho'la")));
        $composed=$q1->parse($g1);
        $this->assertEquals("SELECT * FROM MiTest WHERE a='Ho\\'la'",trim($composed));
    }

    function testQuerySeveralParameters()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array("TYPE"=>"String"),
                "number"=>array("TYPE"=>"Integer"),
                "number2"=>array("TYPE"=>"Integer")
            ),
            "base"=>"SELECT * FROM MiTest WHERE ([%filter1%] OR [%filter2%]) AND [%filter3%]",
            "conditions"=>array(
                "filter1"=>array("TRIGGER_VAR"=>"simple", "DISABLE_IF"=>"", "FILTER"=>"a='[%simple%]'"),
                "filter2"=>array("TRIGGER_VAR"=>"number", "DISABLE_IF"=>"", "FILTER"=>"[%simple:a=1%][%!simple:b=2%] AND c=[%number%]"),
                "filter3"=>array("TRIGGER_VAR"=>"number2", "DISABLE_IF"=>4, "FILTER"=>"d=[%number2%]")
            )
        ));
        $q1->setConnection($this->connection);
        // Primer test, no se especifica valor para el parametro "simple".
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number"=>"4")));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR b=2 AND c=4) AND TRUE",trim($composed));

        // Test de condicion
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number"=>"4","simple"=>"hola")));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (a='hola' OR a=1 AND c=4) AND TRUE",trim($composed));

        // Test de serializacion de numero entero
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number2"=>'texto')));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR TRUE) AND d=0",trim($composed));

        // Test de DISABLE_IF
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number2"=>4)));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR TRUE) AND TRUE",trim($composed));
    }

    function testQuerySeveralParametersLimitPagingGroupingSorting()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array("TYPE"=>"String"),
                "number"=>array("TYPE"=>"Integer"),
                "number2"=>array("TYPE"=>"Integer")
            ),
            "base"=>"SELECT * FROM MiTest WHERE ([%filter1%] OR [%filter2%]) AND [%filter3%] [[%group%]] [[%sort%]] [[%limit%]]  ",
            "conditions"=>array(
                "filter1"=>array("TRIGGER_VAR"=>"simple", "DISABLE_IF"=>"", "FILTER"=>"a='[%simple%]'"),
                "filter2"=>array("TRIGGER_VAR"=>"number", "DISABLE_IF"=>"", "FILTER"=>"[%simple:a=1%][%!simple:b=2%] AND c=[%number%]"),
                "filter3"=>array("TRIGGER_VAR"=>"number2", "DISABLE_IF"=>4, "FILTER"=>"d=[%number2%]")
            )
        ));
        $q1->setConnection($this->connection);
        /*var $filter = null;
        var $sorting = null; // sorting es un array campo=>direccion
        var $grouping = null;
        var $pageStart = null;
        var $nElems = null;*/
        // Primer test, sorting
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number"=>"4"),"sorting"=>array("c"=>"DESC")));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR b=2 AND c=4) AND TRUE  ORDER BY c DESC",trim($composed));

        // Test de sorting y limite de resultados
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number"=>"4","simple"=>"hola"),"sorting"=>array("c"=>"DESC"),"nElems"=>10));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (a='hola' OR a=1 AND c=4) AND TRUE  ORDER BY c DESC LIMIT 10",trim($composed));

        // Test de sorting y paginacion
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number2"=>'texto'),"sorting"=>array("c"=>"DESC"),"pageStart"=>5,"nElems"=>10));
        $composed=trim($q1->parse($g));
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR TRUE) AND d=0  ORDER BY c DESC LIMIT 5,10",$composed);

        // Test de sorting, paginacion y grouping
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number2"=>4),"sorting"=>array("c"=>"DESC"),"pageStart"=>5,"nElems"=>10,
            "grouping"=>array(array("field"=>"f"),array("field"=>"g"))));
        $composed=$q1->parse($g);
        // Notese que se han autoincluido los valores de agrupacion, como valores de ordenacion.
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR TRUE) AND TRUE GROUP BY f,g ORDER BY c DESC,f DESC,g DESC LIMIT 5,10",trim($composed));

        // Test de desaparicion de los placeholders si no son especificados.
        $g=new StorageEngineGetParams(array("query"=>"test","params"=>array("number2"=>4)));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE (TRUE OR TRUE) AND TRUE",trim($composed));
    }

    // Tests asociados a filtros free-form.
    function testFilteredQuery()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "simple" => array("TYPE" => "String"),
                "number" => array("TYPE" => "Integer"),
                "number2" => array("TYPE" => "Integer")
            ),
            "base" => "SELECT * FROM MiTest WHERE [[%filter%]] "
        ));
        $q1->setConnection($this->connection);
        $g=new StorageEngineGetParams(array("query"=>"test","filter"=>array("FIELD"=>"simple","OPERATOR"=>"EQUALS","VALUE"=>"Ho'la")));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE simple = 'Ho\\'la'",trim($composed));
    }

    function testFilteredQuery2()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "simple" => array("TYPE" => "String"),
                "number" => array("TYPE" => "Integer"),
                "number2" => array("TYPE" => "Integer")
            ),
            "base" => "SELECT * FROM MiTest WHERE [[%filter%]] "
        ));
        $q1->setConnection($this->connection);
        $g=new StorageEngineGetParams(array("query"=>"test",
                    "filter"=>array(
                        "FIELD"=>"simple",
                        "OPERATOR"=>"EQUALS",
                        "VALUE"=>"Ho'la",
                        "CONDITION"=>"AND",
                        "FIELDS"=>array(
                            array(
                            "FIELD"=>"number",
                            "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_GREATER,
                            "VALUE"=>2,
                            "CONDITION"=>"OR",
                            "FIELDS"=>array(
                                array(
                                "FIELD"=>"number",
                                "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_NOTNULL
                                ),
                                array(
                                    "FIELD"=>"number",
                                    "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_SMALLER,
                                    "VALUE"=>2
                                ),
                                array(
                                    "FIELD"=>"simple",
                                    "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_STARTS_WITH,
                                    "VALUE"=>"a'aa"
                                ),
                            )
                            ),
                            array(
                                "FIELD"=>"number2",
                                "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_NULL
                            )
                        ))
            )
        );
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT * FROM MiTest WHERE simple = 'Ho\'la' AND (number > 2 OR (number IS NOT NULL) OR (number < 2) OR (simple LIKE 'a\\'aa%')) AND (number2 IS NULL)",trim($composed));
    }

    function testFilteredQueryError()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "simple" => array("TYPE" => "String"),
                "number" => array("TYPE" => "Integer"),
            ),
            "base" => "SELECT * FROM MiTest WHERE [[%filter%]] "
        ));
        $q1->setConnection($this->connection);
        $g=new StorageEngineGetParams(array("query"=>"test",
                "filter"=>array(
                    "FIELD"=>"simple",
                    "OPERATOR"=>"EQUALS",
                    "VALUE"=>"Ho'la",
                    "CONDITION"=>"AND",
                    "FIELDS"=>array(
                        array(
                            "FIELD"=>"number",
                            "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_GREATER,
                            "VALUE"=>2,
                            "CONDITION"=>"OR"
                        ),
                        array(
                            "FIELD"=>"number3",
                            "OPERATOR"=>\lib\storageEngine\QueryConditionConstants::COND_NULL
                        )
                    ))
            )
        );
        $this->setExpectedException('\lib\storageEngine\StorageEngineQueryException',
            '',
            \lib\storageEngine\StorageEngineQueryException::ERR_UNKNOWN_PARAMETER);
        $composed=$q1->parse($g);
    }

    function testFieldTransforms()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array("TYPE"=>"Integer")
            ),
            "base"=>array(
                "TABLE"=>"tableTest",
                "FIELDS"=>array("a","b","c")
            ),
            "conditions"=>array(
                "filter1"=>array(
                    "TRIGGER_VAR"=>"simple",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"a=[%simple%]"
                )
            )
        ));
        $q1->setConnection($this->connection);
        // Este test tiene que sustituir los campos seleccionados.Ojo: no se hace nigun chequeo de si esos campos son realmente devueltos por la query!!
        $g=new StorageEngineGetParams(
            array("query"=>"test","requestedFields"=>array(array("field"=>"d"),array("field"=>"e","transform"=>"SUM"),array("field"=>"f","transform"=>"COUNT"))));
        $composed=$q1->parse($g);
        $this->assertEquals("SELECT d,SUM(e),COUNT(f) FROM tableTest",trim($composed));
        $extraFields=$q1->getCalculatedFields();
        $this->assertEquals(3,count($extraFields));
        $keys=array_keys($extraFields);
        $this->assertEquals("d",$keys[0]);
        $this->assertEquals(true,isset($extraFields["d"]["CALCULATED"]));
        $this->assertEquals("Decimal",$extraFields["d"]["TYPE"]);
        $this->assertEquals("FIELDTRANSFORM",$extraFields["d"]["CALCULATED"]["SOURCE"]);
        $this->assertEquals(null,$extraFields["d"]["CALCULATED"]["TRANSFORM"]);
        $this->assertEquals("SUM",$extraFields["SUM(e)"]["CALCULATED"]["TRANSFORM"]);
        $this->assertEquals("COUNT",$extraFields["COUNT(f)"]["CALCULATED"]["TRANSFORM"]);
    }

    function testFieldTransformsAndGroupings()
    {
        $q1=MysqlQueryFactory::getInstance(array(
            "parameters"=>array(
                "simple"=>array("TYPE"=>"Integer")
            ),
            "base"=>array(
                "TABLE"=>"tableTest",
                "FIELDS"=>array("a","b","c")
            ),
            "conditions"=>array(
                "filter1"=>array(
                    "TRIGGER_VAR"=>"simple",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"a=[%simple%]"
                )
            )
        ));
        $q1->setConnection($this->connection);
        // Este test tiene que sustituir los campos seleccionados.Ojo: no se hace nigun chequeo de si esos campos son realmente devueltos por la query!!
        $g=new StorageEngineGetParams(
            array("query"=>"test",
                "requestedFields"=>array(array("field"=>"d"),array("field"=>"e","transform"=>"SUM"),array("field"=>"f","transform"=>"COUNT")),
                "grouping"=>array(array("field"=>"f","transform"=>\lib\StorageEngine\StorageEngineGetParams::GROUP_MONTH),
                    array("field"=>"g","transform"=>\lib\StorageEngine\StorageEngineGetParams::GROUP_DAY),
                    array("field"=>"h","transform"=>\lib\StorageEngine\StorageEngineGetParams::GROUP_HOUR),
                    array("field"=>"i","transform"=>\lib\StorageEngine\StorageEngineGetParams::GROUP_WEEK),
                    array("field"=>"j","transform"=>\lib\StorageEngine\StorageEngineGetParams::GROUP_WEEKDAY),
                    )
            ));

        $composed=$q1->parse($g);
        $this->assertEquals("SELECT YEAR(f) as g_year,MONTH(f) as g_month,DATE(g) g_day_g,HOUR(h) g_hour_h,WEEK(i) g_week_i,DAYOFWEEK(j) g_dweek_j,d,SUM(e),COUNT(f) FROM tableTest GROUP BY YEAR(f),MONTH(f),DATE(g),HOUR(h),WEEK(i),DAYOFWEEK(j) ORDER BY g_year_f ASC,g_month_f ASC,g_day_g ASC,g_hour_h DESC,g_week_i DESC,g_dweek_j ASC",
            trim($composed));
        $expected=array (
            'g_year' =>
                array (
                    'TYPE' => 'Integer',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'f',
                            'TRANSFORM' => 'MONTH',
                            'PARAM' => 'Year',
                        ),
                ),
            'g_month' =>
                array (
                    'TYPE' => 'Integer',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'f',
                            'TRANSFORM' => 'MONTH',
                            'PARAM' => 'Month',
                        ),
                ),
            'g_g' =>
                array (
                    'TYPE' => 'Integer',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'g',
                            'TRANSFORM' => 'DAY',
                        ),
                ),
            'g_hour_h' =>
                array (
                    'TYPE' => 'Integer',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'h',
                            'TRANSFORM' => 'HOUR',
                        ),
                ),
            'g_week_i' =>
                array (
                    'TYPE' => 'Integer',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'i',
                            'TRANSFORM' => 'WEEK',
                        ),
                ),
            'g_dweek_j' =>
                array (
                    'REFERENCES' => 'j',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'GROUPING',
                            'FIELD' => 'j',
                            'TRANSFORM' => 'GROUP_WEEKDAY',
                        ),
                ),
            'd' =>
                array (
                    'TYPE' => 'Decimal',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'FIELDTRANSFORM',
                            'FIELD' => 'd',
                            'TRANSFORM' => NULL,
                        ),
                ),
            'SUM(e)' =>
                array (
                    'TYPE' => 'Decimal',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'FIELDTRANSFORM',
                            'FIELD' => 'e',
                            'TRANSFORM' => 'SUM',
                        ),
                ),
            'COUNT(f)' =>
                array (
                    'TYPE' => 'Decimal',
                    'CALCULATED' =>
                        array (
                            'SOURCE' => 'FIELDTRANSFORM',
                            'FIELD' => 'f',
                            'TRANSFORM' => 'COUNT',
                        ),
                ),
        );

        $extraFields=$q1->getCalculatedFields();
        $diff=\lib\php\ArrayTools::array_diff_assoc_recursive($extraFields,$expected);
        $this->assertEquals(0,$diff);
    }

    function testSimpleInsert()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "simple" => array("TYPE" => "Integer")
            ),
            "base" => array(
                "TABLE" => "tableTest"
            ),
            "fields"=>array(
                "a"=>array("TYPE"=>"Integer"),
                "b"=>array("TYPE"=>"String"),
                "c"=>array("TYPE"=>"DateTime")
            )
        ));
        $q1->setConnection($this->connection);
        // Este test tiene que sustituir los campos seleccionados.Ojo: no se hace nigun chequeo de si esos campos son realmente devueltos por la query!!
        $g = new StorageEngineSetParams(
            array("query" => "test",
                  "type"=>StorageEngineSetParams::INSERT,
                  "values"=>array(
                      "a"=>1,
                      "b"=>"Ho'la",
                      "c"=>"2015-02-02 23:00:12"
                  )
            ));
        $insert=$q1->parse($g);
        $this->assertEquals("INSERT INTO tableTest (a,b,c) VALUES (1,'Ho\\'la','2015-02-02 23:00:12')",trim($insert));
    }

    function testSimpleUpdate()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "key" => array("TYPE" => "Integer"),
                "key2" => array("TYPE" => "Integer")
            ),
            "base" => array(
                "TABLE" => "tableTest"
            ),
            "fields"=>array(
                "a"=>array("TYPE"=>"Integer"),
                "b"=>array("TYPE"=>"String"),
                "c"=>array("TYPE"=>"DateTime"),
                "key"=>array("TYPE"=>"Integer")
            ),
            "conditions"=>array(
                "filter1"=>array(
                    "TRIGGER_VAR"=>"key",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"d=[%key%]"
                ),
                "filter2"=>array(
                    "TRIGGER_VAR"=>"key2",
                    "DISABLE_IF"=>"",
                    "FILTER"=>"e=[%key2%]"
                )
            )
        ));
        $q1->setConnection($this->connection);
        // Este test tiene que sustituir los campos seleccionados.Ojo: no se hace nigun chequeo de si esos campos son realmente devueltos por la query!!
        $g = new StorageEngineSetParams(
            array("query" => "test",
                "type"=>StorageEngineSetParams::UPDATE,
                "params"=>array(
                    "key"=>1
                ),
                "values"=>array(
                    "a"=>1,
                    "b"=>"Ho'la",
                    "c"=>"2015-02-02 23:00:12"
                )
            ));
        $update=$q1->parse($g);
        $this->assertEquals("UPDATE tableTest SET a=1,b='Ho\\'la',c='2015-02-02 23:00:12' WHERE d=1 AND TRUE",trim($update));

        // Test de operacion DELETE
        $g = new StorageEngineSetParams(
            array("query" => "test",
                "type"=>StorageEngineSetParams::DELETE,
                "params"=>array(
                    "key"=>1,
                    "key2"=>2
                )
            ));
        $delete=$q1->parse($g);
        $this->assertEquals("DELETE FROM tableTest WHERE d=1 AND e=2",trim($delete));
    }

    function testFixedInsert()
    {
        $q1 = MysqlQueryFactory::getInstance(array(
            "parameters" => array(
                "param1" => array("TYPE" => "Integer"),
                "param2" => array("TYPE" => "String"),
                "param3" => array("TYPE" => "Integer")
            ),
            "base" => "INSERT INTO testTable (param1,param2,param3) VALUES ([%param1%],[%param2%],[%param3%])",
            "fields"=>array(
                "param1"=>array("TYPE"=>"Integer"),
                "param2"=>array("TYPE"=>"String"),
                "param3"=>array("TYPE"=>"Integer")
            )
        ));
        $q1->setConnection($this->connection);
        // Este test tiene que sustituir los campos seleccionados.Ojo: no se hace nigun chequeo de si esos campos son realmente devueltos por la query!!
        $g = new StorageEngineSetParams(
            array("query" => "test",
                "type"=>StorageEngineSetParams::INSERT,
                "values"=>array(
                    "param1"=>1,
                    "param2"=>"Ho'la",
                    "param3"=>3
                )
            ));
        $insert=$q1->parse($g);
        $this->assertEquals("INSERT INTO tableTest (a,b,c) VALUES (1,'Ho\\'la','2015-02-02 23:00:12')",trim($insert));
    }
}