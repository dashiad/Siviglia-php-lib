<?php
namespace lib\reflection\classes;
class PermissionRequirementsDefinition 
{
    var $stateDef;
    var $definition;


    function __construct($definition)
    {                
        if(is_array($definition))
        {

            if(\lib\php\ArrayTools::isAssociative($definition))
            {
                if($definition["STATES"])
                {
                    $this->stateDef=new \lib\reflection\classes\StateRequirementDefinition($definition["STATES"],'\lib\reflection\classes\PermissionRequirementsDefinition');
                }
            }
            else
            {                 
                if(count($definition)==0) // "PERMISSIONS"=>array()
                    $this->definition=array("_PUBLIC_");
                else
                {                   
                    $this->definition=$definition;
                }
            }
        }
        else
        {

            if(!$definition)
                $this->definition=array("_PUBLIC_");
            else
            {
                $this->definition=array($definition);
            }
        }
    }

    static function create($def)
    {                
        $obj= new PermissionRequirementsDefinition($def);        
        return $obj;
    }
    function isStated()
    {
        return $this->stateDef!=NULL;
    }
    function getRequiredPermissionsForState($state)
    {
        if( !$this->isStated )
            return $this->getRequiredPermissions();        
        return $this->stateDef->getObjectForState($state);
    }
    
    function isJustPublic()
    {
        return ($this->definition=="_PUBLIC_" || $this->definition==array("_PUBLIC_") );
    }
    function getDefinition()
    {        
        if($this->stateDef)
            return $this->stateDef->getDefinition();
        if( !is_array($this->definition) )
            return array($this->definition);
        return $this->definition;
    }
    function getRequiredPermissions($model)
    {
        if($this->stateDef)
        {
            $state=$model->getState();
            return $this->stateDef[$state];
        }
        return $this->definition;
    }
}
