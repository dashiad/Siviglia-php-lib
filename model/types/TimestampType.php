<?php
namespace lib\model\types;
class TimestampType extends DateTimeType
{
        function __construct($definition,$value=false)
        {
                $definition["TYPE"]="Timestamp";
                $definition["DEFAULT"]="NOW";
                DateTimeType::__construct($definition,$value);
                $this->flags |= BaseType::TYPE_NOT_EDITABLE;
        }
}
