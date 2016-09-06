<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 11:17
 */

namespace lib\storageEngine\Mysql;
include_once(__DIR__."/Types.php");

use lib\php\ArrayMappedParameters;
use \lib\model\BaseException;
use \lib\storageEngine\Mysql\BaseType;

class MysqlConnectionException extends BaseException
{
    const ERR_CANT_CONNECT = 1;
    const ERR_WRITE_ERROR = 2;
    const ERR_GET_ERROR = 3;
    const ERR_METADATA_ERROR = 4;
    const ERR_BIND_ERROR=5;

    const TXT_CANT_CONNECT="Error de conexion a la BD";
    const TXT_WRITE_ERROR="Error al escribir en la BD";
    const TXT_GET_ERROR="Error al leer de la BD";
    const TXT_METADATA_ERROR="Error al obtener metadata";
    const TXT_BIND_ERROR="Error en bind de variables";
}

class MysqlConnectionParams extends ArrayMappedParameters
{
    var $host;
    var $port;
    var $database;
    var $username;
    var $password;
    var $options;
    var $driverOptions;
    var $context = array();
    static $__definition=array(
      "fields"=>array(
          "port"=>array("default"=>3306),
          "username"=>array("required"=>false),
          "password"=>array("required"=>false),
          "options"=>array("default"=>array()),
          "driverOptions"=>array("default"=>array())
      )
    );
}

class MysqlConnection
{
    var $conn;
    var $connected = false;
    var $params;
    var $manager;
    var $affectedRows=null;
    var $nRows=null;
    var $lastResult=null;
    var $lastStatement=null;

    /* Constantes de flags en campos */
    const FIELD_NOT_NULL_FLAG=1;
    const FIELD_PRI_KEY_FLAG=2;
    const FIELD_UNIQUE_KEY_FLAG=4;
    const FIELD_MULTIPLE_KEY_FLAG=8;
    const FIELD_BLOB_FLAG=16;
    const FIELD_UNSIGNED_FLAG=32;
    const FIELD_ZEROFILL_FLAG=64;
    const FIELD_BINARY_FLAG=128;
    const FIELD_ENUM_FLAG=256;
    const FIELD_AUTO_INCREMENT_FLAG=512;
    const FIELD_TIMESTAMP_FLAG=1024;
    const FIELD_SET_FLAG=2048;
    const FIELD_NO_DEFAULT_VALUE_FLAG=4096;
    const FIELD_ON_UPDATE_NOW_FLAG=8192;
    const FIELD_NUM_FLAG=32768;
    const FIELD_PART_KEY_FLAG=16384;
    const FIELD_GROUP_FLAG=32768;
    const FIELD_UNIQUE_FLAG=65536;
    const FIELD_BINCMP_FLAG=131072;
    const FIELD_GET_FIXED_FIELDS_FLAG=(1 << 18) ;
    const FIELD_FIELD_IN_PART_FUNC_FLAG=(1 << 19);

    /* Constantes de tipo en campos */
    const MYSQL_TYPE_DECIMAL=0;
    const MYSQL_TYPE_TINY=1;
    const MYSQL_TYPE_SHORT=2;
    const MYSQL_TYPE_LONG=3;
    const MYSQL_TYPE_FLOAT=4;
    const MYSQL_TYPE_DOUBLE=5;
    const MYSQL_TYPE_NULL=6;
    const MYSQL_TYPE_TIMESTAMP=7;
    const MYSQL_TYPE_LONGLONG=8;
    const MYSQL_TYPE_INT24=9;
    const MYSQL_TYPE_DATE=10;
    const MYSQL_TYPE_TIME=11;
    const MYSQL_TYPE_DATETIME=12;
    const MYSQL_TYPE_YEAR=13;
    const MYSQL_TYPE_NEWDATE=14;
    const MYSQL_TYPE_VARCHAR=15;
    const MYSQL_TYPE_BIT=16;
    const MYSQL_TYPE_TIMESTAMP2=17;
    const MYSQL_TYPE_DATETIME2=18;
    const MYSQL_TYPE_TIME2=19;
    const MYSQL_TYPE_NEWDECIMAL=246;
    const MYSQL_TYPE_ENUM=247;
    const MYSQL_TYPE_SET=248;
    const MYSQL_TYPE_TINY_BLOB=249;
    const MYSQL_TYPE_MEDIUM_BLOB=250;
    const MYSQL_TYPE_LONG_BLOB=251;
    const MYSQL_TYPE_BLOB=252;
    const MYSQL_TYPE_VAR_STRING=253;
    const MYSQL_TYPE_STRING=254;
    const MYSQL_TYPE_GEOMETRY=255;
    static $typeConversions=null;

