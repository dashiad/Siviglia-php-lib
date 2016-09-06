<?php

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

require_once 'AltParser.class.php';
require_once 'Grammar.class.php';
require_once 'ListParser.class.php';
require_once 'MaybeParser.class.php';
require_once 'MultiParser.class.php';
require_once 'SeqParser.class.php';
require_once 'SubParser.class.php';
require_once 'Symbol.class.php';

?>