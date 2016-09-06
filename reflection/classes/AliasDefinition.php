<?php
namespace lib\reflection\classes;
class AliasDefinition extends BaseDefinition
{
        function __construct($parentModel,$definition)
        {
               $this->definition=$definition;
               $this->parentModel=$parentModel;
               $this->type=$this->createType($parentModel,$definition["TYPE"],"aliases",$definition);
        }

        static function createAlias($parentModel,$type)
        {
            return new AliasDefinition($parentModel,$type->getDefinition());
        }
        function getType()
        {
            return $this->type;
        }
        function getDefinition()
        {
            return $this->type->getDefinition();
        }

        function isRelation()
        {
            return false;
        }
}
