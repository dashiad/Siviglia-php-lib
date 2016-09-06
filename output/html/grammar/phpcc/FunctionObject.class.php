<?php
/**
 * Encapsulates a method from an object
 */
class FunctionObject
{
    var $target;
    var $method_name;
    var $params;

    function FunctionObject(&$target, $method_name, $params=array()) {
        #@gencheck
        if($target == null)
        {
        	//if(!function_exists($method_name)) { print_backtrace('Function ' . $method_name . ' does not exist');        }
        }
        else {
            if(!method_exists($target, $method_name)) { print_backtrace('Method ' . $method_name . ' does not exist in ' . getClass($target));        }
        }//@#

        $this->setTarget($target);
        $this->method_name = $method_name;
        $this->params = $params;
    }

    function getMethodName() {
    	return $this->method_name;
    }

    function getParams() {
    	return $this->params;
    }

	function setTarget(&$target){
		$this->target =& $target;
	}
	function &getTarget(){
		return $this->target;
	}
    function &call() {
        
      	$method_name = $this->method_name;
      	$ret = '';
        if($this->target==null)
            $ret = call_user_func($method_name,$this->params);
        else
            $ret=call_user_func(array($this->target,$method_name),$this->params);
        
       	//eval($this->callString($method_name) . '($this->params);');
       	return $ret;
    }
	function execute() {
        
        call_user_func($this->method_name,$this->params);
       	//eval($this->executeString($method_name) . '($this->params);');
    }
	function executeWith(&$params) {
        
      	//$method_name = $this->method_name;
        call_user_func($this->method_name,$params,$this->params);
       	//eval($this->executeString($method_name) . '($params, $this->params);');
    }

	function executeWithWith(&$param1, &$param2) {
        
      	//$method_name = $this->method_name;
        call_user_func($this->method_name,$param1,$param2,$this->params);
       	//eval($this->executeString($method_name) . '($param1, $param2, $this->params);');
    }

    function callString($method) {
        
    	if ($this->target === null) {
    		return '$ret =& '. $method;
    	}
    	else {
       		return '$t =& $this->getTarget(); $ret =& $t->' . $method;
    	}
    }
    function executeString($method) {
        
    	if ($this->target === null) {
    		return $method;
    	}
    	else {
       		return '$t =& $this->getTarget(); $t->' . $method;
    	}
    }
	/**
	 *  Permission checking
	 */
	function hasPermissions(){
		$m = $this->method_name;
		$msg = 'check'.ucfirst($m).'Permissions';
		if (method_exists($this->target, $msg)){
			return $this->target->$msg($this->params);
		} else {
			return true;
		}
	}

    function &callWith(&$params) {

		$method_name = $this->method_name;
		$ret ='';
        if($this->target==null)
            $ret = call_user_func($method_name,$params,$this->params);
        else
            $ret= call_user_func(array($this->target,$method_name),$params,$this->params);

    	//eval($this->callString($method_name) . '($params, $this->params);');
    	return $ret;
    }

    function &callWithWith(&$param1, &$param2) {
        
    	$method_name = $this->method_name;
    	$ret ='';
    	//eval($this->callString($method_name) . '($param1, $param2, $this->params);');

        if($this->target==null)
            $ret = call_user_func($method_name,$param1,$param2,$this->params);
        else
            $ret= call_user_func(array($this->target,$method_name),$param1,$param2,$this->params);
    	return $ret;
    }

    /* We may want to use function objects as ValueHolders. Similar to Aspect adaptors */

    function &getValue() {
    	return $this->call();
    }

    function setValue(&$value) {
    	return $this->callWith($value);
    }

    function primPrintString($str){
        return '[' . getClass($this) . ' ' . $str .']';
    }

    function printString() {
        return $this->primPrintString($this->target->printString() . '->' . $this->method_name);
    }

    function debugPrintString() {
    	return $this->primPrintString($this->target->debugPrintString() . '->' . $this->method_name);
    }
}

function &callback(&$target, $selector) {
	return new FunctionObject($target, $selector);
}

?>