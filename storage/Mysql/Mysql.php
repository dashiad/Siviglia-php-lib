<?php
namespace lib\storage\Mysql;
/* 
Set global log en servidores 
1) execute "SET GLOBAL general_log = 'ON';"
 2) execute "SET GLOBAL log_output = 'TABLE';"
 3) take a look at the table mysql.general_log 
 
 
*/
class MysqlException extends \lib\model\BaseException
{
	const ERR_NO_CONNECTION=1;
	const ERR_NO_DB=2;
	const ERR_QUERY_ERROR=3;    
}
class Mysql
{
    var $conn;
    static $nInstances=0;
    static $nUpdates=0;
    static $lastWhere='';
    // Niveles de debug : 0 : No debug
    //                    1 : Mostrado de errores.
    //                    2 : Mostrado de todas las queries.
    var $debugLevel=0;
    var $currentDb=NULL;
    function __construct($definition)
    {
        $this->myInstance=Mysql::$nInstances;
        Mysql::$nInstances++;

        $this->definition=$definition;
    }
    

    function connect()
    {        
        extract($this->definition);

        // Siempre se abre una nueva conexion

        $conn=mysql_connect($host,$user,$password,true);
        if(!$conn)
        {
            // Por ahora, un simple "die".La aplicacion no debe
            // intentar recuperarse de un error al conectar a la bd.
            throw new MysqlException(MysqlException::ERR_NO_CONNECTION,array("host"=>$host));
            
        }
        mysql_query('set names "utf8"',$conn);
        mysql_query
            ('set character set "utf8"',$conn);
        mysql_query('set character_set_server="utf8"',$conn);
        mysql_query('set collation_connection="utf8_general_ci"',$conn);
        $this->conn=$conn;
        $this->debugLevel=$debugLevel;
    }

    function getConnectionResource()
    {
        return $this->conn;
    }

    function selectDb($database)
    {
        if(!mysql_select_db($database,$this->conn))
        {
            echo mysql_error($this->conn);
            throw new MysqlException(MysqlException::ERR_NO_DB,array("host"=>$host,"database"=>$database));
        }
        $this->currentDb=$database;
    }
    
    function showSqlErrors($q)
    {
        ob_start();
        clean_debug_backtrace();
        echo $q."<br>\n\n";
        echo mysql_error($this->conn);
        echo "\n*************************************************************************************\n";
        $buf=ob_get_clean();
        $op=fopen("/tmp/sqlErrors.txt","a");
        fputs($op,$buf);
        fclose($op);

        debug(mysql_error($this->conn));
        throw new MysqlException(MysqlException::ERR_QUERY_ERROR);
    }
    
    function lastId()
    {
        return mysql_insert_id($this->conn);
    }
    
    /**
     * select
     * Si solo se pasa una query, esta funcion retorna un array de filas.
     * Si se pasa, ademas, un campo, la funcion retorna un array asociativo, cuyas
     * keys son el valor del campo, y los values son la fila asociada a ese valor.
     * 
     * Esto sirve, por ejemplo, para que una query venga indexada por el campo clave de la tabla.
     * 
     * Hay que tener en cuenta que si la query retorna mas de una fila para el mismo valor del
     * campo clave, solo se devolvera el ultimo, por lo cual solo debe usarse con primary keys.
     * 
     * @param string $q : Query a ejecutar
     * @param string $field : Campo por el que indexar los resultados
     * @return type : array de filas, indexadas en su caso por el valor del campo pasado como parametro.
     * 
     */

