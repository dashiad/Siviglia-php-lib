<?php namespace lib\model;
  class BaseException extends \Exception
  {
      var $params;

    public function __construct($code,$params=null)
      {           
          $this->params=$params;       
          parent::__construct("", $code);
      }    
    public function getParams()
    {
        return $this->params;
    }
    public function fatal()
    {
        return true;
    }
    public function __toString()
      {
        $rfl=new \ReflectionClass(get_class($this));
        $constants=array_flip($rfl->getConstants());
        $cad= get_class($this)."[ {$this->code} :".$constants[$this->code]." ] <br>";
        if($this->params) {
            $cad=\lib\php\ParametrizableString::getParametrizedString($cad,$this->params);
            $cad.="<br>";
        }
        $cad.=json_encode($this->getTrace());
        return $cad;
      }
    
    function getCodeString()
    {
        $reflectionClass=new \ReflectionClass($this);
        $className=get_class($this);
        // Se obtienen las constantes
        $constants = $reflectionClass->getConstants();
        foreach($constants as $key=>$value)
        {            
            if($this->code==$value)
            {
                return $className."::".$key;
            }
            
        }
        return "UNKNOWN::UNKNOWN";
    }
      function getParamsAsString()
      {
          if($this->params=="")
              return "";
        ob_start();
          print_r($this->params);
        return ob_get_clean();
      }

      static function getErrors($type)
      {

          $reflectionClass=new \ReflectionClass($type);
          $unq=$reflectionClass->getShortName();
          $constants=$reflectionClass->getConstants();
          $map=array();
          $errors=array();

          // Primero se obtienen los errores reales, aquellos que no contienen "TXT"
          foreach($constants as $key2=>$value2)
          {
              if( strpos($key2,"TXT_")!==0 && strpos($key2,"REQ_")!==0)
              {
                  $map[$key2]=array("code"=>$value2);
              }
          }
          // Luego, se obtienen los valores de cadena, y se busca si hace match con
          // alguna de las excepciones anteriores
          // Hay que tener en cuenta que se obtienen
          foreach($constants as $key2=>$value2)
          {
              if( strpos($key2,"TXT_")===0 || strpos($key2,"REQ_")===0 )
              {
                  $target=$key2;
                  $cut=substr($key2,4);
                  if(isset($map["ERR_".$cut]))
                      $target="ERR_".$cut;
                  if(isset($map[$cut]))
                      $target=$cut;

                  if(!isset($map[$target]))
                      $map[$target]["name"]=$key2;
                  $map[$target][strtolower(substr($key2,0,3))]=$value2;
              }

          }


          return $map;
      }
      static function getPrintableErrors($type,$definition=null)
      {
          $err=BaseException::getErrors($type);
          $results=array();

              foreach($err as $key2=>$value2)
              {
                  // Si hay un texto:
                  // Si no hay una constante "REQ", se incluye
                  // Si hay  una constante REQ, y su valor no esta en la definicion, o esta, pero evalue a falso, no se incluye
                  if(isset($value2["txt"]))
                  {
                      if(!isset($value2["req"]) || ($definition && isset($definition[$value2["req"]]) && $definition[$value2["req"]]))
                          $results[$key2]=array("txt"=>$value2["txt"],"code"=>$value2["code"]);
                  }
              }
          return $results;
      }


  }
