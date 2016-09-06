<?php
   namespace lib\reflection;
   // Eventos:
   // START_SYSTEM_REBUILD ($sys) // Initializes the system rebuilding
   // REBUILD_MODELS ($sys) // Models are rebuilt
   // REBUILD_STORAGES ($sys) // Storage systems are rebuilt
   // REBUILD_DATASOURCES ($sys)
   // REBUILD_ACTIONS ($sys)
   // REBUILD_VIEWS($sys)
   // END_SYSTEM_REBUILD($sys)

   // Ejemplos de otros eventos:
   // ADD_OBJECT(layer,name)
   // ADD_FIELD(layer,name,

   class ReflectorFactory
   {
        static $objectDefinitions;
        static $factoryLoaded=false;
        static $layers=array();
        static $relationMap=null;
        static function getModel($modelName,$layer=null)
        {
            //echo "MODEL:$modelName";
            $nameObj=new \lib\reflection\model\ObjectDefinition($modelName,$layer);
            $layer=$nameObj->layer;
            $className=$nameObj->className;
            if($nameObj->isPrivate())
                $className=$nameObj->getNamespaceModel().'\\'.$className;

            if(isset(ReflectorFactory::$objectDefinitions[$layer][$className]))
                return ReflectorFactory::$objectDefinitions[$layer][$className];
            if(ReflectorFactory::$factoryLoaded==true)
                return null;
            

            ReflectorFactory::loadFactory();
            return ReflectorFactory::$objectDefinitions[$layer][$className];            
        }
        static function getObjectsByLayer($layer)
        {
            if(!ReflectorFactory::$factoryLoaded)
            {
                ReflectorFactory::loadFactory();
            }
            return ReflectorFactory::$objectDefinitions[$layer];
        }
        
        static function loadFactory()
        {
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $curLayer)
            {
                $dir=PROJECTPATH."/$curLayer/objects";
                $curDir=opendir($dir);

                while($filename=readdir($curDir))
                {

                     if($filename!="." && $filename!=".." && is_dir($dir."/".$filename))
                     {
                         // Si no existe fichero de Definition, continuamos.
                         if(!is_file($dir."/".$filename."/Definition.php"))
                             continue;

                         $curModel=new \lib\reflection\model\ModelDefinition($filename,$curLayer);
                         if($curModel->getExtendedModel())
                             $baseClass="ExtendedModel";
                         else
                         {
                             if($curModel->getSubTypeField())
                                 $baseClass="BaseTypedModel";
                             else
                                 $baseClass="BaseModel";
                         }
                         $existingModels[$filename]=$curLayer;
                         ReflectorFactory::$objectDefinitions[$curLayer][$filename]=$curModel;

                         $subDir=$dir."/".$filename."/objects";
                         if(is_dir($subDir))
                         {          
                             $curSubDir=opendir($subDir);          
                             while($subFilename=readdir($curSubDir))
                             {
                                 if($subFilename=="." || $subFilename==".." || !is_dir($subDir."/".$subFilename))
                                     continue;
                                 // Para clases que no tienen definicion, saltamos
                                 if(!is_file($subDir."/".$subFilename."/Definition.php"))
                                     continue;
                                 $subClassName=$filename.'\\'.$subFilename;
                                 $curModel=new \lib\reflection\model\ModelDefinition($subClassName,$curLayer);
                                 if($curModel->getExtendedModel())
                                     $baseClass="ExtendedModel";
                                 else
                                 {
                                     if($curModel->getSubTypeField())
                                         $baseClass="BaseTypedModel";
                                     else
                                         $baseClass="BaseModel";
                                 }
                                 $existingModels[$filename.'\\'.$subFilename]=$curLayer;
                                 ReflectorFactory::$objectDefinitions[$curLayer][$filename.'\\'.$subFilename]=$curModel;
                             }
                         }
                     }
                }
            }
            // Hay que inicializar primero aquellos objetos que no extienden de nada, y, a partir de ahi,
            // los objetos que heredan de cualquier otro.
            $parsedModels=array();
            while(count($existingModels)>0)
            {
                $newModels=array();
                foreach($existingModels as $name=>$layer)
                {
                    $cur=ReflectorFactory::$objectDefinitions[$layer][$name];

                    if($cur->definition["EXTENDS"])
                    {
                        $objName=new \lib\reflection\model\ObjectDefinition($cur->definition["EXTENDS"]);
                        $normalized=$objName->getNormalizedName();
                        if(!$parsedModels[$normalized])
                        {
                         
                            $newModels[$name]=$layer;
                            continue;
                        }                        
                    }                    
                    $parsedModels[$name]=1;
                    $cur->initialize();
                }

                $existingModels=$newModels;                
            }
            // Lo siguiente no es tecnicamente cierto; la factoria no esta cargada.
            // Pero, si no la establecemos a cargada aqui, si algun alias intenta acceder a los modelos,
            // como aun no estarian cargados (factoryLoaded==false), comenzaria un bucle.Volverian a intentar
            // ser cargados (se volveria a llamar a esta funcion)
            ReflectorFactory::$factoryLoaded=true;
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $curLayer)
            {
                foreach(ReflectorFactory::$objectDefinitions[$curLayer] as $curObj => $curModel)
                {
                    $curModel->initializeAliases();
                }
            }
            
        }
        static function getRelationMap()
        {
            if(ReflectorFactory::$relationMap)
                return ReflectorFactory::$relationMap;
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $curLayer)
            {
                foreach(ReflectorFactory::$objectDefinitions[$curLayer] as $curObj => $curModel)
                {
                    $objects[]=$curObj;
                    $cK=array_keys($curModel->getIndexFields());
                    $keys[$curObj]=$cK[0];
                    $simpleRel=$curModel->getSimpleRelations();
                    foreach($simpleRel as $relName=>$relObj)
                        $relations[$curObj][$relObj->getRemoteModelName()]=$relName;
                }
            }                
            $temp=array("objects"=>array_keys($relations),"relations"=>$relations,"keys"=>$keys);
            $temp["distances"]=ReflectorFactory::buildDistances($temp["objects"],$temp["relations"],$temp["keys"]);
            ReflectorFactory::$relationMap=$temp;
            return $temp;
            
        }
        static function buildDistances($objects,$relations,$oKeys)
        {
            $curDistance=0;
            while(1)
            {

                $cont=0;

                for($k=0;$k<count($objects);$k++)
                {
                    $curObject=$objects[$k];

                    if($curDistance==0)
                    {
                        foreach($relations[$curObject] as $key=>$value)
                        {
                            $distances[$curObject][$key]=1;
                            $paths[$curObject][$key]="/".$key."[$value";
                            $queries[$curObject][$value]=$curObject." INNER JOIN $key ON ".$curObject.".$value=$key.".$oKeys[$key];

                            if(!$relations[$key])
                            {
                                $relations[$key]=array();
                                $objects[]=$key;
                            }
                            $distances[$key][$curObject]=1;
                            $paths[$key][$curObject]="/".$curObject."|$value";
                            $queries[$key][$curObject]=$key." INNER JOIN $curObject ON ".$curObject.".$value=$key.".$oKeys[$key];

                        }
                        $cont=1;
                        continue;
                    }
                    $adist=& $distances[$curObject];
                    if($curDistance==1 && $curObject=="TipoGrupo")
                    {
                        $yy=3;
                    }

                    foreach($adist as $bName=>$bdist)
                    {                              
                        if($bdist==$curDistance)
                        {
                            foreach($distances[$bName] as $cName=>$cDist)
                            {                        
                                if($cName == $curObject)
                                    continue;
                                $fullDist=$cDist+$curDistance;
                                if(!$adist[$cName] || ($adist[$cName] > $fullDist))
                                {
                                    $cont++;
                                    $adist[$cName]=$fullDist;
                                    $paths[$curObject][$cName]=$paths[$curObject][$bName].$paths[$bName][$cName];
                                    $queries[$curObject][$cName]=$queries[$curObject][$bName]." ".substr($queries[$bName][$cName],strpos($queries[$bName][$cName]," ")+1);
                                }
                            }
                        }
                    }

                }
                $curDistance++;
                 if($cont == 0)
                    break;
            }
            foreach($queries as $o1=>$val)
            {
                foreach($val as $o2=>$text)
                    $queries[$o1][$o2]="SELECT ".$o1.".*,".$o2.".* FROM ".$text;
            }
            return array($distances,$paths,$queries);
        }

        static function getLayer($layer)
        {
            if(!ReflectorFactory::$layers[$layer])
                ReflectorFactory::$layers[$layer]=new \lib\reflection\base\Layer($layer);
            return ReflectorFactory::$layers[$layer];
        }
        static function addModel($layer,$name,$instance)
        {
             if(!ReflectorFactory::$factoryLoaded)
            {
                ReflectorFactory::loadFactory();
            }
             ReflectorFactory::$objectDefinitions[$layer][$name]=$instance;
        }
        static function getSerializer($layer)
        {
            return Registry::$registry["serializers"][$layer];
        }
   }

   class SystemReflector
   {
        var $plugins;
        function __construct()
        {
                $dir=opendir(LIBPATH."/reflection/plugins");
                while($filename=readdir($dir))
                {
                        if($filename!="." && $filename!="..")
                        {
                                // Se elimina la extension.
                                $className='\lib\reflection\plugins\\'.basename($filename,".php");
                                $instance=new $className();
                                $this->pluginList[]= new $className();                
                        }
                }
        }

        function __call($funcName,$args)
        {
              foreach($this->pluginList as $key=>$value)
                call_user_method_array($funcName,$value,$args);
        }
       
   }        

?>
