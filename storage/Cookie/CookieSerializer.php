<?php

namespace lib\storage\Cookie;
class CookieSerializerException extends \lib\model\BaseException
{
    const ERR_NO_NAME=1;
    const ERR_NO_SUCH_OBJECT = 2;
    const ERR_INCORRECT_CHECKSUM=3;

}

/*
    DEFINIICION ENTRA EN OBJETOS:
    "SERIALIZERS"=>array(
         "COOKIE"=>array(
            "NAME"=>"<nombre de la cookie>",
            "PATH"=>"" (default,"/"),
            "DOMAIN"=>"" (defaults to Host in Request object),
            "EXPIRES"=>"<number of seconds>"
            "CYPHER"=>array("TYPE"=>(Rijndael/Blowfish), "KEY"=>"<key>","IV"=>"iv"),
            "CHECKSUM"=>1/0 default 0
            "FIELDSEPARATOR"=>"", default '¤'
            "KEYSEPARATOR"=>"" default "|",
            "SECURE"=> <default 0>
         )
     );
*/
class CookieSerializer extends \lib\storage\StorageSerializer
{
    const MAX_COOKIE_TIMESTAMP=2147483647;
    var $path;
    var $origDefinition;
    var $domain;
    var $expires;
    var $fieldSeparator;
    var $keySeparator;
    var $name;
    var $defaultsLoaded=false;

    function __construct($definition,$useDataSpace=true)
    {
        $this->origDefinition=$definition;

        parent::__construct($definition,"HTML");        
        $this->loadDefaults();        
    }


    function loadDefaults($queryDef=null)
    {
        if($this->defaultsLoaded)
            return;
        $this->defaultsLoaded=true;
        if($queryDef)
            $this->definition=$queryDef;


        if(!isset($this->definition["ADDRESS"]["NAME"]))
        {
           throw new CookieSerializerException(CookieSerializerException::ERR_NO_NAME,array());
        }
        else
            $this->name=$this->definition["ADDRESS"]["NAME"];

        $address=$this->definition["ADDRESS"];
        if(!isset($address["PATH"]))
        {
            $this->path="";
        }
        else
        {
            $p=$address["PATH"];
            $p=rawurlencode($p);
            $p = str_replace('%2F', '/', $p);
            $p = str_replace('%7E', '~', $p);
            $this->path=$p;
        }
        if(!isset($address["DOMAIN"]))
        {
            global $registry;
            $this->domain=$registry->getRequest()->getHost();
        }
        else
            $this->domain=$address["DOMAIN"];

        if(isset($address["EXPIRES"]))
            $this->expires=time()+($address["EXPIRES"]);
        else
            $this->expires=CookieSerializer::MAX_COOKIE_TIMESTAMP;


        if(isset($address["FIELDSEPARATOR"]))
            $this->fieldSeparator=$address["FIELDSEPARATOR"];
        else
            $this->fieldSeparator=='¤';

        if(!isset($address["KEYSEPARATOR"]))
            $this->keySeparator=$address["KEYSEPARATOR"];
        else
            $this->keySeparator='|';

        if(isset($address["CYPHER"]))
        {
            $cypherClass='\lib\php\crypto\\'.$address["CYPHER"]["TYPE"];
            $this->cypher=new $cypherClass($address["CYPHER"]["KEY"],$address["CYPHER"]["IV"]);
        }
        else
            $this->cypher=null;

        if(isset($address["CHECKSUM"]))
            $this->checksum=$address["CHECKSUM"];
        else
            $this->checksum=0;
        if(isset($address["SECURE"]))
            $this->secure=$address["SECURE"];
        else
            $this->secure=0;
    }


