<?php
namespace lib\reflection\html\views;
class ViewWidget extends \lib\reflection\base\ConfiguredObject
{
    function __construct($name,$parentModel,$parentDs)
    {                        
        $this->parentDs=$parentDs;        
        $filePath="/html/views";
        parent::__construct($name,$parentModel,'', $filePath, "datasourceTemplate", null, false,".wid");
    }

    function initialize()
    {
        $this->parentDs->setWidget("html",$this);
    }


    function generateCode($isadmin=false,$asSubDs=false,$subDsIterator=null)
    {
        if($this->generating)
            return "";
        $this->generating=1;

        $isadmin=$this->parentDs->isAdmin();
        
        $descriptiveFields=$this->parentModel->getDescriptiveFields();
        
        if($descriptiveFields!=null && count($descriptiveFields)>0)
        {
            $dKeys=array_keys($descriptiveFields);
            $mainLabel=$descriptiveFields[$dKeys[0]]->getLabel();
        }
        else
            $mainLabel="";
            
        $phpCode=<<<'TEMPLATE'
            
            global $SERIALIZERS;
            $params=Registry::$registry["PAGE"];
            $serializer=\lib\storage\StorageFactory::getSerializerByName('{%layer%}');
            $serializer->useDataSpace($SERIALIZERS["{%layer%}"]["ADDRESS"]["database"]["NAME"]);
TEMPLATE;
        if(!$asSubDs)
        {
            $phpCode.=<<<'TEMPLATE'
            $params=\Registry::$registry["PAGE"];
TEMPLATE;
        }

        $widgetCode= <<<'WIDGET'
            [*/VIEWS/OBJECT_VIEW({"currentPage":"$currentPage","object":"{%object%}","dsName":"{%dsName%}","serializer":"$serializer","params":"$params","iterator":"&$iterator"})]
                    [_TITLE]{%title%}[#]
                    [_CONTENTS]
                        [*/LAYOUTS/2Columns]
{%contents%} 
                        [#]
                    [#]    
           [#]
WIDGET;
        
        // Se buscan todos los objetos que tenemos en metadata.
        $def=$this->parentDs->getDefinition();
        
        $metadata=$def["FIELDS"];
        if(!$metadata)
            $metadata=$def["PARAMS"];
        foreach($metadata as $fName=>$fDef)
        {            
            $type=\lib\model\types\TypeFactory::getType($this->parentModel,$fDef);
            $typeClass=get_class($type);
            $pos=strrpos($typeClass,"\\");
            $className=substr($typeClass,$pos+1);

            $contents.="\t\t\t\t[_ROW]\n\t\t\t\t\t[_LEFT]".($typeDef["LABEL"]?$typeDef["LABEL"]:$fName)."[#]\n"
                        ."\t\t\t\t\t[_RIGHT][*:/types/".$className."({\"name\":\"".$fName."\",\"model\":\"\$iterator\"})][#][#]\n\t\t\t\t[#]\n";
        }
        
        $searchs=array("{%layer%}","{%object%}","{%dsName%}","{%contents%}","{%title%}");
        $replaces=array($this->parentModel->getLayer(),
                        str_replace('\\','/',$this->parentModel->getClassName()),
                        $this->parentDs->getName(),
                        $contents,
                        $mainLabel
                        );
        
        $code=str_replace($searchs,$replaces,"<?php\n".$phpCode."\n?>\n".$widgetCode."\n");
        if($asSubDs==false)
        {
          foreach($this->parentDs->getIncludes() as $key=>$value)
          {
              $curDs=$value->getDatasource();
              if(!$curDs)
              {
                  $def=$value->getDefinition();
                  $oName=$def["OBJECT"];
                  $remoteObject=\lib\reflection\ReflectorFactory::getModel($oName);
                  $remoteObject->loadDatasources();
                  $curDs=$value->getDatasource();
                  $p=1;
              }

              if(!$curDs)
              {
                  echo "UNKNOWN INCLUDE<br>";
                  echo "KEY::$key<br>";
                  var_dump($value);
                  $p=1;
                  $h=11;
              }
              echo "KEY::".$key;
              $def=$curDs->getDefinition();

/*              if($def["ROLE"]=="MxNlist")
              {
                  foreach($curDs->includes as $key2=>$value2)
                  {
                      $code.="\tif(\$iterator->".$key."->count() > 0){\n";
                      $code.="\t\t\$".$key2."Iterator=\$iterator->".$key."[0]->".$key2."; ?".">\n";
                      $ds2=$value2->getDatasource()->getWidget("html");
                      $code.=$ds2->generateCode($dsType,true,$key2."Iterator");
                      $code.="<?php \t}\n?".">\n";
                  }
           }
           else
           {*/
               $code.=" \$".$key."Iterator=\$iterator->".$key.";\n";
               $code.=" ?".">";
               $code.=$curDs->getWidget("html")->generateCode($dsType,true,$key."Iterator");
           //}

       }
        }        
        
        $this->code=$code;
        $this->generating=0;
        return $code;
    }
    function save()
    {
    
        @mkdir(dirname($this->filePath),0777,true);
        file_put_contents($this->filePath,$this->code);
    }



}
