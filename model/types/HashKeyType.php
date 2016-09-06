<?php namespace lib\model\types;
class HashKeyType extends StringType {
    function __construct(& $definition,$value=null)
    {
        if(!isset($definition["MINLENGTH"]))
		    $definition['MINLENGTH']=1;
        if(!isset($definition["MAXLENGTH"]))
		    $definition['MAXLENGTH']=32;
        StringType::__construct($definition,$value);
    }

    function setValue($val)
    {
        parent::setValue($this->getSalt().substr(str_replace('+', '.', base64_encode(sha1($value, true))), 0, 22));
    }
    function setRandomValue()
    {
        $this->setValue(microtime(true));
    }
    function getHashed($values)
    {
        return crypt(implode("",$values), $this->getSalt() . $this->value);
    }
    function checkHash($values,$hashedString)
    {
            return $hashedString==$this->getHashed($values);
    }
    function getSalt()
    {
        return isset($this->definition["SALT"])?$this->definition["SALT"]:'';
    }
}

class HashKeyMeta extends \lib\model\types\BaseTypeMeta
{
    function getMeta($type)
    {
        $def=$type->getDefinition();
        unset($def["SALT"]);
        return $def;
    }
}
