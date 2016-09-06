<?php
namespace lib\storage\Dictionary;
class DictionarySerializer
{
    private $serializerType;

    function __construct($definition,$serType)
    {
        $this->serializerType=$serType;
        $this->definition=$definition;
    }

    function getSerializerType()
    {
        return $this->serializerType;
    }


}