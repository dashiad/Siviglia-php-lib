<?php

class EregSymbol extends Parser {
	function EregSymbol($sym) {
		parent :: Parser();
        $escapeChar=$sym[0];
		$bars = explode($escapeChar,$sym);
		$mods = array_pop($bars);
		array_shift($bars);
		$spaces = '[\s\t\n]*';
		$this->preg = $escapeChar.'^'.$spaces.'('.implode($escapeChar,$bars).')'.$spaces.$escapeChar.$mods;
		$this->sym = $sym;
	}
	function parse($tks) {
		$spaces = '[\s\t\n]*';
		if (preg_match($this->preg, $tks->str, $matches)) {
			return array (ParseResult::match($matches[1]),new ParseInput(substr($tks->str,strlen($matches[0]))));
		} else {
			$this->setError(array((string)strlen(preg_replace('/^'.$spaces.'/','',$tks->str))=>$this->sym));
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
?>