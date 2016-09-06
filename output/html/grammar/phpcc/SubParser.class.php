<?php

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

?>