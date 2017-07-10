<?php
global $profileInfo;

class Grammar {
	var $pointcuts = array();
	var $errors = array();
	function Grammar($params) {
		$this->params = & $params;
        $this->pFail=new ParseFail();
        $this->pLambda=new ParseLambda();

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
	function &getRoot() {
		$root =  new SubParser($this->params['root']);
		$root->setParent($this,$this);
		return $root;
	}
	function &process($name, &$data){
		$p =& $this->pointcuts[$name];
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
              
		if (preg_match('~^[\s\t\n]*$~',$this->res->input->str)){
			return $root->process($this->res->match);//$this->process($this->params['root'],$res1);
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

$offset="";
class AltParser extends Parser {
	var $errorBuffer = array();
	function AltParser($children, $backtrack=false) {
		$this->backtrack = $backtrack;
		$this->children =& $children;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		foreach (array_keys($this->children) as $k) {
            if(is_object($this->children[$k]))
			$this->children[$k]->setParent($this, $grammar);
		}
	}
	function parse($tks) {
        global $offset;
        $accepted="";
        $offset.="&nbsp;&nbsp;&nbsp;&nbsp;";
        //echo "<br>".$offset."<b style=\"font-size:24px\">AltParser</b><br>";
        //echo $offset."String: ".$tks->str."<br>";
		//$return = array($this->grammar->pFail, $tks);
        $return=$this->grammar->pFail;
        $curInput=$tks;
        $keys=array_keys($this->children);
		foreach ($keys as $k) {
            //echo $offset."Key:".$k." --- ";
			$c = & $this->children[$k];
            if(is_string($c))
            {
                $res=null;
                if(substr($c,0,1)=="~")
                {
                    if (preg_match("~^".substr($c,1), $curInput->str, $matches)) {
                        //    echo "<b>[".htmlentities($this->preg)."] ".htmlentities($tks->str)." (".strlen($matches[0]).")</b><br>";
                        $res=new ParseResult($matches[0],new ParseInput(substr($curInput->str,strlen($matches[0]))));
                    }
                }
                else
                {
                    $f=substr($curInput->str,0,strlen($c));
                    if ($f==$c)
                        $res=new ParseResult($c,new ParseInput(substr($curInput->str,strlen($c))));

                }
                if(!$res)
                    $res=$this->grammar->pFail;
            }
            else
            {
                //echo $offset."<br>".$offset."[SUBPARSING]<br>";
			    $res = $c->parse($tks);
                //echo $offset."<br>".$offset."[END SUBPARSE]<br>";

            }
			if (!$res->failed() && !$res->isLambda()) {
				//if ($res[0]->isLambda()) print_backtrace(strtolower(get_class($c). ' '.htmlentities($this->print_tree()));

				//if ($res[1]->isBetterMatchThan($return[1])) {
                if($res->input->len < $curInput->len)
                {
                  //  echo $offset."<b>Accepted,better match:$k</b><br>";
                    $return=new ParseResult(array("selector"=>$k,"result"=>$res->match),$res->input);
                    $accepted=$k;
				}
                /*else
                    echo $offset."Accepted,worse match:$k<br>";
                */
			}
            /*else
                echo $offset."Not accepted:$k<br>";*/
		}
		if ($return->failed()){
            //echo $offset." <b>Finally -----> Not accepted (".implode(",",$keys)."</b><br>";
			parent::setError($this->errorBuffer);
		}
        /*else
            echo $offset."<b>Finally: ACCEPTED: $k (".$tks->str.")</b><br>";
        $offset=substr($offset,24);*/
		$this->errorBuffer=array();

		return $return;
	}
	function &process($result) {

		if (!$this->children[$result['selector']])
        {
            echo 'wrong alternative:';var_dump($result);
        }
        if(is_object($this->children[$result['selector']]))
		    $rets =&$this->children[$result['selector']]->process($result['result']);
        else
        {
            $rets=$result["result"];
        }
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


       	return $ret;
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
	function ListParser( $parser, & $separator) {
		$this->sep = $separator;
		$this->parser = $parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		$this->sep->setParent($this, $grammar);
		$this->parser->setParent($this, $grammar);
	}
	function parse($tks) {
		/*first, we parse the list*/
		$mp =  new MultiParser(new SeqParser(array (
			$this->parser,
			$this->sep
		)));
		$mp->setParent($this, $this->grammar);
		$res = $mp->parse($tks);
		/* then, we parse again, in the tail of the list */
		$res1 = $this->parser->parse($res->input);
		/* if the tail failed, the parse failed */
		if ($res1->failed() || $res1->isLambda()) {
			parent::setError($this->errorBuffer);
            $res1=$this->grammar->pFail;
            return $res1;
		}
		/* we collect the last parsed token, in the first position of the subarray (as all the other ones) */
		$res->match[] = array (
			$res1->match
		);
        $res1=new ParseResult($res->match,$res1->input);

        return $res1;
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
			$ret []=$this->parser->process($res[$i][0]);
			if(isset($res[$i][1]))
				$ret []=$this->sep->process($res[$i][1]);
		}

		return $ret;
	}

}


class MaybeParser extends Parser {
	function MaybeParser($parser) {
		$this->parser = $parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
        if(is_object($this->parser))
		    $this->parser->setParent($this, $grammar);
	}
	function parse($tks) {

        $c=$this->parser;
        if(is_string($c))
        {
            $res=null;
            if($c[0]=="~")
            {
                if (preg_match("~^".substr($c,1), $tks->str, $matches)) {
                    //    echo "<b>[".htmlentities($this->preg)."] ".htmlentities($tks->str)." (".strlen($matches[0]).")</b><br>";
                    return new ParseResult($matches[0],new ParseInput(substr($tks->str,strlen($matches[0]))));
                }
            }
            else
            {
                $l=strlen($c);
                $f=substr($tks->str,0,$l);
                if ($f==$c)
                   return new ParseResult($c,new ParseInput(substr($tks->str,$l)));
            }
             return new ParseLambda($tks);
        }
        else
		    $res = $this->parser->parse($tks);
		if ($res->failed())
			return new ParseLambda($tks);
		return $res;
	}
	function print_tree() {
		return  '['.
		$this->parser->print_tree().
		']';
	}
	function &process($result) {

        if(!is_object($this->parser))
            return $result;
		if ($result!=null) $r1=$this->parser->process($result); else {$r1= $result;}

        return $r1;
	}
	function setError($err){}
}



class MultiParser extends Parser {
	function MultiParser( $parser) {

		$this->parser =$parser;
	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		$this->parser->setParent($this, $grammar);
	}
	function parse($tks) {

		$res = $this->parser->parse($tks);
		$ret = array();
        $lastInput=null;
		while ((!$res->failed()) && !$res->isLambda()) {
			$ret[] = $res->match;
            $lastInput=$res->input;
			$res = $this->parser->parse($res->input);
		}
		if (empty($ret))
			return new ParseLambda($tks);
		return new ParseResult($ret,$lastInput);

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


class SeqParser extends Parser {
	function SeqParser($children) {
		if (!is_array($children)) {
			print_r($children);
			exit;
		}
        $this->children =& $children;
        $keys=array_keys($this->children);
        $this->lens=array();
        foreach ($keys as $k) {
            if(is_string($this->children[$k]))
            {
                if($this->children[$k][0]=='~')
                {
                    $this->children[$k]="~^".substr($this->children[$k],1);
                }
                $this->lens[$k]=strlen($this->children[$k]);
            }
        }


	}
	function setParent(&$parent, &$grammar){
		parent :: setParent($parent, $grammar);
		foreach (array_keys($this->children) as $k) {
            if(is_object($this->children[$k]))
			$this->children[$k]->setParent($this, $grammar);
		}
	}

	function &process($result) {

		foreach (array_keys($this->children) as $k) {
            if(is_object($this->children[$k]))
			    $rets [$k]=&$this->children[$k]->process($result[$k]);
            else
                $rets[$k]=$result[$k];
		}

		return $rets;
	}
	function parse($tks) {

		$curInput=$tks;
		$ret = array();
        $keys=array_keys($this->children);
		foreach ($keys as $k) {
            $ob=$this->children[$k];
            if(is_string($ob))
            {
                $res=null;
                if($ob[0]=="~")
                {
                    if(preg_match($ob, $curInput->str, $matches))
                        $res=new ParseResult($matches[0],new ParseInput(substr($curInput->str,strlen($matches[0]))));
                }
                else
                {
                    $l=$this->lens[$k];
                    if (substr($curInput->str,0,$l)==$ob)
                        $res=new ParseResult($ob,new ParseInput(substr($curInput->str,$l)));
                }
                if(!$res)
                {
                    $r1=$this->grammar->pFail;

                    return $r1;
                }

            }
            else
			    $res = $this->children[$k]->parse($curInput);

			if ($res->failed())
				return $this->grammar->pFail;

            $curInput= $res->input;
			$ret[$k] = $res->match;
		}
		$r1=new ParseResult($ret,$curInput);

        return $r1;
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
        if(!is_string($name))
        {
            $h=11;
            $q=22;
        }
		$this->subName = $name;

	}
    function setParent(&$parent, &$grammar){
        $this->grammar =& $grammar;
        $this->def=$this->grammar->get($this->subName);
        $this->setErrorHandler($parent);
    }
	function parse($tks) {
        $s=$this->subName;

		if (($res = @$tks->partials[$s])===null){
			if (in_array($s,$tks->nts)){
				$tks->redescendNonTerminal($s);
				return $this->grammar->pFail;
			}
			$g=$this->grammar;
            $p= $this->def;
			if ($p===null) {print_backtrace_and_exit($s .' does not exist');}
            array_push($tks->nts,$s);
			//$tks->pushNonTerminal($s);
			$p->setErrorHandler($this);
			$res = $p->parse($tks);
            array_pop($tks->nts);

			$next = $res;
			$str = $tks->str;
			$tks->str = '';
			$parts = $tks->partials;
			$tks->partials = array();
			while (isset($tks->rdnts[$s]) && !$next->failed() && !$next->isLambda()){
                $tks->partials[$s]=$res;
				$res =$next;
				$next = $p->parse($tks);
			}
			$tks->str = $str;
			$tks->partials = $parts ;
			$tks->addPartial($s, $res);
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


		$ret = $this->def->process($res);
		$r1=$this->grammar->process($this->subName, $ret);

        return $r1;
	}
}

class EregSymbol extends Parser {
	function EregSymbol($sym) {
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
			$r1=new ParseResult($matches[0],new ParseInput(substr($tks->str,strlen($matches[0]))));
		} else {
                  //  echo "[".htmlentities($this->preg)."] ".htmlentities($tks->str)."<br>";
			$this->setError(array((string)($tks->len)=>$this->sym));
			$r1=$this->grammar->pFail;
		}

        return $r1;
	}
	function print_tree() {
		return $this->sym;
	}
    function &process($res)
    {



        return $res;
    }
}

class Symbol extends EregSymbol {
	function Symbol($ss) {
        $this->mys=$ss;
        $this->slen=strlen($ss);
		//parent :: EregSymbol('~'.preg_quote($ss).'~');
		$this->sym='"'.$ss.'"';

	}
    function parse($tks)
    {

        $f=substr($tks->str,0,$this->slen);
        if ($f==$this->mys) {
            $r1=new ParseResult($this->mys,new ParseInput(substr($tks->str,$this->slen)));
        } else {
            //  echo "[".htmlentities($this->preg)."] ".htmlentities($tks->str)."<br>";
            $this->setError(array((string)($tks->len)=>$this->sym));
            $r1=$this->grammar->pFail;
        }


        return $r1;
    }
    function &process($res)
    {
        return $res;
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
		$eh = array_pop($this->errorHandler);
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
        $this->len=strlen($str);
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
		return $this->len < $input->len;
	}
}

class ParseFail
{
    function isLambda()
    {
        return false;
    }
    function failed()
    {
        return true;
    }
}

class ParseLambda
{
    function __construct($input=null)
    {
        $this->match=null;
        $this->input=$input;
    }
    function isLambda(){

        return true;
    }
    function failed(){
        return false;
    }
}

class ParseResult{
    function __construct($match=null,$input=null)
    {
        $this->match=$match;
        $this->input=$input;
    }
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
		return false;
	}
	function failed(){
		return false;
	}
}
?>
