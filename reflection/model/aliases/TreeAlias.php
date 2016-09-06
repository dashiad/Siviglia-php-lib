<?php
  namespace lib\reflection\model\aliases;
  class TreeAlias extends \lib\reflection\base\AliasDefinition
  {
      function __construct($name,$parentModel,$definition) 
      {
          parent::__construct($name,$parentModel,$definition); 
      }
      function isRelation() {return false;}
      
      function isAlias(){ return true;}
      
      
      function generateActions()
      {
          // En principio, sin acciones.
          return array();
      }

      function getDatasourceCreationCallback()
      {
          return null;
      }
      
       function getDataSources()
        {
            return array();           
        }
      
  }
  
