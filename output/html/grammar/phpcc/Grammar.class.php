<?php

class Grammar {
	var $pointcuts = array();
	var $errors = array();
	function Grammar($params) {
		$this->params = & $params;
		foreach (array_keys($this->params['nt']) as $k) {
			$this->params['nt'][$k]->setParent($this, $this);
		}
	}
	function &get($name) {
		return $this->params['nt'][$name];
	}
	function addPointCuts($ps){
		$this->setPointcuts(array_merge($this->pointcuts,$ps));
	}
	function setPointCuts($ps){
		$this->pointcuts=$ps;
	}
	function &getGrammar() {
		return $this;
	}
	function &getProcessor($name) {
		return $this->pointcuts[$name];
	}
	function &getRoot() {
		$root = & new SubParser($this->params['root']);
		$root->setParent($this,$this);
		return $root;
	}
	function &process($name, &$data){
		$p =& $this->getProcessor($name);
		if ($p===null){
			return $data;
		} else {
			return $p->callWith($data);
		}
	}
	function &compile($str) {
		$this->errors = array();
		$this->input = $str;
		$root =& $this->getRoot();
		$this->res = $root->parse(new ParseInput($str));  
              
		if (preg_match('~^[\s\t\n]*$~',$this->res[1]->str)){
			return $root->process($this->res[0]->match);//$this->process($this->params['root'],$res1);
		} else {
            
			return $this->getError($str);
		}
	}
	function isError(){
		return empty($this->errors);
	}
	function &getError(){
		$str = $this->input;
		$ret = '';
		foreach ($this->errors as $remaining=> $symbol){
			if ($remaining==0){
				$rem = 'EOF';
				$prev = $str;
			} else {
				$rem = '"'.substr($str, -$remaining, 1). '"';
				$prev = substr($str,0, -$remaining);
			}
			$lines = explode("\n",$prev);
			$nl = count($lines);
			$ret .="\n".'Unexpected '.$rem.', expecting '.$symbol.
			' on line '.$nl. ',character '.(strlen(array_pop($lines))+1);
		}

		return $ret;
	}
	function setError($err){
		$this->errors= $err;
	}
	function print_tree() {
		$ret =  "<".$this->params['root']."(\n   ";
		foreach (array_keys($this->params['nt']) as $k) {
			$ret.= $k . '::='.
				$this->params['nt'][$k]->print_tree().
				".\n   ";
		}
		return $ret . ")>";
	}
}


class AltParser extends Parser {
	var $errorBuffer = array();
	function AltParser($children, $backtrack=false) {
		if (!is_array($children)) {
			echo 'NOT ARRAY!';
			print_r($children);
			exit;
		}
		parent :: Parser();
		$this->backtrack = $backtrack;
		$this->children =& $children;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		foreach (array_keys($this->children) as $k) {
			$this->children[$k]->setParent($this, $grammar);
		}
	}
	function parse($tks) {
		$return = array(ParseResult::fail(), $tks);
		foreach (array_keys($this->children) as $k) {
			$c = & $this->children[$k];
			$res = $c->parse($tks);
			if (!$res[0]->failed() && !$res[0]->isLambda()) {
				//if ($res[0]->isLambda()) print_backtrace(strtolower(get_class($c). ' '.htmlentities($this->print_tree()));
				if ($res[1]->isBetterMatchThan($return[1])) {
					$res[0]= ParseResult::match(array('selector'=>$k,'result'=>$res[0]->match));
					$return =  $res;
				}
			}
		}
		if ($return[0]->failed()){
			parent::setError($this->errorBuffer);
		}
		$this->errorBuffer=array();
		return $return;

	}
	function print_tree() {
		foreach (array_keys($this->children) as $k) {
			$c = & $this->children[$k];
			$t = $c->print_tree();
			if (strtolower(get_class($c))=='altparser'){
				$t = '('.$t.')';
			}
			if (is_numeric($k)){
				$ret []= $t;
			} else {
				$ret []= $k.'=>'.$t;
			}
		 }
		 return implode('|',$ret);
	}
	function &process($result) {
		if (!$this->children[$result['selector']]) {echo 'wrong alternative:';var_dump($result);}
		$rets =&$this->children[$result['selector']]->process($result['result']);
		 $arr = array('selector'=>$result['selector'],'result'=>$rets);
		return $arr;
	}
	function setError($err){
		$this->errorBuffer= array_merge($err,$this->errorBuffer);
	}
}


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