    function select($q,$field="")
    {
        //echo $q;
        $results=array();
        $res=mysql_query($q,$this->conn);	
        if(!$res)
        {
            $this->showSqlErrors($q);
            return array();
        }
        if($field!="")
        {
            while($arr=mysql_fetch_assoc($res))        
            $results[$arr[$field]]=$arr;
        }
        else
        {
            while($arr=mysql_fetch_assoc($res))
            $results[]=$arr;
        }
        return $results;
    }

/**
 * fieldIndexedSelect
 * Pasandole los parametros $results, $q, $totalRows, este metodo hace lo siguiente:
 * - En $results, se almacenan key/values, con key cada uno de los campos de la query,
 * y value, un array con los valores de ese campo que se han obtenido de todas las filas.
 * - Tanto totalRows, como el valor retornado, es el numero de filas que se han procesado
 *   (que coincide con el numero de elementos de todos los arrays que existen en $result).
 * 
 * Si se le pasa el parametro "reindexBy", se rellena el array "indexedArr", con campos
 * clave/valor, donde la clave son los diferentes valores del campo indicado por "reindexBy",
 * y el valor son arrays con los indices de las filas (en $results) asociadas a ese valor de campo.
 * En este caso, el valor devuelto, nRows, es un array indexado por cada valor de campo, que indica
 * el numero de filas encontradas con ese valor de campo.
 * 
 * 
 * @param array $results : Array donde devolver los resultados
 * @param string $q
 * @param int $totalRows
 * @param string $reindexBy
 * @param int $indexedArr
 * @return int 
 */

function & fieldIndexedSelect(& $results, $q,& $totalRows,$reindexBy="",& $indexedArr=array())
{
	
    //echo "<b>".$reindexBy.": ".$q."</b><br>";
    $res=mysql_query($q,$this->conn);
    if(!$res)
    {
        $this->showSqlErrors($q);
        return 0;
    }
    
    $keys=0;
    $results=array();
    $totalRows=0;
    if($reindexBy)
         $nRows=array();
    else
        $nRows=0;

    //echo $q;exit();
    while($arr=mysql_fetch_assoc($res))
    {
        
        if(!$keys)
        {
            $keys=array_keys($arr);
            $nKeys=count($keys);
        }
        $p=& $results;

        for($k=0;$k<$nKeys;$k++)
            $p[$keys[$k]][]=$arr[$keys[$k]];

        if($reindexBy!="")
        {
            // Se mantiene el array que apunta a la fila segun el campo por el que se
            // desea reindexar los resultados.
            // asi, si la fila 1 es id:20,nombre 'a',
            // $p es {"id"=>array(20),"nombre"=>array('a')) y $indexedArr es {20=>array(0))
            $indexedArr[$arr[$reindexBy]][]=$totalRows;
            $nRows[$arr[$reindexBy]]++;
        }
        else
            $nRows++;
        $totalRows++;
    }
    return $nRows;
}

function selectColumn($q,$field)
{
   $res=mysql_query($q,$this->conn);
   $results=array();
   while($arr=mysql_fetch_assoc($res))
	$results[]=$arr[$field];
   return $results;
}
function selectIndexed($q,$field="")
{
    $results=array();
    $res=mysql_unbuffered_query($q,$this->conn);	
    
    $keys=null;
    if(!$res)
    {
        $this->showSqlErrors($q);
        return array();
    }
    
    if($field=="")
    {
        while($arr=mysql_fetch_assoc($res))
        {
            foreach($arr as $key=>$value)
                $results[$key][]=$value;
        }
    }
    else
    {
        while($arr=mysql_fetch_assoc($res))
        {
            
            foreach($arr as $key=>$value)
                $results[$arr[$field]][$key][]=$value;
        }

    }
    return $results;

}


    function selectAll($q,& $nRows)
    {
        $results=array();        
        $res=mysql_query($q,$this->conn);    
        if(!$res)
        {
            //echo $q;
            //_d($q);       
	     
            throw new MysqlException(MysqlException::ERR_QUERY_ERROR,array("message"=>mysql_error($this->conn),"query"=>$q));
        }
        $nRows=0;
        while($arr=mysql_fetch_assoc($res))
        {
            $results[]=$arr;
            $nRows++;
        }
        return $results;
    }


    function doQ($q,$ignoreErrors=0)
    {
        if(is_array($q))
        {
            $nQueries=count($q);
            for($k=0;$k<$nQueries;$k++)
                $this->doQ($q[$k],$ignoreErrors);
            return;
        }
        
        $res=mysql_query($q,$this->conn);
        
        if(!$res && $ignoreErrors==0)
        {            
            $this->showSqlErrors($q);
        }
        return $res;
    }
    
