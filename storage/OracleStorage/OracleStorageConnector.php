<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 26/06/2017
 * Time: 13:06
 */

namespace lib\storage\OracleStorage;
use \lib\storage\Base\Connectors\TreeBased;
use \lib\storage\Base\Connectors\TreeBasedException;
include_once(LIBPATH."/storage/Base/Connectors/TreeBased.php");
class OracleStorageConnectorException extends TreeBasedException
{
    const ERR_REQUEST_ERROR=101;
    const ERR_BAD_RESPONSE=102;
    const TXT_REQUEST_ERROR="Error en la peticion [%request%] : [%errcode%]";
    const TXT_BAD_RESPONSE="Respuesta inesperada: [%response%]";
}

class OracleStorageConnector extends TreeBased
{

    var $__token;
    var $__storageToken;
    var $userName;
    var $password;
    var $endPoint;
    var $identityDomain;
    var $__authenticationPerformed=false;
    var $__prefix=null;
    static $__definition = array(
        "fields" => array(
            "userName" => array("required" => true),
            "password"=> array("required" => true),
            "endPoint"=>array("required"=>true),
            "identityDomain"=>array("required"=>true),
            "PATH"=>array("required"=>false)
        )
    );

    function __construct($params)
    {
        parent::__construct($params);
        if(isset($params["PATH"]))
            $this->setPath($params["PATH"]);
    }

    function setPath($p)
    {
        $this->__prefix=trim($p,"/");
    }
    function fixPath($p)
    {
        $this->authenticate();

        $fullPath=$this->getAPIPath()."/";
        if($this->__prefix==null)
            return $fullPath.trim($p,"/");
        return $fullPath.$this->__prefix."/".trim($p,"/");
    }
    private function getAPIPath()
    {
        return $this->endPoint."/v1/Storage-".$this->identityDomain;
    }
    function dirExists($p)
    {
        // En oracle, no existe el concepto de "Directorio"
        return true;
    }

    function getCurrentPath()
    {
        return $this->__prefix;
    }

    function getFileList($path=null,$nameFilter=null,$onlyFiles=false)
    {
        $results=array();
        $this->execOnPath($path,
            function($file,$path) use (&$results)
            {
                $node = new \lib\storage\Base\Connectors\FileSystemNode();
                $node->fileSystem = $this;
                $node->path = $path;
                $node->name = $file;
                $node->isDir= false;
                $results[]=$node;
            },
            function($file,$path) use(&$results,$onlyFiles)
            {
                if($onlyFiles)
                    return;
                $node = new \lib\storage\Base\Connectors\FileSystemNode();
                $node->fileSystem = $this;
                $node->path = $path;
                $node->name = $file;
                $node->isDir= true;
                $results[]=$node;

            },
            null,$nameFilter,false);
        return $results;
    }

    function saveFile($path,$data)
    {
        return $this->putObject($path,$data);
    }

    function readFile($path)
    {
        return $this->getObject($path);
    }
    function fileExists($path)
    {
        $n=$this->getRemoteListing($path);
        return count($n)==1;
    }
    function removeFile($p)
    {
        return $this->removeObject($p);
    }

    function removePath($p, $recursive = false)
    {
        $list = $this->getRemoteListing($p);
        $final=array();
        $p=trim($p,"/");
        for($j=0;$j<count($list);$j++)
        {
            if($recursive==true || count(explode("/",trim($list[$j]["name"],"/")))<2)
                $final[]=$list[$j]["name"];
        }
        $final=array_reverse($final);
        for($k=0;$k<count($final);$k++)
            $this->removeFile($final[$k]);
    }

    function removeFilesByRegularExpression($path,$regexp,$recursive=false)
    {
        // En Oracle, no necesitamos borrar los directorios cuando hemos borrado su contenido: se borran solos
        $this->execOnPath($path,
            function($file,$path){
                $this->removeFile($path."/".$file);
            },
            null,
            null,
            $regexp,
            $recursive
        );
    }

