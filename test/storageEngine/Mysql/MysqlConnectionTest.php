<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 15:25
 */

namespace lib\test\storageEngine\Mysql;
use lib\storageEngine\Mysql\MysqlConnection;

include_once(PROJECTPATH."/lib/storageEngine/Mysql/MysqlConnection.php");
include_once(PROJECTPATH."/lib/model/types/BaseType.php");

class MysqlConnectionTest extends \PHPUnit_Framework_TestCase {
    var $conn;
    var $rawConn;
    function setUp()
    {
        $this->rawConn = new \mysqli(MYSQL_HOST,MYSQL_USERNAME,MYSQL_PASSWORD);
        if(!$this->rawConn)
        {
            $this->markTestSkipped(
                'Cannot connect to the default Mysql DB.'
            );
            return;
        }
        $this->rawConn->query("CREATE DATABASE IF NOT EXISTS ".MYSQL_DB);
        $this->rawConn->query("USE ".MYSQL_DB);
        $this->rawConn->query("DROP TABLE IF EXISTS ConnTests");
        $this->rawConn->query("CREATE TABLE ConnTests (IntField INT,StringField VARCHAR(30),DateField DATE,DateTimeField DATETIME,TimeStampField TIMESTAMP,TextField TEXT,BooleanField BOOL,EnumField ENUM('a','b','c'))");
        while($this->rawConn->next_result());
        $connParams=new \lib\storageEngine\Mysql\MysqlConnectionParams(
            array(
                "host"=>MYSQL_HOST,
                "port"=> MYSQL_PORT,
                "username"=>MYSQL_USERNAME,
                "password"=>MYSQL_PASSWORD,
                "database"=>MYSQL_DB
            )
        );
        $this->conn=new MysqlConnection($connParams);
    }
    function rawGet($q)
    {
        $data=array();
        $res=$this->rawConn->query($q);
        while ($row = $res->fetch_assoc()) {
            $data[]=$row;
        }
        /* free result set */
        $res->free();
        return $data;
    }
    function testSimpleInsert()
    {
        $this->rawConn->query("TRUNCATE ConnTests");
        $this->conn->insert("ConnTests",array("IntField"=>15));
        $data=$this->rawGet("SELECT * FROM ConnTests");
        $this->assertEquals(1,count($data));
        $this->assertEquals(15,$data[0]["IntField"]);
    }
    function testMultipleSimpleInsert()
    {
        $this->rawConn->query("TRUNCATE ConnTests");
        $insertedVals=array(
            "IntField"=>25,
            "StringField"=>'Hola',
            "DateField"=>'2015-06-01',
            "DateTimeField"=>'2015-07-01 22:30:00',
            "TextField"=>'Texto de prueba',
            "BooleanField"=>"1",
            "EnumField"=>"c"
        );
        $this->conn->insert("ConnTests",$insertedVals);
        $data=$this->rawGet("SELECT * FROM ConnTests");
        $this->assertEquals(1,count($data));
        foreach($insertedVals as $k=>$v)
        {
            $this->assertEquals($v,$data[0][$k]);
        }
    }
    function testTypeInsert()
    {
        $this->rawConn->query("TRUNCATE ConnTests");
        $baseInsertedVals=array(
            "IntField"=>25,
            "StringField"=>'Hola',
            "DateField"=>'2015-06-01',
            "DateTimeField"=>'2015-07-01 22:30:00',
            "TextField"=>'Texto de prueba',
            "BooleanField"=>"1",
            "EnumField"=>"c"
        );
        $insertedVals=array(
            "IntField"=>new \lib\model\types\IntegerType(array(),25),
            "StringField"=>new \lib\model\types\StringType(array(),'Hola'),
            "DateField"=>new \lib\model\types\DateType(array(),'2015-06-01'),
            "DateTimeField"=>new \lib\model\types\DateTimeType(array(),'2015-07-01 22:30:00'),
            "TextField"=>new \lib\model\types\TextType(array(),'Texto de prueba'),
            "BooleanField"=>new \lib\model\types\BooleanType(array(),true),
            "EnumField"=>new \lib\model\types\EnumType(array("VALUES"=>array("a","b","c","d")),"c")
        );
        $this->conn->insert("ConnTests",$insertedVals);
        $data=$this->rawGet("SELECT * FROM ConnTests");
        $this->assertEquals(1,count($data));
        foreach($baseInsertedVals as $k=>$v)
        {
            $this->assertEquals($v,$data[0][$k]);
        }

    }
    function testSimpleSelect()
    {
        $this->rawConn->query("TRUNCATE ConnTests");
        $r=$this->conn->query("SELECT * FROM ConnTests");
        // Debe ser un array con 0 filas
        $this->assertEquals(true,is_array($r));
        $this->assertEquals(0,count($r));
        // La siguiente query no devuelve un resultset
        $r=$this->conn->query("INSERT INTO ConnTests (IntField) VALUES (25)");
        $this->assertEquals(null,$r);

        $r=$this->conn->query("SELECT * FROM ConnTests");
        $this->assertEquals(true,is_array($r));
        $this->assertEquals(1,count($r));
        $this->assertEquals(25,$r[0]["IntField"]);

        $this->conn->query("INSERT INTO ConnTests (IntField) VALUES (25),(30),(33),(32)");
        $r=$this->conn->query("SELECT SQL_CALC_FOUND_ROWS * FROM ConnTests LIMIT 2");
        $r2=$this->conn->query("SELECT FOUND_ROWS() as n");
        $this->assertEquals(2,count($r));
        $this->assertEquals(1,count($r2));
        $this->assertEquals(5,$r2[0]["n"]);

    }
} 