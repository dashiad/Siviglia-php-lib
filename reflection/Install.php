<?php
  include_once("../../config.php");
   function __autoload($name)
 {
     
     //set_error_handler("_load_exception_thrower");
     if(is_file(PROJECTPATH."/".str_replace('\\','/',$name).".php"))
     {

         include_once(PROJECTPATH."/".str_replace('\\','/',$name).".php");
         return;
     }
     //restore_error_handler();
 }
    include_once(LIBPATH."/model/types/BaseType.php");
    include_once(LIBPATH."/php/debug/Debug.php");
    include_once(LIBPATH."/Registry.php");
  
  set_time_limit(0);
  error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

  function printPhase($text)
  {
      echo '<div style="background-color:black;color:white;font-size:26px;margin-top:40px;margin-bottom:3px">';
      echo $text;
      echo '</div>';          
  }
  function printSubPhase($text)
  {
      echo '<div style="background-color:#AAAAAA;color:black;font-size:18px">';
      echo $text;
      echo '</div>';          
  }
  function printItem($text)
  {
      echo '<div style="background-color:#EEEEEE;color:black;font-size:10px">';
      echo $text;
      echo '</div>';          
  }
  function printWarning($text)
  {
      echo '<div style="background-color:red;color:white;font-size:14px;padding:10px;padding-left:30px;font-weight:bold">';
      echo "Warning:&nbsp;&nbsp;&nbsp;".$text;
      echo '</div>';          
  }

  include_once(LIBPATH."/reflection/base/ClassFileGenerator.php");

  function importDefinitions($base,$layer,$baseNamespace)
  {
      if(!is_dir($base))
          return;
      
      $d=opendir($base);
      
      if(!$d)
          return false;
      
      while($f=readdir($d))
      {
          if($f=="." || $f==".." || !is_dir($base."/".$f))
              continue;
          if(is_file($base."/".$f."/Definition.json"))
          {
              $fcontents=file_get_contents($base."/".$f."/Definition.json");              
              $gen=new \lib\reflection\base\ClassFileGenerator("Definition",$layer,$baseNamespace."\\".$f,
                                                               $base."/".$f."/Definition.php");
              $def=json_decode($fcontents,true);
              if($def["Queries"])
                  unset($def["Queries"]);
              
              $gen->addProperty(array("NAME"=>"definition",
                                     "ACCESS"=>"static",
                                     "DEFAULT"=>$def));
              $gen->generate();
          }
          if(is_dir($base."/".$f."/objects"))
          {
              importDefinitions($base."/".$f."/objects",$layer,$baseNamespace."\\".$f);
          }
      }
      closedir($d);
  }

  function importJsonDefinitions()
  {
      global $APP_NAMESPACES;
      foreach($APP_NAMESPACES as $value)
          importDefinitions(PROJECTPATH."/$value/objects",$value,"\\$value");

  }

  importJsonDefinitions();
  $sysReflector=new \lib\reflection\SystemReflector();


  $phases=array("START_SYSTEM_REBUILD",
                "REBUILD_MODELS");/*,
                "REBUILD_STORAGES",
                "REBUILD_DATASOURCES",
                "REBUILD_ACTIONS",
                "REBUILD_VIEWS",
                "BUILD_URLS",
                "BUILD_OUTPUT",
                "SAVE_SYSTEM",
                "END_SYSTEM_REBUILD");*/
  try
  {
  
  for($k=0;$k<count($phases);$k++)
  {
      $curPhase=$phases[$k];
      for($h=0;$h<3;$h++)
      {          
          call_user_method($phases[$k],$sysReflector,$h);          
      }
  }
  }catch(Exception $e)
  {
      dumpDebug();
      throw $e;
  }
  

?>