    private function execOnPath($tree,$fileCallback,$directoryCallback,$postDirCallback=null,$regexp=null,$recursive=false)
    {
        if(is_string($tree))
        {
            $list = $this->getRemoteListing($tree);
            $tree=$this->getNestedNodeList($list,$tree);
        }
        if(!isset($tree["CHILDREN"]))
            return call_user_func($directoryCallback,$tree["NAME"],$tree["PATH"]);

        foreach ($tree["CHILDREN"] as $file) {

            if(isset($file["CHILDREN"]))
            {
                if($directoryCallback)
                    call_user_func($directoryCallback,$file["NAME"],$file["PATH"]);
                if($recursive)
                {
                    $this->execOnPath($file,$fileCallback,$directoryCallback,$postDirCallback,$regexp,$recursive);
                }
            }
            else
            {
                if($regexp==null || preg_match($regexp,$file["NAME"])) {
                    if($fileCallback)
                        call_user_func($fileCallback,$file["NAME"],$file["PATH"]);
                }
            }
        }
        if($postDirCallback)
            call_user_func($postDirCallback,$tree["NAME"],$tree["PATH"]);
    }

    function createDirectory($path,$recursive=true,$permissions=0777)
    {
        // En Oracle no hay concepto de "directorio"

    }

    function pathExists($p)
    {
        return $this->pathExists($p);

    }

    function getNodes($path,$nameFilter=null,$onlyLeaves = false)
    {
        return $this->getFileList($path,$nameFilter,$onlyLeaves);
    }

    function setData($p, $data)
    {
        $this->saveFile($p,$data);

    }
    function getData($p)
    {
        return $this->readFile($p);
    }
    function leafExists($p)
    {
        return $this->fileExists($p);

    }
    function nodeExists($p)
    {
        return $this->fileExists($p);
    }

    function removeLeaf($p)
    {
        $this->removeFile($p);
        // TODO: Implement removeLeaf() method.
    }

    function addPath($path,$recursive=true)
    {
        $this->createDirectory($path,$recursive);
    }

    function makeRequest($type,$url,$data=null)
    {
        $sentHeaders=array("X-Auth-Token: ".$this->__token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $sentHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        switch($type)
        {
            case "GET":
            {
                curl_setopt($ch, CURLOPT_HTTPGET,1);
            }break;
            case "POST":
            {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            }break;
            case "PUT":
            {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            }break;
            case "DELETE":
            {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }break;
            case "HEAD":
            {
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'HEAD');
            }break;
        }
        $headers=array();
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function($curl, $header) use (&$headers)
                {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $name = strtolower(trim($header[0]));
                    if (!array_key_exists($name, $headers))
                        $headers[$name] = [trim($header[1])];
                    else
                        $headers[$name][] = trim($header[1]);

                    return $len;
                }
            );

        $output=curl_exec ($ch);

        if(isset($headers["status"])) {
            if ($headers["status"] != "HTTP/1.1 200 OK") {
                throw new OracleStorageConnectorException(OracleStorageConnectorException::ERR_REQUEST_ERROR, array(
                    "request" => $url,
                    "errcode" => $headers["status"]
                ));
            }
        }
        else
        {
            $info=curl_getinfo ($ch ,CURLINFO_HTTP_CODE  );
            if($info<200 || $info>299)
                throw new OracleStorageConnectorException(OracleStorageConnectorException::ERR_REQUEST_ERROR, array(
                    "request" => $url,
                    "errcode" => $info
                ));
        }