    function query($q)
    {
        
        $res=mysql_query($q,$this->conn);
        if(mysql_error($this->conn))
        {
            $this->showSqlErrors($q);
            return null;
        }
        return mysql_affected_rows($this->conn);
    }
    function cursor($q)
    {
        $res=mysql_query($q,$this->conn);
        if(!$res)
        {
            $this->showSqlErrors($q);
            return null;
        }
        return $res;
    }
    function fetch($res)
    {
        if(!$res)
            return false;
        else
            return mysql_fetch_assoc($res);
    }
    
    function delete($q)
    {
        return $this->query($q);
    }
    
    function update($q)
    {
        return $this->query($q);
    }
    
    function insert($q)
    {        
        $res=mysql_query($q,$this->conn);
        //var_dump($res);
        if(mysql_error($this->conn))
        {
            $this->showSqlErrors($q);
            return null;
        }
        return mysql_insert_id($this->conn);
    }

       
   
   function updateFromAssociative($table,$assocValueArray,$wherePart,$autoQuote=true)
   {
       
       $sqlStr="UPDATE ".$table." SET ";
	          
       $c=($autoQuote?"'":"");
       
       while(list($key, $value) = each($assocValueArray)) 
       {
            $sets[]="`".$key."`=".$c.$value.$c." ";
       }
       $sqlStr.=implode(",",$sets);
           
       if($wherePart)
       {
		    if(is_array($wherePart))
			{
				while(list($key,$value)=each($wherePart))				
					$wheres[]=$key."=".$c.$value.$c;				                				
				$sqlStr.=" WHERE ".implode(" AND ",$wheres);
			}
			else
				$sqlStr.=" WHERE ".$wherePart;				
       }

       if ($table == 'ps_product' && $wherePart == Mysql::$lastWhere) {
           Mysql::$nUpdates++;
           if (Mysql::$nUpdates > 5) {
               $e = new \Exception;
               error_log(var_export($e->getTraceAsString(), true), 3, '/tmp/update_crash.log');
               exit();
           }
       }
       else {
           Mysql::$nUpdates=0;
       }
       Mysql::$lastWhere=$wherePart;

       return $this->update($sqlStr);
   }
   
   function insertFromAssociative($table,$data)
   {
       $q="INSERT INTO ".$table." (`".implode("`,`",array_keys($data))."`) VALUES (".implode(",",array_values($data)).")";
       //echo $q;
       return $this->insert($q);
   }
   
   
    function insertQ($table,$fields,$autoQuote=true)
    {
        $c=($autoQuote?"'":"");
        return $this->doQ("INSERT INTO ".$table." (`".implode("`,`",array_keys($fields))."`) VALUES (".$c.implode($c.",".$c,array_values($fields)).$c.")");
    }
    
    
    static function getCreateTableSQL(& $obj)
    {
        if(!is_a($obj,'\lib\model\DescribedClass'))
            return "";
        
        include_once(LIBPATH."/datatypes/TypeFactory.php");    
        $sqlStr="CREATE TABLE ".$obj->name." (";
        $fields=& $obj->definition["FIELDS"];        
        $sql=& $obj->definition["STORAGE"]["MYSQL"];
        $fieldNames=array_keys($fields);
        $nFields=count($fieldNames);
        for($k=0;$k<$nFields;$k++)
        {
            $sqlStr.=$k>0?',':'';
            $curFieldName=$fieldNames[$k];
            $type=\lib\model\types\TypeFactory::getType($obj->definition["OBJECT"],$curFieldName,$fields[$curFieldName]);
            $sqlStr.=$type->getSQLDefinition();            
        }
        $sqlStr.=")";
        return $sqlStr;
    }

