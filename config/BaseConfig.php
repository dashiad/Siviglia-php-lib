<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 22/07/15
 * Time: 18:38
 */

namespace lib\config;
use lib\php\ManagedSourceCode;

class BaseConfig implements \lib\reflection\ManagedSourceCode {
    function __construct()
    {
    }
    function getSourceTemplate()
    {
        return "BaseConfig";
    }
    function getAsArray()
    {
        $obj=new \ReflectionClass($this);
        $class = $this;
        $joinedProperties = array();
        do {
            $reflection = new \ReflectionClass($class);
            $staticProperties = $reflection->getStaticProperties();
            foreach ($staticProperties as $name => $value) {
                if (is_array($value)) {
                    if (isset($joinedProperties[$name]))
                        $joinedProperties[$name] = array_merge($value, $joinedProperties[$name]);
                    else
                        $joinedProperties[$name] = $value;
                } else {
                    if (isset($joinedProperties[$name]))
                        $joinedProperties[$name][] = $value;
                    else
                        $joinedProperties[$name] = array($value);
                }
            }
        } while ($class = get_parent_class($class));
        return $joinedProperties;
    }
}