        curl_close($ch);
        return array("headers"=>$headers,"content"=>$output);
    }

    function authenticate()
    {
        if($this->__authenticationPerformed)
            return $this->__token!=null;

        $this->__authenticationPerformed=true;

        $url=$this->endPoint."/auth/v1.0";
        $headers=array();

        $sentHeaders=array("X-Storage-User: Storage-".$this->identityDomain.":".$this->userName,"X-Storage-Pass: ".$this->password);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $sentHeaders);
        curl_setopt($ch, CURLOPT_HEADER,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY,1);
        curl_setopt($ch, CURLOPT_HTTPGET,1);
        $output=curl_exec ($ch);
        curl_close($ch);
        $lines=explode("\n",$output);
        for($k=0;$k<count($lines);$k++)
        {
            $lines[$k]=trim($lines[$k]);
            if($lines[$k]=="")
                continue;
            $parts=explode(":",$lines[$k]);

            if(count($parts)==1)
                $headers["status"]=trim($lines[$k]);
            else
            {
                $headers[trim($parts[0])]=trim($parts[1]);
            }
        }
        if($headers["status"]!="HTTP/1.1 200 OK") {
            throw new OracleStorageConnectorException(OracleStorageConnectorException::ERR_REQUEST_ERROR,array(
                "request"=>$url,
                "errcode"=>$headers["status"]
            ));
        }

        $this->__token=$headers["X-Auth-Token"];
        $this->__storageToken=$headers["X-Storage-Token"];

    }

    function getObject($path)
    {
        $path=$this->fixPath($path);
        $result=$this->makeRequest("GET",$path."?nc=".time());
        return $result;
    }
    function getObjectMetadata($path)
    {
        $path=$this->fixPath($path);
        $result=$this->makeRequest("HEAD",$path);
        return $result;
    }
    private function getRemoteListing($path)
    {
        $this->authenticate();
        if($this->__prefix!==null)
        {
            $path=$this->__prefix."/".trim($path,"/");
        }
        $p1=explode("/",$path);
        $parts=array();
        for($k=0;$k<count($p1);$k++)
        {
            if($p1[$k]!="")
                $parts[]=$p1[$k];
        }

        $fullPath=$this->endPoint."/v1/Storage-".$this->identityDomain."/".$parts[0];
        $fullPath.="?format=json";
        if(count($parts)>1) {
            array_shift($parts);
            $fullPath .= "&prefix=" . urlencode(implode("/", $parts));
        }

        $result=$this->makeRequest("GET",$fullPath);
        $info=$result["headers"];
        $data=null;
        if(isset($info["x-container-object-count"])) {
            $data=json_decode($result["content"],true);
            if($data===null)
                throw new OracleStorageConnectorException(OracleStorageConnectorException::ERR_BAD_RESPONSE,array("response"=>$result["content"]));
        }
        return $data;
    }
    function putObject($path,$data)
    {
        $path=$this->fixPath($path);
        $result=$this->makeRequest("PUT",$path,$data);
        return $result["headers"]["x-trans-id"];

    }
    function removeObject($path)
    {
        $path=$this->fixPath($path);
        $result=$this->makeRequest("DELETE",$path);
        // Si no ha saltado ninguna excepcion..
        return true;

    }
    function getNestedNodeList($fileList,$path)
    {
        $indexed=array("CHILDREN"=>array());

        for($j=0;$j<count($fileList);$j++)
        {
            $c=$fileList[$j]["name"];
            if($path!=null) {
                $trimmed=trim($path,"/");
                if($trimmed!="") {
                    $pos = strpos($c, $trimmed);
                    if ($pos === 0)
                        $c = substr($c, strlen($trimmed));
                }
                $currentPath=$trimmed;
            }
            else
                $currentPath="";
            $p=explode("/",trim($c,"/"));
            $fileName=array_pop($p);
            $current=& $indexed["CHILDREN"];

            for($k=0;$k<count($p);$k++)
            {
                $cP=$p[$k];
                if(!isset($current[$cP]))
                {
                    $current[$cP]=array("NAME"=>$cP,"TYPE"=>"DIR","PATH"=>$currentPath,"CHILDREN"=>array());
                }
                else
                {
                    if(!isset($current[$cP]["CHILDREN"])) {
                        $current[$cP]["CHILDREN"] = array();
                        $current[$cP]["TYPE"] = "DIR";
                    }
                }
                $current=& $current[$p[$k]]["CHILDREN"];
                $currentPath.="/".$cP;
            }
            $current[$fileName]= array("NAME"=>$fileName,"TYPE"=>"FILE","PATH"=>$currentPath);
        }
        // La estructura en $indexed es: array("CHILDREN"=>array(<path inicial>=>array(...).
        // No queremos devolver los primeros niveles de indexed, que, basicamente, modelan el path inicial.
        // Es decir, si el listado es del path "/a/b/c", va a haber una estructura del tipo:
        // array("CHILDREN"=>array("a"=>array("CHILDREN"=>array("b"=>array("CHILDREN"=>array("c"=>........))))

        if($path==null)
            return $indexed;

        // Si se especifica un path, como en el bucle estamos eliminando el path del nombre de fichero, el elemento
        // que representa el path inicial, tiene como nombre "".Reconstruimos la entrada, y lo devolvemos.
        if(isset($indexed["CHILDREN"])) {
            if (isset($indexed["CHILDREN"][""])) {
                $p = explode("/", trim($path, "/"));
                $indexed["CHILDREN"][""]["NAME"] = array_pop($p);
                $indexed["CHILDREN"][""]["PATH"] = "/" . implode("/", $p);
                return $indexed["CHILDREN"][""];
            }
            else
            {
                // El directorio en si no existia como elemento
                return $indexed;
            }
        }
        return array("CHILDREN"=>array());
    }

    function disconnect()
    {

    }

    function connect()
    {
        // TODO: Implement connect() method.
    }

    function isConnected()
    {
        // TODO: Implement isConnected() method.
    }

}