class ListParser extends Parser {
	var $errorBuffer = array();
	function ListParser(& $parser, & $separator) {
		parent :: Parser();
		$this->sep = & $separator;
		$this->parser = & $parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		$this->sep->setParent($this, $grammar);
		$this->parser->setParent($this, $grammar);
	}
	function parse($tks) {
		/*first, we parse the list*/
		$mp = & new MultiParser(new SeqParser(array (
			$this->parser,
			$this->sep
		)));
		$mp->setParent($this, $this->grammar);
		$res = $mp->parse($tks);
		/* then, we parse again, in the tail of the list */
		$res1 = $this->parser->parse($res[1]);
		/* if the tail failed, the parse failed */
		if ($res1[0]->failed() || $res1[0]->isLambda()) {
			parent::setError($this->errorBuffer);
			return array (
				ParseResult::fail(),
				$tks
			);
		}
		/* we collect the last parsed token, in the first position of the subarray (as all the other ones) */
		$res[0]->match[] = array (
			$res1[0]->match
		);
		return array (
			ParseResult::match($res[0]->match),
			$res1[1]
		);
	}
	function setError($err){
		$this->errorBuffer= array_merge($err,$this->errorBuffer);
	}
	function print_tree() {
		return '{'.
		$this->parser->print_tree().
		';'.
		$this->sep->print_tree().
		'}';
	}
	function &process($res) {
		for ($i=0; $i<count($res);$i++){
			$ret []=&$this->parser->process($res[$i][0]);
			if(isset($res[$i][1]))
				$ret []=&$this->sep->process($res[$i][1]);
		}
		return $ret;
	}

}


class MaybeParser extends Parser {
	function MaybeParser(& $parser) {
		parent :: Parser();
		$this->parser = & $parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		$this->parser->setParent($this, $grammar);
	}
	function parse($tks) {
		$res = $this->parser->parse($tks);
		if ($res[0]->failed()) {
			return array (
				ParseResult::lambda(),
				$tks
			);
		} else {
			return $res;
		}
	}
	function print_tree() {
		return  '['.
		$this->parser->print_tree().
		']';
	}
	function &process($result) {
		if ($result!=null) return $this->parser->process($result); else {return $result;}
	}
	function setError($err){}
}



class MultiParser extends Parser {
	function MultiParser(& $parser) {
		parent :: Parser();
		$this->parser = & $parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		$this->parser->setParent($this, $grammar);
	}
	function parse($tks) {
		$res = $this->parser->parse($tks);
		$ret = array();
		while ((!$res[0]->failed()) && !$res[0]->isLambda()) {
			$ret[] = $res[0]->match;
			$res = $this->parser->parse($res[1]);
		}
		if (empty($ret)){
			return array (ParseResult::lambda(),$tks);
		} else {
			return array (ParseResult::match($ret),	$res[1]);
		}
	}
	function print_tree() {
		return '('.$this->parser->print_tree(). ')*';
	}
	function &process($res) {
		$ret = array();
        
		foreach($res as $r){
			$ret []=&$this->parser->process($r);
		}
		return $ret;
	}
	function setError($err){$this->buffer = $err;}
}

class MultiOneParser extends MultiParser{
	function parse($tks) {
		$res = parent::parse($tks);
		if (count($res[0])==0){
			parent::setError($this->buffer);
			return array(ParseResult::fail(), $tks);
		} else {
			return $res;
		}
	}
	function print_tree() {
		return '('.$this->parser->print_tree(). ')+';
	}
}



class SeqParser extends Parser {
	function SeqParser($children) {
		if (!is_array($children)) {
			print_r($children);
			exit;
		}
		parent :: Parser();
		$this->children =& $children;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		foreach (array_keys($this->children) as $k) {
			$this->children[$k]->setParent($this, $grammar);
		}
	}

	function &process($result) {
		foreach (array_keys($this->children) as $k) {
			$rets [$k]=&$this->children[$k]->process($result[$k]);
		}
		return $rets;
	}
	function parse($tks) {
		$res = array (FALSE,$tks);
		$ret = array();
		foreach (array_keys($this->children) as $k) {
			$res = $this->children[$k]->parse($res[1]);
			if ($res[0]->failed()) {
				return array (ParseResult::fail(),$tks);
			}
			$ret[$k] = $res[0]->match;
		}
		return array (ParseResult::match($ret),$res[1]);
	}
	function print_tree() {
		foreach (array_keys($this->children) as $k) {
			$c = & $this->children[$k];
			$t = $c->print_tree();
			if (strtolower(get_class($c))=='altparser'){
				$t = '('.$t.')';
			}
			if (is_numeric($k)){
				$ret []= $t;
			} else {
				$ret []= $k.'->'.$t;
			}
		}
		return implode(' ',$ret);
	}
}



