<?php

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
?>