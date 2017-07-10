<?php
  namespace lib\storage\Mysql;

  abstract class BaseType {
      abstract function getBindType($type);
      static $mysqlLink;

      // Es necesario establecer el link, para poder usar mysqli_escape_string
      function setMysqlLink($link)
      {
          BaseType::$mysqlLink=$link;
      }
      function getBindValue($type)
      {
          return $type->hasValue()?$type->getValue():null;
      }
      function unserialize($type,$value)
      {
          $type->__rawSet($value);
      }
      static function serialize($type,$opts=null)
      {
          return $type->getValue();
      }
      abstract function getSQLDefinition($name,$definition);
      static function getSerializerFor($typeDefinition)
      {
          if(is_object($typeDefinition))
              $def=$typeDefinition->getDefinition();
          else
              $def=$typeDefinition;
          $typeName=$def["TYPE"];
          $serializer='\lib\storageEngine\Mysql\\'.$typeName."Type";
          return new $serializer();
      }
  }

  class StringType extends BaseType
  {
      function getBindType($type) { return 's'; }
      static function serialize($type,$opts=null){
          if(!$type->hasValue())
          {
              return null;
          }
          $rawVal=mysqli_real_escape_string(BaseType::$mysqlLink->getConnection(),$type->getValue());
          if(isset($opts["raw"]))
              return $rawVal;
          return "'$rawVal'";
      }
      function getSQLDefinition($name,$definition)
      {
          $defaultExpr='';
          if(isset($definition["DEFAULT"])) {
              $default = $definition["DEFAULT"];
              $defaultExpr = " DEFAULT '" . trim($default, "'") . "'";
          }
          $charSet=$definition["CHARACTER SET"];
          if(!$charSet)$charSet="utf8";
          $collation=$definition["COLLATE"];
          if(!$collation)$collation="utf8_general_ci";

          $max=$definition["MAXLENGTH"]?$definition["MAXLENGTH"]:45;
          return array("NAME"=>$name,"TYPE"=>"VARCHAR(".$max.") CHARACTER SET ".$charSet." COLLATE ".$collation." ".$defaultExpr);
      }
  }
  class IntegerType extends BaseType
  {
      static function serialize($type,$opts=null){
          if(!$type->hasValue())
          {
              return null;
          }
          return intval($type->getValue());
      }
      function getBindType($type){return "i";}

      function getSQLDefinition($name,$definition)
      {
          $maxVal=$definition["MAX"];
          $minVal=$definition["MIN"];
          if(!$maxVal)
          {
              $maxVal=$definition["UNSIGNED"]?4294967295:2147483647;
          }
          if(!$minVal)
              $minVal=0;

          $un=$definition["UNSIGNED"];
          if(!isset($un) && ($minVal>0))
              $un=true;

          $type="";
          if(($un && $maxVal<=255) || (!$un && $maxVal<=127 && $minVal>-128))
              $type="TINYINT";

          if(($un && $maxVal<=255) || (!$un && $maxVal<=127 && $minVal>-128))
              $type="TINYINT";
          if(($un && $maxVal<=65535) || (!$un && $maxVal<=32767 && $minVal>-32768))
              $type="SMALLINT";
          if(($un && $maxVal<=16777215) || (!$un && $maxVal<=8388607 && $minVal>-8388608))
              $type="MEDIUMINT";
          if(($un && $maxVal<=4294967295) || (!$un && $maxVal<=2147483647 && $minVal>-2147483648))
              $type="INT";
          if(!$type)
              $type="BIGINT";

          $default="";
          if(isset($definition["DEFAULT"]))
              $default=" DEFAULT ".$definition["DEFAULT"];
          return array("NAME"=>$name,"TYPE"=>$type.$default);
      }
  }
  class TextType extends StringType
  {
      function getSQLDefinition($name,$definition)
      {
          $charSet=$definition["CHARACTER SET"];
          if(!$charSet)$charSet="utf8";
          $collation=$definition["COLLATE"];
          if(!$collation)$collation="utf8_general_ci";
          return array("NAME"=>$name,"TYPE"=>"TEXT CHARACTER SET ".$charSet." COLLATE ".$collation);
      }
  }



  class ArrayTypeType extends BaseType
  {
      var $typeSer;
      var $typeInstance;
      static function serialize($type,$opts=null){
          if(!$type->hasValue())
          {
              return null;
          }
          $values=array_values($type->getValue);
          $escaped=array();
          foreach($values as $k=>$v)
          {
              $escaped[]=mysqli_real_escape_string(BaseType::$mysqlLink,$v);
          }
          $rawVal=implode(",",$escaped);
          if($opts=="raw")
              return $rawVal;
          return "'$rawVal'";
      }
      function getBindType($type){return 's';}
      function getBindValue($type)
      {
         if(!is_array($type->getValue())){
             return $type->getValue();
         }
          return implode(",",array_values($type->getValue()));
      }
      function unserialize($type,$value)
      {
          $type->setValue(explode(",",$value));          
      }
      function getSQLDefinition($name,$definition)
      {
          return array("NAME"=>$name,"TYPE"=>"TEXT");
      }
  }

  class AutoIncrementType extends IntegerType{

      function getSQLDefinition($name,$definition)
      {
          $iSer=new Integer();
          $subDef=$iSer->getSQLDefinition($name,$definition);
          return array("NAME"=>$name,"TYPE"=>$subDef["TYPE"]." AUTO_INCREMENT");
      }
  }


  class BankAccountType extends StringType {};


  class BooleanType extends IntegerType
  {
      static function serialize($type,$opts=null)
      {
          if(!$type->hasValue())
              return null;
          return $type->hasValue()==1?1:0;
      }

      function getBindValue($type)
      {
          if($type->hasValue())
          {
              $val=$type->getValue();
              if($val===true || $val==="true" || $val==1)
                  return 1;
              return 0;
          }
          return NULL;
      }
      function unserialize($type,$value)
      {
          if($value)
              $type->setValue(true);
          else
              $type->setValue(false);
      }
      function getSQLDefinition($name,$definition)
      {
          $cad="BOOLEAN";
          if(isset($definition["DEFAULT"])) {
              if ($definition["DEFAULT"] == true || $definition["DEFAULT"] == "TRUE")
                  $cad .= " DEFAULT TRUE";
              else
                  $cad .=" DEFAULT FALSE";
          }
          return array("NAME"=>$name,"TYPE"=>$cad);
      }
  }

  class CityType extends StringType {};

  class ColorType extends StringType {};

  class CompositeSerializer extends BaseType
  {
      static function serialize($type,$opts=null)
      {
          $subTypes=$type->getSubTypes();
          $results=array();
          foreach($subTypes as $key=>$value)
          {
              $serializer=BaseType::getSerializerFor($value);
              $bType=$serializer::serialize($value,$opts);
              if(is_array($bType))
              {
                  foreach($bType as $key2=>$val2)
                      $results[$key."_".$key2]=$val2;
              }
              else
                  $results[$key]=$bType;
          }
          return $results;
      }
      function getBindType($type)
      {
          $subTypes=$type->getSubTypes();
          $results=array();
          foreach($subTypes as $key=>$value)
          {
              $serializer=BaseType::getSerializerFor($value);
              $bType=$serializer->getBindType($type->{$key});
              if(is_array($bType))
              {
                  foreach($bType as $key2=>$val2)
                      $results[$key."_".$key2]=$val2;
              }
              else
                  $results[$key]=$bType;
          }
          return $results;
      }
      function getBindValue($type)
      {
          $subTypes=$type->getSubTypes();
          $results=array();
          foreach($subTypes as $key=>$value)
          {
              $serializer=BaseType::getSerializerFor($value);
              $bType=$serializer->getBindValue($type->{$key});
              if(is_array($bType))
              {
                  foreach($bType as $key2=>$val2)
                      $results[$key."_".$key2]=$val2;
              }
              else
                  $results[$key]=$bType;
          }
          return $results;
      }
      function getSQLDefinition($name,$def)
      {
          $type=\lib\model\types\TypeFactory::getType(null,$def);

          $definition=$type->getDefinition();
          $results=array();

          foreach($definition["FIELDS"] as $key=>$value)
          {
              $type=$value["TYPE"];
              $subSerializer=\lib\model\types\TypeFactory::getSerializer($type,"MYSQL");
              $subDefinitions=$subSerializer->getSQLDefinition($key,$value);
              if(!\lib\php\ArrayTools::isAssociative($subDefinitions))
                  $results=array_merge($results,$subDefinitions);
              else
                  $results[]=$subDefinitions;
          }
          $finalResults=array();
          foreach($results as $key=>$value)
          {
              $finalResults[]=array("NAME"=>$name."_".$value["NAME"],"TYPE"=>$value["TYPE"]);
          }
          return $finalResults;
      }
  }

  class DateType extends StringType
  {
      function getSQLDefinition($name,$definition)
      {
          return array("NAME"=>$name,"TYPE"=>"DATE");
      }
  }
  class DateTimeType extends DateType
  {
      function getSQLDefinition($name,$definition)
      {
          return array("NAME"=>$name,"TYPE"=>"DATETIME");
      }
  }

  class DecimalType extends BaseType
  {
      static function serialize($type,$opts=null)
      {
            if(!$type->hasValue())
                return NULL;
          return floatval($type->getValue());
      }

      function getBindType($type)
      {
          return "d";
      }
      function getSQLDefinition($name,$def)
      {
          $nDecimals=$def["NDECIMALS"];
          $nIntegers=$def["NINTEGERS"];
          return array("NAME"=>$name,"TYPE"=>"DECIMAL(".($nDecimals+$nIntegers).",".$nDecimals.")");
      }
  }
  class DescriptionType extends TextType {};
  class EmailType extends StringType {};
  class EnumType extends BaseType
  {
      static function serialize($type,$opts=null)
      {
          if(isset($def["MYSQL_STORE_AS_INTEGER"]))
              return IntegerType::serialize($type,$opts);
          return StringType::serialize($type,$opts);

      }
      function getBindType($type)
      {
          $def=$type->getDefinition();
          if(isset($def["MYSQL_STORE_AS_INTEGER"]))
              return 'i';
          return 's';
      }
      function getBindValue($type)
      {
          if($type->hasValue())
          {
              $def=$type->getDefinition();
              if(isset($def["MYSQL_STORE_AS_INTEGER"]))
                  return intval($type->getValue());
              return htmlentities($type->getLabel(),ENT_NOQUOTES,"UTF-8");
          }
          return "NULL";
      }
      function unserialize($type,$value)
      {
          $type->setValue($value);
      }
      function getSQLDefinition($name,$definition)
      {
          $defaultStr='';
          if(isset($definition["DEFAULT"]))
              $defaultStr=" DEFAULT '".$definition["DEFAULT"]."'";
          return array("NAME"=>$name,"TYPE"=>"ENUM('".implode("','",$definition["VALUES"])."') ".$defaultStr);
      }
  }

  class FileType extends StringType{
      function unserialize($type,$value)
      {
          // No se hace via setValue, para no provocar el intento de copia del fichero.
          $type->setUnserialized($value);
      }
      function getSQLDefinition($name,$definition)
      {
          $defaultStr='';
          if(isset($definition["DEFAULT"]))
                $defaultStr=" DEFAULT '".$definition["DEFAULT"]."'";
          return array("NAME"=>$name,"TYPE"=>"CHAR(255)".$defaultStr);
      }
  }
  class HashKeyType extends StringType {}
  class ImageType extends FileType {}
  class IPType extends StringType {}
  class LabelType extends StringType {}
  class LinkType extends StringType {}
  class LoginType extends StringType {}
  class MoneyType extends DecimalType {}
  class NameType extends StringType {}
  class NIFType extends StringType {}
  class PasswordType extends StringType{
      function getBindValue($type)
      {
          if($type->hasValue())
          {
              if($type->isEncrypted())
                  return $type->getValue();
              return $type->getEncrypted();
          }
          return "NULL";
      }
      function unserialize($type,$value)
      {
          $type->__rawSet($value);
          $type->setAsUnserialized();
      }
  }
  class PhoneType extends StringType {}
  class PHPVariableType extends StringType
  {
      function unserialize($type,$value)
      {
          if($value)
          {
              $type->setValue(unserialize($value));
          }
      }
      function getSQLDefinition($name,$definition)
      {
          return array("NAME"=>$name,"TYPE"=>"BLOB");
      }
  }
  class RelationshipType extends BaseType
  {
      static function serialize($type,$opts=null)
      {
          if(!$type->hasValue())
              return null;
          $remoteType=$type->getRelationshipType();
          $serializer=BaseType::getSerializerFor($remoteType->getDefinition());
          $remoteType->setValue($type->getValue);
          return $serializer::serialize($remoteType,$opts);
      }

      function getBindType($type)
      {
          $remoteType=$type->getRelationshipType();
          $serializer=BaseType::getSerializerFor($remoteType->getDefinition());
          $remoteType->setValue($type->getValue);
          return $serializer->getBindType($remoteType);
      }
      function getBindValue($type)
      {
          if(!$type->hasValue())
              return null;
          $remoteType=$type->getRelationshipType();
          $serializer=BaseType::getSerializerFor($remoteType->getDefinition());
          $remoteType->setValue($type->getValue);
          return $serializer->getBindValue($remoteType);
      }

      function unserialize($type,$value)
      {
          $remoteType=$type->getRelationshipType();
          \lib\model\types\TypeFactory::unserializeType($remoteType,$value,"MYSQL");
          $type->setValue($remoteType->getValue());
      }
      function getSQLDefinition($name,$definition)
      {
          // TODO : ERROR, ESTE TIPO HAY QUE CALCULARLO
          $remoteType=$type->getRelationshipType();
          $serializer=\lib\model\types\TypeFactory::getSerializer($remoteType,"MYSQL");
          return $serializer->getSQLDefinition($name,$remoteType->getDefinition());

      }
  }
  class StateType extends EnumType {}
  class StreetType extends StringType {}
  class TimestampType extends StringType
  {
      function getBindValue($type)
      {
          if($type->hasValue())
              return $type->getValue();
          else
              return $type->getValueFromTimestamp();
      }
      function getSQLDefinition($name,$definition)
      {
          return array("NAME"=>$name,"TYPE"=>"DATETIME");
      }
  }
  class TreePathType extends StringType {}
  class TypeReferenceType extends PHPVariableType
  {
      var $typeSer;
      var $typeInstance;

      function getBindValue($type)
      {
          if($type->hasValue())
          {
              return $type->getRawValue();
          }
          return null;
      }
      function unserialize($type,$value)
      {
          // TODO : ESTO ES INCORRECTO
          $type->setRawValue($value);
      }
  }
  class TypeReferenceValueType extends TextType {}

  class UrlPathString extends StringType
  {
      function getSQLDefinition($name,$definition)
      {
          if(!isset($definition["MAXLENGTH"]))
              $definition["MAXLENGTH"]=255;
          return parent::getSQLDefinition();
      }
  }

  class UUIDType extends StringType {
      function getSQLDefinition($fieldName,$definition)
      {
          return array("NAME"=>$fieldName,"TYPE"=>"varchar(36)");
      }
  }
  class UserIdType extends UUIDType {}