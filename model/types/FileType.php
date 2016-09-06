<?php namespace lib\model\types;

 class  FileTypeException extends BaseTypeException{
      const ERR_FILE_TOO_SMALL=100;
      const ERR_FILE_TOO_BIG=101;
      const ERR_INVALID_FILE=102;
      const ERR_NOT_WRITABLE_PATH=103;
      const ERR_INVALID_ARRAY_VALUE=104;
      const ERR_FILE_DOESNT_EXISTS=105;
      const ERR_CANT_MOVE_FILE=106;
      const ERR_CANT_CREATE_DIRECTORY=107;
      const ERR_UPLOAD_ERR_PARTIAL=108;
      const ERR_UPLOAD_ERR_CANT_WRITE=109;
      const ERR_UPLOAD_ERR_INI_SIZE=110;
      const ERR_UPLOAD_ERR_FORM_SIZE=111;

  }

  class FileType extends BaseType
  {                      
      var $mustCopy=false;
      var $srcFile='';
      var $uploadedFileName=null;
      function __construct($def,$neutralValue=null)
      {
        parent::__construct($def,$neutralValue);
        $this->setFlags(BaseType::TYPE_IS_FILE | BaseType::TYPE_REQUIRES_UPDATE_ON_NEW | BaseType::TYPE_REQUIRES_SAVE | BaseType::TYPE_NOT_MODIFIED_ON_NULL);
      }      
      function setFlags($flags)
      {
          $this->flags|=$flags;
      }
      function getFlags()
      {
          return $this->flags;
      }
      // setUnserialized no marca como dirty.
      function setUnserializedValue($val)
      {
          if(isset($val) && !$empty($val))
          {
              $this->value=$val;
              $this->valueSet=true;
          }
      }

      function setValue($val)
      {
          if($val===null || !isset($val))
          {
              if($this->hasOwnValue())
                  $this->dirty=true;
              return $this->clear();
          }
                              
          $osName=PHP_OS;
          $relative=false;
          if($osName[0]=="W") // WINDOWS,WINNT
          {
               // Se mira si el path es un path relativo.En caso de que sea asi, se ajusta.
              if($val[0]!="/" && $val[0]!='\\' && !($val[1]==":" && $val[1]!='\\'))
                  $relative=true;
          }
          else
          {
              if($val[0]!="/")
                  $relative=true;
          }
              
          // En caso de que el valor sea un path relativo, se presupone que parte de PROJECTPATH.
          if($relative)
              $newPath=realpath(PROJECTPATH."/".$val);
          else
              $newPath=realpath($val);
          // Si no hay $fileInfo, es que el fichero no existe.
          if(!$newPath)
          {        
              clean_debug_backtrace();
              throw new FileTypeException(FileTypeException::ERR_FILE_DOESNT_EXISTS,array("path"=>$val));
          }          
              
          $val=$newPath;
          $this->validate($val);

          if($this->hasOwnValue())
          {
              if($this->value==$val)
                  return;
              $this->oldValue=$this->value;
          }

          $this->srcFile=$val;
          $this->valueSet=true;
          $this->dirty=true;
          // Ojo..En caso de que estemos en un upload de HTML, este val es el fichero temporal PHP.Esto es asi, porque este tipo de dato,
          // usa el contexto para decidir el nombre y el path de fichero.Y ese contexto no estara listo hasta que se haya hecho save() del
          // modelo.Por ejemplo, esto ocurre cuando en el nombre del fichero debe ir un id autogenerado.
          $this->value=$val;
      }

      function equals($value)
      {          
          if($this->value===null || $value===null)
              return $this->value===$value;
          return $this->value==$value;
      }

      function getValue()
      {
          if($this->valueSet)
            return $this->value; 
          if($this->hasDefaultValue())
            return $this->getDefaultValue();
          return null;          
      }
      
      function validate($srcValue)
      {           
          
        $value=$srcValue;        
        $fsize=filesize($value);
        if(isset($this->definition["MINSIZE"]) && $this->definition["MINSIZE"] > $fsize)
            throw new FileTypeException(FileTypeException::ERR_FILE_TOO_SMALL,array("minsize"=>$this->definition["MINSIZE"],"actualsize"=>$fsize));

        if(isset($this->definition["MAXSIZE"]) && $this->definition["MAXSIZE"] < $fsize)
            throw new FileTypeException(FileTypeException::ERR_FILE_TOO_BIG,array("minsize"=>$this->definition["MAXSIZE"],"actualsize"=>$fsize));
        
        // El nombre original del fichero no  tiene por que ser lo que nos han pasado como "value", ya que, en un input HTML, el value
        // sera el nombre del fichero temporal.
        // El deserializador HTML es el que tiene que llamar a setOriginalFileName, para especificar el nombre original del fichero en el disco
        // del usuario.Por ello, para comprobar las extensiones de fichero, hay que mirar si se ha especificado el originalFileName.
        if(isset($this->uploadedFileName))
            $fname=$this->uploadedFileName;
        else
            $fname=$value;        

        if(isset($this->definition["EXTENSIONS"]))
        {
            if(!is_array($this->definition["EXTENSIONS"]))
                $allowedExtensions=array($this->definition["EXTENSIONS"]);
            else
                $allowedExtensions=$this->definition["EXTENSIONS"];
            
            $extension=array_pop(explode(".",$fname));
            
            if(!in_array($extension,array_map(strtolower,$allowedExtensions)))
            {
                throw new FileTypeException(FileTypeException::ERR_INVALID_FILE,array("extension"=>$extension,"allowed"=>$allowedExtensions));
            }
        }

       return true;

      }

      function calculateFinalPath($filename)
      {
          global $globalPath;
          global $globalContext;
          $filePath=$this->definition["TARGET_FILEPATH"];
          // Si esta establecido TARGET_FILENAME, no especifica una extension, asi que hay que copiarla de $filename.

          if(isset($this->definition["TARGET_FILENAME"]))
          {
                 $filePath.="/".$this->definition["TARGET_FILENAME"].".".(array_pop(explode(".",$filename)));
          }
             else
                 $filePath.="/".$filename;
          return $globalPath->parseString($filePath,$globalContext);
      }

      function postValidate($value)
      {
          
          if(!$this->hasValue())
          {
              return;
          }

          if($this->srcFile)
          {
              // Ahora hay que mover el fichero a su path final.El procedimiento para hacer esto es distinto segun si el fichero
              // ha venido via HTML (lease, move_uploaded_file), o no.Esto, tambien podria hacerse en el serializador HTML, cosa que 
              // queda pendiente de analizar los pro/contra.El mecanismo seria que el serializer haga el move_uploaded_file a algun sitio,
              // y que el tipo de dato, lo vuelva a mover a su destino final.Esta complejidad es lo que no me gusta de moverlo al serializador.
              // Pero, a la vez, hacerlo en el tipo de dato (esta clase), supone chequear si se ha establecido o no cierta variable, no directamente
              // relacionada con move_uploaded_file.

              // En el metodo "save", que se ha tenido que llamar antes, se ha calculado $value.

              $destFile=$this->getFullFilePath();

              if($this->uploadedFileName)
              {
                  if(!move_uploaded_file($this->srcFile,$destFile))
                  {
                      throw new FileTypeException(FileTypeException::ERR_ERR_CANT_MOVE_FILE,array("src"=>$this->srcFile,"dest"=>$destFile));
                  }
              }
              else
              {
                  if(!@rename($this->srcFile,$destFile))
                      throw new FileTypeException(FileTypeException::ERR_CANT_MOVE_FILE,array("src"=>$this->srcFile,"dest"=>$destFile));
                  
              }

              $this->uploadedFileName=null;
              $this->srcFile=null;
          }
          
      }
      function getFullFilePath()
      {
          if(!$this->hasOwnValue())
              return "";
          if(isset($this->definition["PATHTYPE"]) && $this->definition["PATHTYPE"]=="ABSOLUTE")
                  return $this->value;
              else
                  return PROJECTPATH."html/".$this->value;
      }
      function clear()
      {
          if($this->hasOwnValue())
          {
              // Tenia valor, pero ahora se pone a null=>puede haber que borrar el fichero.
              if(!isset($this->definition["AUTODELETE"]) || $this->definition["AUTODELETE"]==true)
              {
                 if(file_exists($this->value))
                      @unlink($this->value);
              }
          }
          $this->valueSet=false;
          $this->value=null;
          return;
      }
      function setUploadedFilename($filename)
      {
          $this->uploadedFileName=$filename;
      }
      function setUnserialized($value)
      {
          $this->valueSet=true;
          $this->value=$value;
          $this->srcFile=null;
          $this->isUpload=null;
      }
      function save()
      {
          if(!$this->dirty)
              return;
          $this->dirty=false;
          if($this->srcFile)
          {
              // Ahora hay que mover el fichero a su path final.El procedimiento para hacer esto es distinto segun si el fichero
              // ha venido via HTML (lease, move_uploaded_file), o no.Esto, tambien podria hacerse en el serializador HTML, cosa que 
              // queda pendiente de analizar los pro/contra.El mecanismo seria que el serializer haga el move_uploaded_file a algun sitio,
              // y que el tipo de dato, lo vuelva a mover a su destino final.Esta complejidad es lo que no me gusta de moverlo al serializador.
              // Pero, a la vez, hacerlo en el tipo de dato (esta clase), supone chequear si se ha establecido o no cierta variable, no directamente
              // relacionada con move_uploaded_file.
              if($this->uploadedFileName)
              
                  $filePath=$this->calculateFinalPath($this->uploadedFileName);
              else
                  $filePath=$this->calculateFinalPath(basename($this->srcFile));

              
              $destDir=dirname($filePath);
              if(!is_dir($destDir))
              {
                  if(!@mkdir($destDir,0777,true))
                      throw new FileTypeException(FileTypeException::ERR_CANT_CREATE_DIRECTORY,array("dir"=>$destDir));
              }
              if(!is_writable(dirname($filePath)))
                   throw new FileTypeException(FileTypeException::ERR_NOT_WRITABLE_PATH,array("path"=>$filePath));

              // Establecemos de nuevo el valor.
              // Por defecto, se va a almacenar un path relativo al projectPath
              
              if(!isset($this->definition["PATHTYPE"]) || $this->definition["PATHTYPE"]=="RELATIVE")
              {
                  $filePath=trim(str_replace(PROJECTPATH."html/","",$filePath),"/");
              }
              $this->value=$filePath;
          }
          $this->postValidate(null);           
      }

      function copy($type)
      {
          if($type->hasValue())
          {
              $remVal=$type->getValue();
              if($this->hasOwnValue() && $remVal==$this->value)
                  return;

              $this->valueSet=true;
              $this->value=$type->getValue();
              $this->srcFile=$type->srcFile;
              $this->uploadedFileName=$type->uploadedFileName;

                            
          }
          else
          {
              if(!$this->hasValue())
                  return;
              $this->valueSet=false;
              $this->value=null;
              $this->srcFile=null;
              $this->uploadedFileName=null;
          }
          $this->dirty=true;
      }      
  }

  class FileMeta extends \lib\model\types\BaseTypeMeta
  {
    function getMeta($type)
    {
        $def=$type->getDefinition();
        unset($def["TARGET_FILEPATH"]);
        unset($def["TARGET_FILENAME"]);
        return $def;
    }
  }

  class FileTypeHTMLSerializer extends BaseTypeHTMLSerializer
  {
      function serialize($type)
      {
          if($type->hasValue())return $type->getValue();
		  return "";
      }
      function unserialize($type,$value)
      {
          switch($value["error"])
          {
          case UPLOAD_ERR_PARTIAL: // error 3: 
            {
                throw new FileTypeException(FileTypeException::ERR_UPLOAD_ERR_PARTIAL);
            }break;
          case UPLOAD_ERR_INI_SIZE: // error 7
          {
                throw new FileTypeException(FileTypeException::ERR_UPLOAD_ERR_INI_SIZE);
          }break;
          case UPLOAD_ERR_FORM_SIZE:
              {
                  throw new FileTypeException(FileTypeException::ERR_UPLOAD_ERR_FORM_SIZE);
              }break;
          case UPLOAD_ERR_CANT_WRITE:
              {
                  throw new FileTypeException(FileTypeException::ERR_UPLOAD_ERR_CANT_WRITE);
              }break;
          }

          if($value["error"]==UPLOAD_ERR_NO_FILE ) // No file uploaded
              return;

          // TODO : check error en $value["error"]
          $type->setUploadedFilename($value["name"]);
          $type->setValue($value["tmp_name"]);
  
      }
  }


?>
