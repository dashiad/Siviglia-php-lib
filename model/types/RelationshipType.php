<?php namespace lib\model\types;

// El tipo Relacion solo existe para poder "redireccionar" columnas de tipo Relationship, a su tipo padre.
class RelationshipType extends BaseType {
    function getRelationshipType()
      {          
          $obj=$this->definition["OBJECT"];
          if ($obj == null)
              $obj=$this->definition["MODEL"];

          $subTypes=array();
          if($this->definition["MULTIPLICITY"]=="M:N")
          {
          
              $flist=$this->definition["FIELDS"]["REMOTE"];
              $remoteDef=\lib\model\types\TypeFactory::getObjectDefinition($obj);
            
              if(!$flist)
                $flist=$remoteDef["INDEXFIELDS"];
            
              foreach($flist as $key=>$value)
                $subTypes[$value]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($obj,$value);
                        
          } 
          else
          {
               if($this->definition["FIELD"])
                    $fields=(array)$this->definition["FIELD"];
               else
                    $fields=& $this->definition["FIELDS"];
          
              $flist=array_values($fields);
              $subTypes=array();
              for($k=0;$k<count($flist);$k++)
              {
                $subTypes[$flist[$k]]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($obj,$flist[$k]);
              }
          }
          if(count($subTypes)>1)
              return $subTypes;
          return $subTypes[$flist[0]];
      }
      function setValue($val)
      {
          // Las relaciones no permiten "" como valor de relacion.
          if($val==="")
          {
              return;
          }
          parent::setValue($val);
      }
}


class RelationshipTypeHTMLSerializer extends BaseTypeHTMLSerializer
   {
      function serialize($type)
      {         
          $remoteType=$type->getRelationshipType();
          return \lib\model\types\TypeFactory::serializeType($remoteType,"HTML");
      }      
      function unserialize($type,$value)
      {   
          $remoteType=$type->getRelationshipType();
          \lib\model\types\TypeFactory::unserializeType($remoteType,$value,"HTML");          
          $type->setValue($remoteType->getValue());
      }
   }