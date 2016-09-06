<?php
namespace lib\model;
   class TreeAlias
   {
       var $childDs;
       var $treeFieldName;
       var $descendantDs;
       var $rootDs;
       var $ancestorDs;
       var $name;
       var $model;
       var $definition;       
       var $separator;
       function __construct($name,& $model, $definition,$value=false)
       {
          $this->definition=$definition;
          $this->name=$name;
          $this->model=$model;
          $this->treeFieldName=$this->definition["FIELD"];
          $this->definition=$definition;
          $treeFieldDef=$model->__getFieldDefinition($this->treeFieldName);
          if(!isset($treeFieldDef["SEPARATOR"]))
              $this->separator='#';
          else
              $this->separator=$treeFieldDef["SEPARATOR"];
          $this->treeIdField=$treeFieldDef["FIELD"];

       }
       function __get($varname)
       {
        
           switch($varname)
           {
           case "children":
               {
                   $ds=$this->children();
               }break;
           case "ancestors":
               {
                   $ds=$this->ancestors();
               }break;
           case "descendants":
               {
                   $ds=$this->descendants();
               }break;
           case "roots":
               {
                   $ds=$this->roots();
               }break;
           }
           return $ds->getIterator();
       }
       function get()
       {
           return $this;
       }
       function children($fields=null)
       {
          if($this->childDs==null)
          {
                $this->childDs=$this->getDs("getRecursiveChildDsDefinition",$fields);
          }
          return $this->childDs;
       }
       function descendants($fields=null)
       {
          if($this->descendantDs==null)
                $this->descendantDs=$this->getDs("getRecursiveDsDescendantDsDefinition",$fields);
          return $this->descendantDs;
       }
       function ancestors($fields=null)
       {
           if($this->ancestorDs==null)
               $this->ancestorDs=$this->getDs("getRecursiveParentsDsDefinition",$fields);
           return $this->ancestorDs;
       }
       function roots($fields=null)
       {
           if($this->rootDs==null)
               $this->rootDs=$this->getDs("getRecursiveRootsDsDefinition",$fields);
           return $this->rootDs;
       }
       
       function getDs($callback,$fields=null)
       {
           $dsDef=$this->generateDsDefinition($fields);
           $serializerDs=$this->getSerializerDsClass();

           
           $serDsDef=$serializerDs::$callback(
                                        $this->model->__getTableName(),
                                        $this->treeIdField,
                                        $this->treeFieldName,
                                        $this->model->{$this->treeFieldName},
                                        $this->model->{$this->treeIdField},                                        
                                        array_keys($dsDef["FIELDS"]),
                                        $this->separator);
           
           $this->childDs=new $serializerDs($this->model->__getObjectName(),$callback,$dsDef,$this->model->__getSerializer(),$serDsDef);
           return $this->childDs;             
       }

       function getSerializerDsClass()
       {
            $serializer=$this->model->__getSerializer();
            $type=$serializer->getSerializerType();
            return '\lib\storage\\'.$type.'\\'.$type.'DataSource';
       }
       function generateDsDefinition($fields=null)
       {
           if($fields==null)
           {
              $fields=array($this->treeFieldName,$this->treeIdField);
           }
           else
           {
               if($fields[0]=="*")
               {
                   $def=$this->model->getDefinition();
                   $fieldDef=$def["FIELDS"];                       
               }
           }

            
           if(!$fieldDef)
           {
               $parentObjName=$this->model->__getObjectName();
               foreach($fields as $val)            
                 $fieldDef[$val]=array("MODEL"=>$parentObjName,"FIELD"=>$val);
           }
            
            return array(
            "ROLE"=>"list",
            "FIELDS"=>$fieldDef);
       }
   }

?>