    function __construct(MysqlConnectionParams $params)
    {
        $this->conn = new \mysqli($params->host,$params->username,$params->password,$params->database);

        if ($this->conn->connect_error) {
            throw new MysqlConnectionException(MysqlConnectionException::ERR_CANT_CONNECT,array("code"=>$this->conn->connect_errno,"text"=>$this->conn->connect_error));
        }
        $this->conn->autocommit(TRUE);
        $this->conn->multi_query('set names "utf8";set character set "utf8";set character_set_server="utf8";set collation_connection="utf8_general_ci"');
        while($this->conn->next_result());
        if(MysqlConnection::$typeConversions==null)
        {
            MysqlConnection::$typeConversions=array(
                MysqlConnection::MYSQL_TYPE_DECIMAL=>"Decimal",
                MysqlConnection::MYSQL_TYPE_TINY=>"Integer", // TINY
                MysqlConnection::MYSQL_TYPE_SHORT=>"Integer", // SHORT
                MysqlConnection::MYSQL_TYPE_LONG=>"Integer", // LONG
                MysqlConnection::MYSQL_TYPE_FLOAT=>"Float", // FLOAT
                MysqlConnection::MYSQL_TYPE_DOUBLE=>"Float", // DOUBLE
                MysqlConnection::MYSQL_TYPE_NULL=>NULL, // NULL
                MysqlConnection::MYSQL_TYPE_TIMESTAMP=>"Timestamp",// TIMESTAMP
                MysqlConnection::MYSQL_TYPE_LONGLONG=>"Integer",//LONGLONG
                MysqlConnection::MYSQL_TYPE_INT24=>"Integer",//INT24
                MysqlConnection::MYSQL_TYPE_DATE=>"Date",//DATE
                MysqlConnection::MYSQL_TYPE_TIME=>"DateTime",//TIME
                MysqlConnection::MYSQL_TYPE_DATETIME=>"DateTime",//DATETIME
                MysqlConnection::MYSQL_TYPE_YEAR=>"Integer",// YEAR
                MysqlConnection::MYSQL_TYPE_NEWDATE=>"DateTime",// NEWDATE
                MysqlConnection::MYSQL_TYPE_VARCHAR=>"String",// NEWDATE
                MysqlConnection::MYSQL_TYPE_BIT=>"Integer",// NEWDATE
                MysqlConnection::MYSQL_TYPE_TIMESTAMP2=>"Integer",// NEWDATE
                MysqlConnection::MYSQL_TYPE_DATETIME2=>"String",// NEWDATE
                MysqlConnection::MYSQL_TYPE_TIME2=>"DateTime",//TIME
                MysqlConnection::MYSQL_TYPE_NEWDECIMAL=>"Decimal",
                MysqlConnection::MYSQL_TYPE_ENUM=>"Enum",
                MysqlConnection::MYSQL_TYPE_SET=>"Enum",
                MysqlConnection::MYSQL_TYPE_TINY_BLOB=>"Blob",
                MysqlConnection::MYSQL_TYPE_MEDIUM_BLOB=>"Blob",
                MysqlConnection::MYSQL_TYPE_LONG_BLOB=>"Blob",
                MysqlConnection::MYSQL_TYPE_BLOB=>"Blob",
                MysqlConnection::MYSQL_TYPE_VAR_STRING=>"String",
                MysqlConnection::MYSQL_TYPE_STRING=>"String",
                MysqlConnection::MYSQL_TYPE_GEOMETRY=>"Geometry"
            );
        }
        // Para mapeo de tipo Geometry, ver : http://stackoverflow.com/questions/8355000/can-i-do-a-parameterized-query-containing-geometry-function

    }
    public function discoverTableFields($table)
    {
        return $this->discoverFields("SELECT * from " . $table." LIMIT 1");
    }

