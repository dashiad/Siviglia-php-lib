<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/06/14
 * Time: 12:23
 */

namespace lib\model\types;
include_once(LIBPATH."/model/types/BaseType.php");
class DataReferenceTypeException extends BaseTypeException {
    const ERR_CANT_FIND_REFERENCE=100;

    const TXT_CANT_FIND_REFERENCE="No se encuentra la referencia a %model%::%field% ";
}
class DataReferenceType extends BaseType{

    var $refModel;
    var $refField;
    var $resolvedModel;
    var $resolvedField;
    var $contentType;
    function __construct($definition,$val=null)
    {
        $definition["TYPE"]="DataReference";
        parent::__construct($definition,$val);
        $ref=$this->definition["REFERENCES"];
        $this->refModel=$ref["MODEL"];
        $this->refField=$ref["FIELD"];

    }
    function validate($value)
    {
        if(is_a($value,'\lib\model\types\ArrayType'))
        {
            $this->contentType="Array";
            $def=$value->getSubTypeDef();
            if(!TypeFactory::isSameField($def,$this->refModel,$this->refField))
            {
                // Si no apunta directamente al mismo, se ve si tanto el tipo local como remoto son de tipo int
                $type=TypeFactory::getType( null,$this->definition["REFERENCES"]);
                $remType=TypeFactory::getType(null,$def);
                if(is_a($type,'\\lib\\model\\types\\Integer') && is_a($remType,"\\lib\\model\\types\\Integer"))
                    return true;
            }
            throw new DataReferenceTypeException(DataReferenceTypeException::ERR_CANT_FIND_REFERENCE,
                array("model"=>$this->refModel,"field"=>$this->refField,"mode"=>"Array"));
        }
        else
        {
            if(is_a($value,'\lib\datasource\StorageDataSource'))
            {
                $this->contentType="DataSource";
                $dsField=$this->getRequiredDsField($value);
                if(!$dsField)
                    throw new DataReferenceTypeException(DataReferenceTypeException::ERR_CANT_FIND_REFERENCE,
                    array("model"=>$this->refModel,"field"=>$this->refField,"ds"=>$value->dsName));
            }
            else
            {
                $this->contentType="Fixed";
                // Se mira si es un dato compatible con el campo especificado en la definicion.
                $type=\lib\model\types\TypeFactory::getFieldTypeInstance($this->refModel,$this->refField);
                return $type->validate($value);
            }
        }
    }
    function getRequiredDsField($value)
    {
        // Hay que encontrar si datasource retorna el campo que tenemos especificado en la definicion,
        // sea porque es el mismo campo, sea porque apunta a el, sea porque ambos apuntan al mismo.
        $oDef=$value->getOriginalDefinition();
        $fields=$oDef["FIELDS"];

        foreach($fields as $key=>$val)
        {
            if(\lib\model\types\TypeFactory::isSameField($val,$this->refModel,$this->refField))
                return $key;
        }
        return null;
    }
    function getContentType()
    {
        return $this->contentType;
    }
} 