<?php
namespace lib\reflection\classes;
class DataSourceIncludeDefinition
{
    function __construct($definition)
    {
        $this->definition=$definition;
    }
    static function create($relationshipName,$relationshipField,$remoteDs,$joinType="LEFT")
    {
        $relations=$relationshipField->getRelation();
        if(is_array($relations))
            $relation=array_flip($relations);
        else
            $relation=array($relations=>$relationshipName);
        return new DataSourceIncludeDefinition(array(
            "OBJECT"=>$relationshipField->getTargetObject(),
            "DATASOURCE"=>$remoteDs,
            "JOINTYPE"=>$joinType,
            "JOIN"=>$relation
            ));
    }
    function getDefinition()
    {
        return $this->definition;
    }
}
