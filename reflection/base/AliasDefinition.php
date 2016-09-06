<?php
namespace lib\reflection\base;
abstract class AliasDefinition extends \lib\reflection\model\ModelComponent
{
    var $datasources;
        function __construct($name,$parentModel,$definition)
        {
               $this->definition=$definition;
               $this->parentModel=$parentModel;
               $this->name=$name;
               $this->type=$definition["TYPE"];
        }
        function getName()
        {
            return $this->name;
        }        
        function isRelation(){ return true;}
        function isAlias(){ return true;}
        abstract function getDatasourceCreationCallback();
        function getDataSources($targetModel=null,$baseName=null)
        {

            if($targetModel==null)
                $targetModel=$this->parentModel;
            if($baseName==null)
                $baseName=$this->name;
           $callbackName=$this->getDatasourceCreationCallback();        

            if($this->datasources!=null)
                return $this->datasources;
            $perms=array("_PUBLIC_");
            $isadmin=false;            

            $innerDs=$targetModel->getDataSource($baseName);
              if(!$innerDs)
                  $innerDs=new \lib\reflection\datasources\DatasourceDefinition($baseName,$targetModel);

              if($innerDs->mustRebuild())
              {
                  $innerDs->$callbackName($this,"MxNlist","INNER",$isadmin,$perms);
              }
              $this->datasources[]=$innerDs;


              $fullName="Full".ucfirst($baseName);
              $fullDs=$targetModel->getDataSource($fullName);
              if(!$fullDs)
                  $fullDs=new \lib\reflection\datasources\DatasourceDefinition($fullName,$targetModel);

              if($fullDs->mustRebuild())
                  $fullDs->$callbackName($this,"MxNlist","LEFT",$isadmin,$perms);

              $this->datasources[]=$fullDs;

              $notName="Not".ucfirst($baseName);
              $notDs=$targetModel->getDataSource($notName);
              if(!$notDs)              
                  $notDs=new \lib\reflection\datasources\DatasourceDefinition($notName,$targetModel);

              if($notDs->mustRebuild())
                  $notDs->$callbackName($this,"MxNlist","OUTER",$isadmin,$perms);
              
             $this->datasources[]=$notDs;
             return $this->datasources;
        }

}
