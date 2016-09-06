<?php
namespace lib\reflection\model;
class ModelComponent
{
    var $parentModel;
    var $name;
    var $definition;
    var $required;
    function __construct($name,$parentModel,$definition)
    {
        $this->name=$name;
        $this->parentModel=$parentModel;
        $this->definition=$definition;
        $this->required=$definition["REQUIRED"];
    }
    function getName()
    {
        return $this->name;
    }
    function getLabel()
    {
        return $this->definition["LABEL"]?$this->definition["LABEL"]:$this->name;
    }

    function getShortLabel()
    {
            return $this->definition["SHORTLABEL"]?$this->definition["SHORTLABEL"]:$this->name;
    }
    function isRequired()
    {
        $notRequiredFlags=\lib\model\types\BaseType::TYPE_SET_ON_SAVE |
            \lib\model\types\BaseType::TYPE_REQUIRES_SAVE;

        $curType=\lib\model\types\TypeFactory::getType($this->parentModel,$this->definition);

        if($curType->flags & $notRequiredFlags && $curType->isEditable())
            return false;

        return $this->required;
    }

    function getFormInput($form,$name,$definition,$inputsDefinition=null)
    {
        $label=(isset($definition["LABEL"])?$definition["LABEL"]:$name);
        $help=(isset($definition["HELP"])?$definition["HELP"]:"**Insert Field Help**");
        if(isset($inputsDefinition) && isset($inputsDefinition))
        {
            $input=$inputsDefinition["TYPE"];
        }
        if(!$input)
        {
            $input="/types/inputs/".$this->getDefaultInputName($definition);
        }     
        $errors=$this->getFormInputErrors($definition);
        
        return $this->getInputPattern($name,$label,$help,$definition["REQUIRED"],$input,$errors);
    }
    function getFormInputErrors($definition)
    {        
        $type=\lib\model\types\TypeFactory::getType($this->parentModel,$definition);
        $errors=array();
        if($type)
        {
            $type=$type->getRelationshipType(); // So autoincrements return ints.
            // Se obtienen las clases padre del tipo.
            $parents=array_values(class_parents($type));

            array_unshift($parents,get_class($type));
            $n=count($parents);
            for($k=0;$k<$n;$k++)
            {
                $exClass=$parents[$k]."Exception";
                if(class_exists($exClass))
                {
                    return $exClass::getPrintableErrors($exClass,$definition);
                }
            }
        }
        return $errors;
    }
    function getDefaultInputParams($form=null,$actDef=null)
    {
         return null;
    }
    function getDefaultInputName($definition)
    {
        $fullclass=get_class(\lib\model\types\TypeFactory::getType($this->parentModel,$definition));
        $parts=explode('\\',$fullclass);
        $className=$parts[count($parts)-1];
        return $className;
    }

    function getInputPattern($name,$label,$help,$required,$inputType,$errors)
    {
        if($help=="")$help="**Insert Field Help**";

        $inputStr="\n\n\t\t\t\t[*/FORMS/inputContainer({\"name\":\"".$name."\"})]\n\t\t\t\t\t[_LABEL]".$label."[#]\n";
        $inputStr.="\t\t\t\t\t[_HELP]".$help."[#]\n";
        if($required["REQUIRED"])
            $inputStr.="\t\t\t\t\t[_REQUIRED][#]\n";
        $inputStr.="\t\t\t\t\t[_INPUT]\n";

        $inputStr.="\t\t\t\t\t\t[*:".$inputType."({\"model\":\"\$currentModel\",\"name\":\"".$name."\",\"form\":\"\$form\"})][#]\n\t\t\t\t\t[#]\n";
        if(isset($errors))
        {        
            $inputStr.="\t\t\t\t\t[_ERRORS]\n";
            foreach($errors as $key2=>$value2)
            {
                $inputStr.="\t\t\t\t\t\t[_ERROR({\"type\":\"".$key2."\",\"code\":\"".$value2["code"]."\"})][@L]".$value2["txt"]."[#][#]\n";
            }   
            $inputStr.="\t\t\t\t\t[#]\n";
        }
        $inputStr.="\t\t\t\t[#]\n";
        return $inputStr;
    }
    


    function fillTypeErrors($type,$definition,& $errors)
    {                
        // Se obtienen las constantes de la clase base, BaseType, para filtrarlas.
        // Se deben filtrar ya que dichas constantes son para excepciones internas,
        // no relacionadas con los errores que pueden aparecer en un formulario.
        $type=new \lib\reflection\model\types\BaseType($definition);
        return $type->getTypeErrors();
    }

    function createType($definition)
    {
        if(!$this->definition["TYPE"])
            return null;
        $type=$this->definition["TYPE"]."Type";

        $typeReflectionFile=LIBPATH."/reflection/model/types/$type".".php";
        $dtype=$type;

        if(!is_file($typeReflectionFile))
        {
            $dtype="BaseType";
            $typeReflectionFile=LIBPATH."/reflection/model/types/BaseType.php";
        }
        $className='\lib\reflection\model\types\\'.$dtype;

        include_once($typeReflectionFile);

        // Los "alias" siempre tienen un parentModel.Los "types", no necesariamente.
        // Por eso, los constructores de alias tienen $parentModel como primer parametro del constructor.

         $instance=new $className($definition);     
         $instance->setTypeName($type);
         return $instance;

    }

     function getDefinition()
     {
         return $this->definition;
     }


     function dumpArray($arr,$initialNestLevel=0)
     {
         return \lib\php\ArrayTools::dumpArray($arr,$initialNestLevel);
     }


}
?>
