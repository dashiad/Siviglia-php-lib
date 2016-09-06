<?php
namespace lib\reflection\model\types;
class ModelReferenceType
{
        function __construct($definition)
        {
                $this->definition=$definition;
        }
        static function create($model,$field)
        {
                return new ModelReferenceType(array("MODEL"=>$model,"FIELD"=>$field));
        }
        function getDefinition()
        {
                return $this->definition;
        }
}
?>

