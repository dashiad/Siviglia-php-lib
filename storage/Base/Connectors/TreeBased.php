<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 23/06/2017
 * Time: 12:49
 */

namespace lib\storage\Base\Connectors;

class TreeBasedException extends \lib\model\BaseException
{
    const ERR_NO_SUCH_PATH=1;
    const ERR_NO_SUCH_NODE=2;
    const ERR_PERMISSION_DENIED=3;
    const ERR_NO_PATH=4;
    const TXT_NO_SUCH_PATH="Path not found : %s";
    const TXT_NO_SUCH_FILE="File not found : Path :[%path%] [%file:, file:{%file%}%]";
    const TXT_PERMISSION_DENIED="Permission denied";
    const TXT_ERR_NO_PATH="No path set";
}


abstract class TreeBased extends Connector
{
    abstract function setPath($p);
    abstract function getCurrentPath();
    abstract function getNodes($path,$nameFilter=null,$onlyLeaves=false);

    abstract function setData($p,$data);
    abstract function getData($p);
    abstract function leafExists($p);
    abstract function nodeExists($p);
    abstract function removeLeaf($p);
    abstract function removePath($p,$recursive=false);
    abstract function pathExists($p);
    abstract function addPath($p,$recursive=true);
}