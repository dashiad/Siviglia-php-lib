<?php
namespace lib\reflection\model;
class QuickModelGenerator
{
  static function createFromQuick($name,$layer,$quickDef)
  {
        $objectName=$name;        
        $table=$name;
        
        $instance=new lib\reflection\model\ModelDefinition($name,$layer);
            
        // Se crea automaticamente un campo id
        $fieldIndex="id_".strtolower($objectName);
        $this->indexFields=array($fieldIndex);
        $this->fields[$fieldIndex]=\lib\reflection\model\FieldDefinition::createField($fieldIndex,$instance,array("TYPE"=>"UUID"));           

        for($k=0;$k<count($quickDef);$k++)
        {
            $isDigit=0;
            $fName=$quickDef[$k];
            if(strpos($fName,"@"))
            {
                $parts=explode("@",$fName);
                $relFields=explode(",",trim($parts[1]));
                $targetObj=substr($parts[0],1);
                $instance->addField($parts[0],\lib\reflection\RelationshipDefinition::createRelation($targetObj,$instance,$targetObj,$relFields));
                continue;
            }
           if($quickDef[$k][0]=='#')
           {
                $rfname=substr($fName,1);
                $instance->addField($rfname,\lib\reflection\FieldDefinition::createField($rfname,$this,array("TYPE"=>"Integer"));
           }
           else
                $instance->addField($fName,\lib\reflection\FieldDefinition::createField($fName,$this,array("TYPE"=>"String","MAXLENGTH"=>45)));
         }
                        
         $instance->acls=ModelPermissionsDefinition::getDefaultAcls($objectName,$layer);            
         // Setup de permisos, con un array vacio.
         $instance->modelPermissions=new \lib\reflection\ModelPermissionsDefinition($this,array());
         $instance->initialize($instance->getDefinition());
         return $instance;           
  }
}

?>
