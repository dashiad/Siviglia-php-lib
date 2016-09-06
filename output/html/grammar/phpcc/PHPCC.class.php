<?php

require_once 'Parser.class.php';
//require_once 'PHPGrammar.class.php';
require_once 'FunctionObject.class.php';

class PHPCC {

    function &createGrammar($grammar) {
    	$g =& PHPCC::ccGrammar();
    	$g->addPointCuts(array(
    			'alternative'=>new FunctionObject($n=null, 'PHPCC::createAlternative'),
    			'maybe'=>new FunctionObject($n=null, 'PHPCC::createMaybe'),
    			'list'=>new FunctionObject($n=null, 'PHPCC::createList'),
    			'sequence'=>new FunctionObject($n=null, 'PHPCC::createSequence'),
    			'multiparser'=>new FunctionObject($n=null, 'PHPCC::createMultiParser'),
    			'grammar'=>new FunctionObject($n=null, 'PHPCC::createNTS'),
				'symbol'=>new FunctionObject($n=null, 'PHPCC::createSymbol'),
				'ereg'=>new FunctionObject($n=null, 'PHPCC::createEreg'),
				'subparser'=>new FunctionObject($n=null, 'PHPCC::createSubparser'),
		));
		return $g->compile($grammar);
    }
    function printCcGrammar(){
    	$g =& PHPCC::ccGrammar();
    	return $g->print_tree();
    }
    function &ccGrammar(){
    	global $grammars;
    	if (!isset($grammars['ccGrammar'])){
    	$grammars['ccGrammar']=& new Grammar(array(
    		'root'=>'grammar',
    		'nt'=>array(
    			'alternative'=>new ListParser(new SeqParser(array(new MaybeParser(
    							new SeqParser(array(new EregSymbol("~[a-zA-Z_][a-zA-Z_0-9]*~"), new Symbol('=>')))),new SubParser('sequence'))),new EregSymbol('~\|\||\|~')),
    			'maybe'=>new SeqParser(array(new Symbol('['),new SubParser('alternative'),new Symbol(']'))),
    			'list'=>new SeqParser(array(new Symbol('{'),new SubParser('alternative'),new Symbol(';'),new SubParser('alternative'),new Symbol('}'))),
    			'sequence'=>new MultiParser(new SeqParser(array(new MaybeParser(
    							new SeqParser(array(new EregSymbol("~[a-zA-Z_][a-zA-Z_0-9]*~"), new Symbol('->')))),
    							new AltParser(array(
    								new SubParser('list'),
    								new SubParser('maybe'),
    								new SubParser('symbol'),
    								new SubParser('ereg'),
    								new SubParser('multiparser'),
    							))))),
    			'multiparser'=>new SeqParser(array('name'=>
    					new AltParser(array('alt'=>new SeqParser(array(new Symbol('('),new SubParser('alternative'),new Symbol(')'))),
    					new SubParser('subparser'))),
					'iterator'=>new MaybeParser(new EregSymbol('~\*|\+~')))),
    			'subparser'=>new SeqParser(array(new Symbol('<'),'name'=>new EregSymbol("~[a-zA-Z_][a-zA-Z_0-9]*~"),new Symbol('>'),)),
    			'symbol'=>new EregSymbol('~"[^"]+"~'),
    			'ereg'=>new EregSymbol('/~[^~]+~\w*/'),
    			'non-terminal'=>new SeqParser(array(new EregSymbol("~[a-zA-Z_][a-zA-Z_0-9]*~"), new Symbol('::='), new SubParser('alternative'), new Symbol('.'))),
    			'grammar'=>new SeqParser(array(new Symbol('<'),new EregSymbol("~[a-zA-Z_][a-zA-Z_0-9]*~"), new Symbol('('),new MultiParser(new SubParser('non-terminal')), new Symbol(')'),new Symbol('>')))
    		)));
    	}
    	return $grammars['ccGrammar'];
    }

    function &createSequence(&$params){
	    if (count($params)==1 && $params[0][0]==null) {
	    	return $params[0][1]['result'];
	    }
	    $ks = array_keys($params);
	    for($i=0;$i<count($params);$i++){
	    	$param = $params[$ks[$i]];
	    	if ($param[0]===null) {
	    		$ret [] = $param[1]['result'];
	    	} else {
	    		$ret [$param[0][0]] = $param[1]['result'];
	    	}
	    }
	    $seq =& new SeqParser($ret);
	    return $seq;
    }
    function &createAlternative(&$params){
	    if (count($params)==1 && $params[0][0]==null) return $params[0][1];
	    $ks = array_keys($params);
	    $backtrace = false;
	    for($i=0;$i<count($params);$i+=2){
	    	$param = $params[$ks[$i]];
	    	if ($param[0]===null) {
	    		$ret [] = $param[1];
	    	} else {
	    		$ret [$param[0][0]] = $param[1];
	    	}
	    	$backtrace = $backtrace || @$params[$ks[$i+1]]=='||';
	    }
	    $alt =& new AltParser($ret, $backtrace);
	    return $alt;
    }
    function &createNTS(&$params){
    	foreach($params[3] as $param) {
    		$ret [$param[0]]=$param[2];
    	}
    	$g =& new Grammar(array('root'=>$params[1],'nt'=>&$ret));
    	return $g;
    }
    function &createList(&$elems){
    	$lp =& new ListParser($elems[1],$elems[3]);
    	return $lp;
    }
    function &createSymbol(&$sym){
    	$sym = substr(substr($sym, 1),0, -1);
    	$s =& new Symbol($sym);
    	return $s;
    }
    function &createEreg(&$sym){
    	$s =& new EregSymbol($sym);
    	return $s;
    }
    function &createMultiParser(&$params){
    	if ($params['name']['selector']==='alt'){
    		$sub =&$params['name']['result'][1];
    	} else {
    		$sub =&$params['name']['result'];
    	}
    	if ($params['iterator']=='*'){
    		$s =& new MultiParser($sub);
    	} else if($params['iterator']=='+') {
	    	$s =& new MultiOneParser($sub);
    	} else {
    		$s =& $sub;
    	}
    	return $s;
    }
    function &createSubparser(&$sp){
    	$s =& new SubParser($sp['name']);
    	return $s;
    }
    function &createMaybe(&$sp){
    	$s =& new MaybeParser($sp[1]);
    	return $s;
    }
}

?>