    function getSequenceQuery($maxNumber)
    {
        $units=array("UNI","DECS","CEN","MIL","DECMIL","CENMIL");
        $nDigits=1;
        $number=$maxNumber;
        while(floor($number/10) > 0)
        {
            $nDigits++;
            $number=$number/10;
        }
        $q="SELECT SEQ.v FROM ( SELECT (";
        for($k=0;$k<$nDigits;$k++)
        {
            $q.=($k>0?"+":"").$units[$k].".v";
        }
        $q.=") v FROM ";
        for($k=0;$k<$nDigits;$k++)
        {
            $max=($k==$nDigits-1?$number:9);
            $zeroes=str_pad(1,$k+1,"0");
            if($k+1!=$nDigits)
                $numbers=range($zeroes,$max*$zeroes,$zeroes);
            else
            {
                $numbers=array();
                for($j=1;$j<=$number;$j++)
                    $numbers[]=$j*$zeroes;
            }
            $subQuery[]="(SELECT 0 v UNION ALL SELECT ".implode(" v UNION ALL SELECT ",$numbers)." v )".$units[$k];
        }
        $q.=implode($subQuery," CROSS JOIN ");
        $q.=") SEQ WHERE SEQ.v < ".$maxNumber." ORDER BY v";
        return $q;
        
    }

    function getTableSchema($table)
    {
        $q="SHOW FIELDS FROM `$table`";
        $data=$this->select($q);
        $info["NCOLUMNS"]=count($data);

        for($k=0;$k<$info["NCOLUMNS"];$k++)
        {
            $cField=array();
            $current=$data[$k];
            $curField=$current["Field"];
            $rawType=$current["Type"];
            $carPos=strpos($rawType,'(');
            if($carPos)
            {
                $cField["TYPE"]=substr($rawType,0,$carPos);
                $endCarPos=strpos($rawType,')');
                $contents=substr($rawType,$carPos+1,$endCarPos-$carPos-1);
                
                if($cField["TYPE"]=="enum")
                    $cField["VALUES"]=explode(",",str_replace("'","",$contents));
                else
                {
                    $parts=explode(",",$contents);                    
                    $cField["SIZE"]=$parts[0];
                    if($parts[1])
                        $cField["DECIMALS"]=$parts[1];                    
                }
                $cField["TYPE_EXTRA"]=substr($rawType,$endCarPos+1);
            }
            else
                $cField["TYPE"]=$rawType;
            
           $cField["NULL"]=($current["Null"]=="NO"?0:1);
           $cField["KEY"]=$current["Key"];
           $cField["DEFAULT"]=$current["Default"];
           $cField["EXTRA"]=$current["Extra"];

           $info["FIELDS"][$curField]=$cField;
        }
        return $info;
    }

    function batch($queryArr,$ignoreErrors=false)
    {
        foreach($queryArr as $key=>$value)
        {
            $res=mysql_query($value,$this->conn);
            if(!$res && $ignoreErrors==false)
            {         
                 throw new MysqlException(MysqlException::ERR_QUERY_ERROR,array("query"=>$value,"message"=>mysql_error($this->conn)));
            }
        }
    }

    function selectCallback($q,$cb)
    {
        $cursor=$this->cursor($q);
        while($arr=$this->fetch($cursor))
            $cb($arr);
    }
    function getCurrentDatabase()
    {
        $data=$this->select("SELECT database() as dname");
        return $data[0]["dname"];
    }
    function getStatus()
    {
        $status=$this->selectAll("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Created_tmp_disk_tables','Max_used_connections','Open_tables','Select_full_join','Slow_queries');",$nRows);
        return $status;
    }
    function getSlaveStatus()
    {
        $status=$this->selectAll("SHOW SLAVE STATUS",$nRows);
        $cols=array(
            "Slave_IO_Running"=>$status[0]["Slave_IO_Running"],
            "Slave_SQL_Running"=>$status[0]["Slave_SQL_Running"],
            "Last_Error"=>$status[0]["Last_Error"],
            "Last_SQL_Error"=>$status[0]["Last_SQL_Error"],
            "Seconds_Behind_Master"=>$status[0]["Seconds_Behind_Master"]
        );
        return $cols;
    }
    function getFullStatus()
    {
        return array("mysql"=>$this->getStatus(), "slave"=>$this->getSlaveStatus());
    }
}
?>