    /**
     * @param $query
     * @return array
     * @throws MysqlConnectionException
     *
     * Definicion de la metadata obtenida:
     *
     * name: The name of the column
       orgname: Original column name if an alias was specified
       table: The name of the table this field belongs to (if not calculated)
       orgtable: Original table name if an alias was specified
       def: The default value for this field, represented as a string
       max_length: The maximum width of the field for the result set.
       length: The width of the field, as specified in the table definition.
       charsetnr: The character set number for the field.
       flags: An integer representing the bit-flags for the field.
       type: The data type used for this field
       decimals: The number of decimals used (for numeric fields)

     */
    function discoverFields($query)
    {
        $qb=new \lib\storage\Mysql\QueryBuilder($query);
        $q=$qb->build();
        $q=preg_replace(array('/\[\[\%[^%]+\%\]\]','/\[\%[^%]+\%\]/','/{\%[^%]+\%}/'),array("","true","'-1'"),$q);
        $sentencia = mysqli_prepare($this->conn,$q);
        $res = mysqli_stmt_result_metadata($sentencia);
        if(!$res)
            throw new MysqlConnectionException(MysqlConnectionException::ERR_METADATA_ERROR,array("text"=>mysqli_error($this->conn)));
        $metaData=mysqli_fetch_fields($res);
        mysqli_free_result($res);
        $nFields=count($metaData);
        $returnedFields=array();
        for($k=0;$k<$nFields;$k++)
        {
            $fTable=$metaData[$k]->orgtable?$metaData[$k]->orgtable:$metaData[$k]->table;
            $fName=$metaData[$k]->orgname?$metaData[$k]->orgname:$metaData[$k]->name;
            $returnedFields[$fTable][$fName]=get_object_vars($metaData[$k]);

            $returnedFields[$fTable][$fName]["TYPE"]=array(
                "TYPE"=>MysqlConnection::$typeConversions[$metaData[$k]->type]
            );
        }
        return $returnedFields;
    }
    // Esta funcion nunca devolvera un "blob".
    private function doTypeInference($values)
    {
        $typeString='';
        foreach($values as $key=>$value)
        {
            if(is_numeric($value))
            {
                $parts=explode($value,".");
                // Solo hacemos este test para calcular float
                if(count($parts)==2)
                    $typeString.='d';
                else
                    $typeString .= 'i';
            }
            else
                    $typeString.='s';

        }
        return array($typeString,$values);
    }
    private function getBindings($values)
    {
        $bindString="";
        $bindValues=array();
        foreach($values as $k=>$v)
        {
            $ser=BaseType::getSerializerFor($v);
            $newBind=$ser->getBindType($v);
            if(is_array($newBind))
                $bindString.=implode("",$newBind);
            else
                $bindString.=$newBind;
            $newVals=$ser->getBindValue($v);
            if(is_array($newVals))
            {
                $bindValues=array_merge($bindValues,$newVals);
            }
            else
                $bindValues[$k]=$newVals;
        }
        return array($bindString,$bindValues);
    }

    function insert($table,$values)
    {
        // Si no existen types, se intenta derivar de la informacion de tabla
        $v1=array_keys($values);
        $q="INSERT INTO $table (".implode(",",$v1).") VALUES (".implode(",",array_fill(0,count($v1),"?")).")";
        return $this->doQ($q,$values);

    }
    //
    // EncodedQuery espera una query donde los placeholders ('?') ya existen.
    // Lo que falta es obtener la cadena de tipos, lo cual se hace a partir de los valores.
    // Logicemente, el numero de '?' y de valores deben coincidir, y estar en el mismo orden.
    function encodedQuery($query,$values=null)
    {
        if($this->lastStatement) {
            $this->lastStatement->close();
            $this->lastStatement=null;
        }

        $prepared=$this->conn->prepare($query);

        if(!$prepared)
        {
            throw new MysqlConnectionException(MysqlConnectionException::ERR_GET_ERROR,array("query"=>$query,"errno"=>$this->conn->errno,"error"=>$this->conn->error));
        }
        $this->lastStatement=$prepared;

        if($values==null)
            return $prepared;

        $vals=array_values($values);
        if(!is_object($vals[0]))
            $bTypes=$this->doTypeInference($values);
        else
            $bTypes=$this->getBindings($values);

        $binds=array();
        $bindValues=$bTypes[1];
        foreach($bindValues as $k=>$v)
            $binds[]=& $bindValues[$k];

        array_unshift($binds,$bTypes[0]);
        if(!call_user_func_array(array($prepared, "bind_param"), $binds))
            throw new MysqlConnectionException(MysqlConnectionException::ERR_BIND_ERROR,array("query"=>$query,"errno"=>$this->conn->errno,"error"=>$this->conn->error));
        return $prepared;
    }

    function doQ($q,$values=null)
    {

        $prepared=$this->encodedQuery($q,$values);
        $result=$prepared->execute();
        if(!$result)
        {
            throw new MysqlConnectionException(MysqlConnectionException::ERR_GET_ERROR,array("query"=>$q,"errno"=>$this->conn->errno,"error"=>$this->conn->error));
        }
        $res=$prepared->get_result();

//        You can detect whether the query produced a result set by checking if mysqli_stmt_result_metadata() returns NULL.
        $meta=$prepared->result_metadata();
        if($meta==null)
        {
            // La query no devuelve un resultset.Se supone entonces que es una operacion de modificacion.
            // Obtenemos el affected_rows
            $this->nRows=-1;
            $this->affectedRows=$prepared->affected_rows;
            $this->lastResult=null;
            return null;
        }
        else
        {
            $this->affectedRows=-1;
            $this->nRows=$prepared->num_rows();
            $this->lastResult=$res;
        }
        return $res;
    }

    function getLastResult()
    {
        return $this->lastResult;
    }

    function query($q,$values=null)
    {
        $this->doQ($q,$values);
        if(!$this->lastResult)
            return null;
        // Se devuelve todo el resultset de forma asociativa
        return $this->lastResult->fetch_all(MYSQLI_ASSOC);
    }
    function close()
    {
        return $this->conn->close();
    }
    function getConnection()
    {
        return $this->conn;
    }
}