class SubParser extends Parser {
	function SubParser($name) {
		parent :: Parser();
		$this->subName = $name;
	}
	function parse($tks) {
		if (($res = $tks->getPartial($this->subName))===null){
			if ($tks->includesNonTerminal($this->subName)){
				$tks->reDescendNonTerminal($this->subName);
				return array(ParseResult::fail(), $tks);
			}
			$p = & $this->get($this->subName);
			$g =& $this->getGrammar();
			if ($p===null) {print_backtrace_and_exit($this->subName .' does not exist');}
			$tks->pushNonTerminal($this->subName);
			$p->setErrorHandler($this);
			$res = $p->parse($tks);
			$tks->popNonTerminal($this->subName);
			$next = $res;
			$str = $tks->str;
			$tks->str = '';
			$parts = $tks->partials;
			$tks->partials = array();
			while ($tks->shouldReDescend($this->subName) && !$next[0]->failed() && !$next[0]->isLambda()){
				$tks->addPartial($this->subName, $res);
				$res =$next;
				$next = $p->parse($tks);
			}
			$tks->str = $str;
			$tks->partials = $parts ;
			$tks->addPartial($this->subName, $res);
			$p->popErrorHandler();
		}
		return $res;
	}
	function setError($err){
		$eh =& $this->popErrorHandler();
		$eh->setError($err);
		$this->setErrorHandler($eh);
	}
	function &getParser(){
		return $this->get($this->subName);
	}
	function print_tree() {
		return '<' . $this->subName . '>';
	}
	function &process($res){
		$p = & $this->get($this->subName);
		$ret =& $p->process($res);
		$g =& $this->getGrammar();
		return $g->process($this->subName, $ret);
	}
}

class EregSymbol extends Parser {
	function EregSymbol($sym) {
		parent :: Parser();
                
                $escapeChar=$sym[0];
                $this->preg=$escapeChar."^".substr($sym,1);
		/*$bars = explode($escapeChar,$sym);
		$mods = array_pop($bars);
		array_shift($bars);               
                   $spaces='[\s\t\n]*';*
		$this->preg = $escapeChar.'^'.$spaces.'('.implode($escapeChar,$bars).')'.$spaces.$escapeChar.$mods;
                echo $this->preg."<br>";*/
                //$this->preg = $sym;
		$this->sym = $sym;
	}
	function parse($tks) {
	
		if (preg_match($this->preg, $tks->str, $matches)) {
                //    echo "<b>[".htmlentities($this->preg)."] ".htmlentities($tks->str)." (".strlen($matches[0]).")</b><br>";
			return array (ParseResult::match($matches[0]),new ParseInput(substr($tks->str,strlen($matches[0]))));
		} else {
                  //  echo "[".htmlentities($this->preg)."] ".htmlentities($tks->str)."<br>";
			$this->setError(array((string)strlen($tks->str)=>$this->sym));
			return array (ParseResult::fail(),$tks);
		}
	}
	function print_tree() {
		return $this->sym;
	}
}

class Symbol extends EregSymbol {
	function Symbol($ss) {
		parent :: EregSymbol('~'.preg_quote($ss).'~');
		$this->sym='"'.$ss.'"';
	}
}


/**************************************************************************************************************************************/
class Parser {
	function Parser() {}
	function &get($name) {
		$gr =& $this->getGrammar();
		return $gr->get($name);
	}
	function &getGrammar() {
		return $this->grammar;
	}
	function setParent(&$parent, &$grammar){
		$this->grammar =& $grammar;
		$this->setErrorHandler($parent);
	}
	function setErrorHandler(&$eh){
		$this->errorHandler[] =& $eh;
	}
	function &popErrorHandler(){
		$eh =& array_pop($this->errorHandler);
		return $eh;
	}
	function &process($result){return $result;}
	function setError($err){
		//echo "<br/>setting $err from ".get_class($this) . " to ".get_class($this->errorHandler[count($this->errorHandler)-1]);
		$this->errorHandler[count($this->errorHandler)-1]->setError($err);
	}
}

class ParseInput{
	var $partials = array();
	var $nts=array();
	function ParseInput($str){
		$this->str = $str;
	}
	function addPartial($name, $res){
		$this->partials[$name]=$res;
	}
	function getPartial($name){
		return @$this->partials[$name];
	}
	function pushNonTerminal($nt){
		array_push($this->nts,$nt);
	}
	function popNonTerminal($nt){
		array_pop($this->nts);
	}
	function includesNonTerminal($nt){
		return in_array($nt,$this->nts);
	}
	function redescendNonTerminal($nt){
		$this->rdnts[$nt] = $nt;
	}
	function shouldReDescend($nt){
		$b = isset($this->rdnts[$nt]);
		//unset($this->rdnts[$nt]);
		return $b;
	}
	function isBetterMatchThan($input){
		return strlen($this->str) < strlen($input->str);
	}
}

class ParseResult{
	function fail(){
		//return ParseResult::match(FALSE);
		$pr = new ParseResult();
		$pr->failed=true;
		return $pr;
	}
	function match($result){
		$pr = new ParseResult();
		$pr->match=$result;
		return $pr;
	}
	function lambda(){
		$pr = new ParseResult();
		$pr->lambda=true;
		$pr->match=null;
		return $pr;
	}
	function isLambda(){
		return isset($this->lambda);
	}
	function failed(){
		return isset($this->failed);
	}
}
?>