    function unserialize($object, $queryDef = null, $filterValues = null)
    {
        $def=$this->__loadExtraDefinition($object->getDefinition());

        $this->loadDefaults($def);

        $object->__setSerializer($this);


        if(!isset($_COOKIE[$this->name]))
        {
            throw new CookieSerializerException(CookieSerializerException::ERR_NO_SUCH_OBJECT,array("name"=>$this->name));
        }
        $contents=$_COOKIE[$this->name];
        if($this->cypher)
            $contents=$this->cypher->decrypt($contents);

        if($this->cypher && $this->checksum)
        {
            $mbStrValue = ((1 << 1) & ini_get('mbstring.func_overload')) ? 1 : 2;
            $checksum = crc32($this->cypher->getIV().substr($contents, 0, strrpos($contents, $this->fieldSeparator) + $mbStrValue));
        }
        $fields=explode($this->fieldSeparator,$contents);
        $data=array();
        foreach ($fields as $keyAndValue)
        {
            $singleField = explode($this->keySeparator, $keyAndValue);
            if (sizeof($singleField) == 2)
                $data[$singleField[0]] = $singleField[1];
            else
                $data[$singleField[0]]=null;
        }
        if($data["checksum"] && $checksum)
        {
            if((int)($data["checksum"])!=$checksum)
                throw new CookieSerializerException(CookieSerializerException::ERR_INCORRECT_CHECKSUM);
        }
        $tFields=$object->__getFields();
        foreach($tFields as $key=>$value)
        {
            if($value->isAlias())
                continue;
            if(isset($data[$key]))
            {
                $value->unserialize($data,$this->getSerializerType());
            }
        }
    }

    function _store($object, $isNew=false, $dirtyFields=false)
    {
        $def=$this->__loadExtraDefinition($object->getDefinition());
        $this->loadDefaults($def);
        $tFields=$object->__getFields();
        $fields=array();
        foreach ($tFields as $key => $value)
        {
            $curField=$key.$this->keySeparator;
            if(!$value->is_set())
            {
                if(!isset($dirtyFields[$key]))
                {
                    continue;
                }
                if($isNew && $value->getType()->hasDefaultValue())
                    $value->getType()->setValue($value->getType()->getValue());
            }
            if($value->isAlias())
                continue;

            $subVals = $value->serialize($this->getSerializerType());
            if(is_array($subVals))
            {
                // Una relacion...Seguimos rompiendo compatibilidad con multi-campos.
                $vals=array_values($subVals);
                $curField.=$vals[0];
            }
            else
                $curField.=($subVals===null?'':$subVals);
            $fields[]=$curField;
        }
        $contents=implode($this->fieldSeparator,$fields);
        if($this->cypher)
        {
            if($this->checksum)
            {
                $contents .= 'checksum|'.crc32($this->cypher->getIV().$contents);
            }
            $contents=$this->cypher->encrypt($contents);
        }
        $this->_setCookie($contents);
    }
    protected function _setCookie($contents)
    {
        if(!headers_sent())
            return setcookie($this->name, $contents, $this->expires, $this->path, $this->domain, 0, true);
        else
        {

            $cad=$this->name."=".rawurlencode($contents);
            if($this->expires)
                $cad.=";expires=".gmdate('D, d-M-Y H:i:s \G\M\T',$this->expires);
            if($this->path)
                $cad.=";path=".$this->path;
            if($this->domain)
                $cad.=";domain=".$this->domain;
            if($this->secure)
                $cad.=";secure";

            echo '<script type="text/javascript">';
            echo 'document.cookie="'.$cad.'";';
            echo '</script>';
        }
    }

    function delete($table, $keyValues=null)
    {
        if(!headers_sent())
        {
            unset($_COOKIE[$this->name]);
        }
        else
        {
            $cad=$this->name+"="+";expires=Thu,01-Jan-1970 00:00:01 GMT;";
            echo '<script type="text/javascript">';
            echo 'document.cookie="'.$cad.'";';
            echo '</script>';
        }
    }


    function subLoad($definition, & $relationColumn)
    {
        return null;
    }

    function count($definition, & $model)
    {
        if(isset($_COOKIE[$this->name]))
            return 1;
        return 0;
    }

    function createStorage($modelDef, $extraDef = null)
    {
    }

    function destroyStorage($object)
    {
    }

    function createDataSpace($spaceDef)
    {
    }

    function existsDataSpace($spaceDef)
    {
        return true;
    }

    function destroyDataSpace($spaceDef)
    {
    }

    function useDataSpace($dataSpace)
    {
    }
    function getCurrentDataSpace()
    {
        return "COOKIE";
    }
}



?>
