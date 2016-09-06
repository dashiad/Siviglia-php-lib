<?php


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

?>