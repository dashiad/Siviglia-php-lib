<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 23/06/2017
 * Time: 12:54
 */

namespace lib\storage\File;
use \lib\storage\Base\Connectors\TreeBased;
use \lib\storage\Base\Connectors\TreeBasedException;

class FileConnector extends TreeBased
{
    var $prefix=null;
    var $currentPath=null;
    function __construct($params)
    {
        if(isset($params["PATH"]))
            $this->setPath($params["PATH"]);
    }
    function setPath($p)
    {
        if($p!=null)
        {
            $this->prefix=realpath($p);
            if($this->prefix===false) {
                $this->createDirectory($p);
                $this->prefix = realpath($p);
            }
        }
    }
    function fixPath($p)
    {
        if($this->prefix==null)
            return $p;
        return $this->prefix."/".$p;
    }
    function checkPath($p)
    {
        if($this->prefix==null)
            return true;
        $rp=realpath($p);
        $pos=strpos($rp,$this->prefix);
        return $pos!==false?$rp:false;
    }
    function dirExists($p)
    {
        $p=$this->fixPath($p);
        return is_dir($p);
    }

    function getCurrentPath()
    {
        return $this->prefix;
    }

    function getFileList($path=null,$nameFilter=null,$onlyFiles=false)
    {
        $path=$this->fixPath($path);
        if($path==null) {
            if($this->prefix==null)
                throw new TreeBasedException(TreeBasedException::ERR_NO_PATH);
            $path = $this->prefix;
        }
        $d=opendir($path);
        if(!$d)
            throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
        $results=array();
        while($f=readdir($d))
        {
            if($f=="." || $f=="..")
                continue;
            $isDir=is_dir($path."/".$f);
            if($onlyFiles==true)
            {
                if($isDir)
                    continue;
            }
            if($nameFilter==null || preg_match($nameFilter,$f)) {
                $node = new \lib\storage\Base\Connectors\FileSystemNode();
                $node->fileSystem = $this;
                $node->path = $path;
                $node->name = $f;
                $node->isDir=$isDir;
                $results[]=$node;
            }
        }
        return $results;
    }

    function saveFile($path,$data)
    {
        $path=$this->fixPath($path);
        if(!file_put_contents($path,$data))
        {
            throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
        }
    }

    function readFile($path)
    {
        $path=$this->fixPath($path);
        $d=@file_get_contents($path);
        if($d===false) {
            if(!is_file($path))
                throw new TreeBasedException(TreeBasedException::ERR_NO_SUCH_NODE,array("path"=>$path));
            else
                throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
        }
        return $d;
    }

    function fileExists($path)
    {
        $path=$this->fixPath($path);
        return is_file($path);
    }
    function removeFile($p)
    {
        $path=$this->fixPath($p);
        $path=$this->checkPath($path);
        if($path===false)
            throw new TreeBasedException(TreeBasedException::ERR_NO_SUCH_PATH,array("path"=>$path));
        if(!is_file($path))
            throw new TreeBasedException(TreeBasedException::ERR_NO_SUCH_NODE,array("path"=>$path));
        if(!@unlink($path))
            throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
    }

    function removePath($p, $recursive = false)
    {
        $path=$this->fixPath($p);
        $path=$this->checkPath($path);
        if($path===false)
            throw new TreeBasedException(TreeBasedException::ERR_NO_SUCH_PATH,array("path"=>$path));
        if(is_file($path))
        {
            if(!unlink($p))
                throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
        }
        if(!$recursive)
        {
            if(!rmdir($p))
            {
                throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
            }
        }
        else
            $this->removeFilesByRegularExpression($p,null,$recursive);
    }

    function removeFilesByRegularExpression($path,$regexp,$recursive=false)
    {
        $path=$this->fixPath($path);
        $this->execOnPath($path,
            function($file,$path){
                if(unlink($path."/".$file)===false)
                    throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED,$path."/".$file);
            },
            null,
            function($path)
            {
                if(rmdir($path)==false)
                    throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED,$path);
            },
            $regexp,
            $recursive
            );
    }

    private function execOnPath($path,$fileCallback,$directoryCallback,$postDirCallback=null,$regexp=null,$recursive=false)
    {
        $files = array_diff(scandir($path), array('.','..'));
        foreach ($files as $file) {
            $cur=$path."/".$file;
            if(is_dir($cur))
            {
                if($directoryCallback)
                    call_user_func($directoryCallback,$file,$path);
                if($recursive)
                {
                    $this->execOnPath($cur,$fileCallback,$directoryCallback,$postDirCallback,$regexp,$recursive);
                }
            }
            else
            {
                if($regexp==null || preg_match($regexp,$file)) {
                    if($fileCallback)
                        call_user_func($fileCallback,$file,$path);
                }
            }
        }
        if($postDirCallback)
            call_user_func($postDirCallback,$path);
    }


    function createDirectory($path,$recursive=true,$permissions=0777)
    {
        $path=$this->fixPath($path);
        if(!mkdir($path,$permissions,$recursive))
            throw new TreeBasedException(TreeBasedException::ERR_PERMISSION_DENIED);
    }

    static function recurse_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    function pathExists($p)
    {
        $p=$this->fixPath($p);
        return file_exists($p);
    }

    function getNodes($path,$nameFilter=null,$onlyLeaves = false)
    {
        return $this->getFileList($path,$nameFilter,$onlyLeaves);
    }

    function setData($p, $data)
    {
        $this->saveFile($p,$data);

    }
    function getData($p)
    {
        return $this->readFile($p);
    }
    function leafExists($p)
    {
        return $this->fileExists($p);

    }
    function nodeExists($p)
    {
        return $this->fileExists($p);
    }

    function removeLeaf($p)
    {
        $this->removeFile($p);
        // TODO: Implement removeLeaf() method.
    }

    function addPath($path,$recursive=true)
    {
        $this->createDirectory($path,$recursive);
    }

    function disconnect()
    {
    }

    function connect()
    {
        return true;
    }

    function isConnected()
    {
        return true;
    }

}
