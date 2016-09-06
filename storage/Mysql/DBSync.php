<?php
  include_once(LIBPATH."/mysql.php3");
  include_once(LIBPATH."/CMVC.php");
  include_once(LIBPATH."/CModuleManager.php");
  class DBSync {

      function checkColumns($columns,$tableColumns,$typeDefinition,$tableName)
      {
          global $oDebug;
          $nColumns=count($columns);
          for($k=0;$k<$nColumns;$k++)
          {
              $curColumn=$columns[$k];
              $curTableColumn=$tableColumns[$curColumn];
              $curFieldDef=$typeDefinition[$curColumn];

              $oDebug->log("LOG","Comparando columna ".$curColumn);
              // Se compara el tipo...Primero, se obtiene el tipo de la columna
              $oType=CMVC::__getType($typeDefinition[$curColumn]);
              // Se sustituye la definicion del campo, por la que genera el tipo, que
              // no tiene por que ser la misma (especialmente, el campo "REQUIRED" para PKEYS

              $curFieldDef=$oType->def;
              $typeStr=$oType->getSQLType();

              $mustAlter=0;
              if(strtoupper($typeStr)!=strtoupper($curTableColumn["Type"]))
              {
                  $oDebug->log("LOG","Columna $curColumn distinta: en SQL es: <b>".
                               $curTableColumn["Type"]."</b> , en TYPE, es:<b>".
                               $typeStr."</b>");
                  $mustAlter=1;                               
              }
              // Se comprueban los valores por defecto.
              $defaultValueTable=$curTableColumn["Default"];
              if($curFieldDef["DEFAULT"])
              {
                  if($curFieldDef["DEFAULT"]!=$defaultValueTable)
                  { 
                      $oDebug->log("LOG","Columna $curColumn tiene distintos valores por defecto.En SQL es:<b>".
                                   $curTableColumn["Default"]."</b> , EN TYPE es:<b>".
                                   $curFieldDef["DEFAULT"]."</b>");
                      $mustAlter=1;
                  }
              }else{
                  if($defaultValueTable!="NULL" && $defaultValueTable!="")
                  {
                      $oDebug->log("LOG","Columna $curColumn tiene distintos valores por defecto.En SQL es:<b>".
                                   $curTableColumn["Default"]."</b> , EN TYPE es:<b>".
                                   $curFieldDef["DEFAULT"]."</b>");
                      $mustAlter=1;
                  }
              }
              // Se comprueba si el campo es requerido o no.
              $reqDef=$curFieldDef["REQUIRED"];
              $reqTab=$curTableColumn["Null"];
              if(($reqDef && $reqTab=="YES") || (!$reqDef && $reqTab=="NO"))
              {
                  $oDebug->log("LOG","Columna $curColumn tiene distinto valor en Required: en tabla, <b>".
                               $reqTab."</b> , en definicion: <b>".$reqDef."</b>");
                  $mustAlter=1;
              }

              if($mustAlter)
                  doQ("ALTER TABLE ".$tableName." MODIFY COLUMN ".$oType->getSQLString());
          }

      }
      function createColumns($arr,$typeDefinition,$tableName)
      {
          global $oDebug;
          for($k=0;$k<count($arr);$k++)
          {
              $oDebug->log("LOG","...Creando columna ".$arr[$k]);
              $oType=CMVC::__getType($typeDefinition[$arr[$k]]);
              doQ("ALTER TABLE ".$tableName." ADD COLUMN ".$oType->getSQLString());
          }
      }
      function dropColumns($arr,$tableName)
      {
          global $oDebug;
          for($k=0;$k<count($arr);$k++)
          {
              $oDebug->log("LOG","...Eliminando columna ".$arr[$k]);
              doQ("ALTER TABLE ".$tableName." DROP COLUMN ".$arr[$k]);
          }
      }

      function createTables($arr)
      {
          global $installValues;
          global $oDebug;
          for($k=0;$k<count($arr);$k++)
          {
              $oDebug->log("LOG","...Creando tabla ".$arr[$k]);
              $oModel=CMVC::__getModel($arr[$k]);
              $createTableString=$oModel->__createTableString();
              doQ($createTableString);
          }
      }
      function checkTables($arr)
      {
          $t=array_map("strtoupper",$arr);
          global $oDebug;

          for($k=0;$k<count($t);$k++)
          {
              $oDebug->log("LOG","Comparando objeto ".$t[$k]);
              $results=fillArrQuery("DESCRIBE ".$t[$k],"Field");
              $tableColumns=array();
              if(count($results)>0)
              {
                  $tableColumns=array_keys($results);
              }
              // Se obtiene un modelo 
              $oModel=CMVC::__getModel($t[$k]);
              $fieldColumns=array();
              if(count($oModel->def["FIELDS"])>0)
                  $fieldColumns=array_keys($oModel->def["FIELDS"]);
              $common=array_values(array_intersect($tableColumns,$fieldColumns));
              $oldColumns=array_values(array_diff($tableColumns,$common));
              $newColumns=array_values(array_diff($fieldColumns,$common));
              $this->checkColumns($common,$results,$oModel->def["FIELDS"],$t[$k]);
              $this->createColumns($newColumns,$oModel->def["FIELDS"],$t[$k]);
              $this->dropColumns($oldColumns,$t[$k]);
          }
          
          
      }
      function dropTables($arr)
      {
          global $oDebug;
          for($k=0;$k<count($arr);$k++)
          {
              $oDebug->log("LOG","Eliminando tabla ".$arr[$k]);
              doQ("DROP TABLE ".$arr[$k]);
          }
      }
      function createDatabase()
      {
          global $oDebug;
          $result=@mysql_select_db(DATABASE);
          if(mysql_error()!="")
          {
              doQ("create database ".DATABASE." CHARACTER SET UTF8");
              if(mysql_error()!="")
                  $oDebug->log("ERR","Error al crear base de datos:".mysql_error());
              $result=@mysql_select_db(DATABASE);
              if(!$result)
                  $oDebug->log("ERR","Imposible crear la base de datos ".DATABASE);
          }
      }
      function synchronizeDb()
      {
          global $oDebug;
          $this->createDatabase;
          // Primera fase:tablas.Tenemmos que coger las tablas que existen,
          // y los objetos definidos por la aplicacion.
          // Las tablas de sistema se prefijan con "_", de forma que aqui sean ignoradas.
          $q="SHOW TABLES";
          $results=fillArrQuery($q);
          $currentTables=array();
          if(count($results)>0)
          {
              $keys=array_keys($results[0]);
              $k0=$keys[0];
           
              for($k=0;$k<count($results);$k++)
                  $currentTables[]=$results[$k][$k0];
          }
          // Se leen los objetos definidos en ficheros
          $odir=opendir(PROJECTPATH."/objects");
          while($dir=readdir($odir))
          {
              if($dir=="." || $dir==".." || $dir=="_system" || $dir=="Website")
                  continue;              
              $definedObjects[]=strtoupper($dir);
          }
          $currentTables=array_map("strtoupper",$currentTables);
          // Se calculan las diferencias en tablas
          $common=array_values(array_intersect($currentTables,$definedObjects));
          $oldObjects=array_values(array_diff($currentTables,$common));
          $newObjects=array_values(array_diff($definedObjects,$common));
          $this->checkTables($common);
          $this->createTables($newObjects);
          // Para las tablas nuevas a crear, se insertan los datos.
          // Hay que tener en cuenta que, por ahora, en los updates, no se van a revisar los campos.
          // Habría que calcular qué columnas han cambiado (ya se hace), y luego, hacer updates de las
          // filas, basandose en las columnas antiguas, para updatear las columnas nuevas.
          $this->insertData($newObjects);
         // $this->dropTables($oldObjects);
          
      }
      function reset()
      {
          $q="drop database ".DATABASE;
          doQ($q);
          $this->createDatabase();
      }
      
      function parseInstallValues(& $arr,$columns,& $definition)
      {
          $nVals=count($arr);
          for($k=0;$k<$nVals;$k++)
          {
              if(preg_match("/([^@]*)@\[([^=]*)=\'([^']*)\'\]/",$arr[$k],$matches))
              {
                  $mod=CMVC::__getModel($matches[1]);
                  $mod->addCondition($matches[2]."='".$matches[3]."'");
                  
                  $arr[$k]=$mod[0]->{"@".$definition[$columns[$k]]["KEY"]};
              }
          }
          return $arr;
      }

      function insertData($tables)
      {
          $oModuleManager=new CModuleManager();
          $depStack=$oModuleManager->getDependencyStack($tables);
          $nEl=count($depStack);
          $m=0;
          for($k=0;$k<$nEl;$k++)
          {
              $nSubEl=count($depStack[$k]);
              for($j=0;$j<$nSubEl;$j++)
              {
                  
                  $oName=$depStack[$k][$j];
                  $curModel=CMVC::__getModel($oName);
                  $curClass=$oModuleManager->getInstallClass($oName);
                  $fields=$curClass->data["FIELDS"];

                  $nVals=count($curClass->data["VALUES"]);
                  for($i=0;$i<$nVals;$i++)
                  {
                      $curVal=& $curClass->data["VALUES"][$i];
                      $subFields=$this->parseInstallValues($curVal,$fields,$curModel->def["FIELDS"]);
                      $q="INSERT INTO ".$oName."(".implode(",",$fields).") VALUES ('".implode("','",$subFields)."')";
                      doQ($q);
                  }
              }
          }
      }
  }
