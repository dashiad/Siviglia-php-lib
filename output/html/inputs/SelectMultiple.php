<?php
namespace lib\output\html\inputs;
class SelectMultiple extends DefaultInput
{
    var $aliasInstance;
    function __construct($name,$fieldDef,$inputDef)
    {
        parent::__construct($name,$fieldDef,$inputDef);        
        $this->definition=$this->getMetaData();

    }

    function unserialize($val)
    {
        // Aunque $val sea nulo, significa que no hay ningun elemento seleccionado, asi que se supone valido.
        $this->isSet=true;
             if(!is_array($val) || count($val)==0)
             {
                 $this->value=array();
                return;
             }                
             
             $this->value=$val;

    }

    function getMetaData()
    {
        $metaData=null;
        if(isset($this->fieldDef["TARGET_RELATION"]))
        {
            // Se obtiene el modelo donde esta definida la relacion
            $modelInstance=\lib\model\BaseModel::getModelInstance($this->fieldDef["MODEL"]);
            // Se obtiene el alias al que apunta el campo.
            $aliasInstance=$modelInstance->__getField($this->fieldDef["TARGET_RELATION"]);
            $this->aliasInstance=$aliasInstance;
            $remoteInstance=\lib\model\BaseModel::getModelInstance($aliasInstance->getRemoteModelName()->getNormalizedName());
            $valueFields=$this->inputDef["PARAMS"]["VALUE"];
            
            foreach($valueFields as $curField)
            {
                 $metaData[$curField]=$remoteInstance->__getFieldDefinition($curField);
            }
         }
        return $metaData;
    }

    function getDataSet()
    {
        // En caso de que haya mas de 1 campo value, los valores del input vienen separados por comas
        if(isset($this->inputDef["VALUE_SEPARATOR"]))
            $separator=$this->inputDef["VALUE_SEPARATOR"];
        else
            $separator=",";
                    
        $valueFields=$this->inputDef["PARAMS"]["VALUE"];
        $nVals=count($this->value);
        $nFields=count($valueFields);        
        // A partir de la metadata, hay que obtener los serializadores de los tipos.
        if($this->definition)
        {
            for($k=0;$k<$nFields;$k++)
            {                
                $curField=$valueFields[$k];                
                $type=\lib\model\types\TypeFactory::getType(null,$this->definition[$curField]);
                $types[$k]=$type;
                $serializers[$k]=\lib\model\types\TypeFactory::getSerializer($type,"HTML");
            }
        }
        $remoteMap=$this->aliasInstance->getRemoteMapping();
        for($h=0;$h<$nVals;$h++)
        {
            $parts=explode($separator,$this->value[$h]);            
            for($k=0;$k<$nFields;$k++)
            {
                if($serializers[$k])
                {
                    $serializers[$k]->unserialize($types[$k],$parts[$k]);
                    $newVal=$types[$k]->getValue();
                }
                else
                    $newVal=$parts[$k];
                $data[$h][$remoteMap[$valueFields[$k]]]=$newVal;
            }
        }

        $dataSet= new \lib\model\types\DataSet($this->definition,$data,$nVals,$nVals,null,null);        
        return $dataSet;
    }
}

