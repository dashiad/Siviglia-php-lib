<?php
/**
 * No quiero que cargar el sistema de cache signifique cargar demasiados ficheros.Asi que las implementaciones base van a ir aqui.
 */
namespace lib\cache;
include_once(PROJECTPATH."/lib/model/BaseException.php");
class CacheException extends \lib\model\BaseException
{
    const ERR_CACHE_MISS=1;
}
abstract class Cache
{
    const IGNORE_CACHE=1;
    const MAX_LIFETIME=2;
    var $dirty=false;
    var $opts;
    var $lifetime=0;
    var $ignore=0;
    function __construct($opts)
    {
         $this->opts=$opts;
         foreach($opts as $key=>$value)
         {
             switch($key)
             {
                 case Cache::IGNORE_CACHE:{$this->ignore=$value;}break;
                 case Cache::MAX_LIFETIME:{$this->lifetime=$value;}break;
             }
         }
    }
    function save()
    {
        if($this->dirty)
        {
            $this->_save();
            $this->dirty=false;
        }
    }
    abstract function _save();
    abstract function get($key);
    function set($key,$value)
    {
         $this->_set($key,$value);
         $this->dirty=true;
    }
    abstract function _set($key,$value);
    abstract function remove($key);
    abstract function clean();
}

class DirectoryCache extends Cache
{

    var $baseDir;
    function __construct($baseDir,$opts)
    {
        $this->baseDir=$baseDir;
        parent::__construct($opts);
    }
    function get($key)
    {
        if($this->ignore)
            throw new CacheException(CacheException::ERR_CACHE_MISS,array("key"=>$key));
        $cleanKey=str_replace($this->baseDir,"",$key);
        $fullFile=$this->baseDir."/".$cleanKey;
        $info=filemtime($fullFile);
        // Si el fichero no existe, o tiene tiempo de expiracion, y ya ha pasado, la cache no es valida.
        if((!$info) || ($this->lifetime && time() > $info + $this->lifetime))
            throw new CacheException(CacheException::ERR_CACHE_MISS,array("key"=>$key));

        return file_get_contents($cleanKey);
    }
    function _set($key,$value)
    {
        file_put_contents($this->baseDir."/".$key,$value);
    }

    function clean()
    {
        $this->recurse_clean($this->baseDir);
        clearstatcache();
    }
    function remove($key)
    {
         unlink($this->baseDir."/".$key);
    }
    function recurse_clean($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file)
          (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
        return rmdir($dir);
    }
    function _save()
    {

    }
}

class MemoryCache extends Cache
{
     var $data=array();
     function get($key)
     {
         if(isset($this->data[$key]))
            return $this->data[$key];
        throw new CacheException(CacheException::ERR_CACHE_MISS,array("key"=>$key));
     }
     function _save()
     {
     }
     function _set($key,$value)
     {
         $this->data[$key]=$value;
     }
     function clean()
     {
         $this->data=array();
         $this->dirty=true;
     }
     function remove($key)
     {
         unset($this->data[$key]);
         $this->dirty=true;
     }
     function replace($data)
     {
         $this->data=$data;
         $this->dirty=true;
     }
}

class SerializedFileCache extends MemoryCache
{
     var $data;
     var $baseFile;
     function __construct($baseFile,$opts)
     {
         parent::__construct($opts);
         $this->baseFile=$baseFile;
         if(!is_file($baseFile))
            $this->data=array();
         else
            $this->data=unserialize(file_get_contents($baseFile));
     }
     function _save()
     {
         file_put_contents($this->baseFile,serialize($this->data));
     }
     function clean()
     {
         parent::clean();
         unlink($this->baseFile);
     }
     function remove($key)
     {
         unset($this->data[$key]);
         $this->dirty=true;
     }
}
