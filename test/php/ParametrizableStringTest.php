<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 14:56
 */

namespace lib\test\php;
use \lib\php\ParametrizableString;
use \lib\php\ParametrizableStringException;

class ParametrizableStringTest extends \PHPUnit_Framework_TestCase {
    function testNullTransform()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("aaa",array()),
                            "aaa");
    }
    function testSingleParam()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("aa [%bb%] cc",array("bb"=>"11")),"aa 11 cc");
    }
    function testSingleCompositeParam()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("aa [%bb%] [%cc: aa {%cc%} bb%] bb",array("bb"=>"11","cc"=>"22")),
            "aa 11  aa 22 bb bb");
    }
    function testSingleNegatedCompositeParam()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("aa [%bb%] [%!cc: aa bb%] bb",array("bb"=>"11","cc"=>"22")),
            "aa 11  bb"
        );
    }
    function testCompositedNegatedCompositeParam()
    {
        $cad4="[%!cc: aa bb%][%cc: aa {%cc%} bb%]";
        $a=(ParametrizableString::getParametrizedString($cad4,array("bb"=>"11","cc"=>"22"))
         == " aa 22 bb");
        $b=ParametrizableString::getParametrizedString($cad4,array("bb"=>"11"))==" aa bb";

        $this->assertEquals($a && $b,true);
    }
    function testSimpleRequiredParam()
    {
        $this->setExpectedException('\lib\php\ParametrizableStringException',
            '',
            ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM);
        $cad4="[%hh%] [%bb%]";
        $res=ParametrizableString::getParametrizedString($cad4,array("bb"=>"11","cc"=>"22"));
    }
    function testSimpleCondition()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh is array:ee%]",array("hh"=>array(1,2))),"ee");
    }
    function testSimpleNegatedCondition()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%!hh is array:ee%]",array("hh"=>1)),"ee");
    }
    function testCompositedNegatedCondition()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%!hh is array,c == 1:ee%][%c > 1:qq%]",array("hh"=>1,"c"=>4)),"qq");
    }
    function testDoubleCondition()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh is array,c == 1:ee%][%c == 1:qq%]",array("hh"=>1,"c"=>1)),"qq");
    }
    function testSimpleTransform()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh:rr{%e:ucfirst%}%]",array("hh"=>1,"e"=>"aaa")),"rrAaa");
    }
    function testCompositeTransform()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh:rr{%e:ucfirst|str_repeat @@ 2%}%]",array("hh"=>1,"e"=>"aaa")),"rrAaaAaa");
    }
    function testDefaultValue()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh:rr{%e:default 'hola'|ucfirst|str_repeat @@ 2%}%]",array("hh"=>1)),"rrHolaHola");
    }
    function testDobleDefault()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh:rr{%e:default 'hola'|ucfirst|str_repeat @@ 2%}%][%hh:rr{%e:default 'hola'|ucfirst|str_repeat @@ 2%}%]",array("hh"=>1)),"rrHolaHolarrHolaHola");
    }
    function testComparisonConditions()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh > 1:{%e:default 'hola'}%][%hh < 1:qq%][%hh == 1:aa%][%hh != 0:bb%]",array("hh"=>1)),"aabb");
    }
    function testNoExceptionUse()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh:aa%] ww",array())," ww");
    }
    function testExceptionUse()
    {
        $this->setExpectedException('\lib\php\ParametrizableStringException',
            '',
            ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM);
        ParametrizableString::getParametrizedString("[%hh > 1:aa%] ww",array());
    }
    function testExceptionUseMultiple()
    {
        $this->setExpectedException('\lib\php\ParametrizableStringException',
            '',
            ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM);
        ParametrizableString::getParametrizedString("[%hh > 1,c == 2:aa%] ww",array("hh"=>2));
    }
    function testNegatedExceptionUseMultiple()
    {
        $this->assertEquals(ParametrizableString::getParametrizedString("[%hh > 1,!c:aa%] ww",array("hh"=>2)),"aa ww");
    }
    function testNotDefinedVariable()
    {
        $this->setExpectedException('\lib\php\ParametrizableStringException',
            '',
            ParametrizableStringException::ERR_MISSING_REQUIRED_VALUE);
        ParametrizableString::getParametrizedString("[%hh > 1:{%aa%}%] ww",array("hh"=>2));
    }
}
