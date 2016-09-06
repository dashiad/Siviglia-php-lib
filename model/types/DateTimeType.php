<?php namespace lib\model\types;

  class  DateTimeTypeException extends BaseTypeException{ 
      const ERR_START_YEAR=100;
      const ERR_END_YEAR=101;
      const ERR_WRONG_HOUR=102;
      const ERR_WRONG_SECOND=103;
      const ERR_STRICTLY_PAST=104;
      const ERR_STRICTLY_FUTURE=105;

      const TXT_START_YEAR="El año minimo permitido es %year%";
      const REQ_START_YEAR="STARTYEAR";

      const TXT_END_YEAR="El año maximo permitido es %year%";
      const REQ_END_YEAR="ENDYEAR";

      const TXT_STRICTLY_PAST="La fecha no puede ser futura";
      const REQ_STRICTLY_PAST="STRICTLYPAST";

      const TXT_STRICTLY_FUTURE="La fecha debe ser futura";
      const REQ_STRICTLY_FUTURE="STRICTLYFUTURE";

      const TXT_INVALID="Fecha no válida";
  }

  // Definitions of this class should always indicate:
  // "TIMEZONE"=>"UTC", "SERVER" or "CLIENT".If not set, it'll be "CLIENT", ie, no modification
  // Internal date representation IS ALWAYS IN THE FORMAT: YYYY-MM-DD HH:MM:SS.
  // DateTime types CANT BE UNIX timestamps, as there's no warranty about dates being > 1970-01-01
  // REMEMBER TO SET default-time-zone='UTC' in mysql if working with UTC dates
  // Importing timezones into mysql:mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql

  class DateTimeType extends BaseType
  {
      const DATE_FORMAT="Y-m-d H:i:s";
      const DATE_FORMAT_EU="d/m/Y H:i:s";
      function __construct($definition,$value=false)
      {
          BaseType::__construct($definition,$value);
      }
       function getValue()
      {
          if($this->valueSet)
            return $this->value; 
          if(isset($this->definition["DEFAULT"]))
          {
              if($this->definition["DEFAULT"]=="NOW")
              {
                  $this->setAsNow();
                  return $this->value;                  
              }
          }
          return null;          
      }
      function setAsNow()
      {
          $this->setValue(DateTimeType::getValueFromTimestamp());
      }

      function validate($value)
      {
          if (!$value && (!isset($this->definition["REQUIRED"]) || $this->definition["REQUIRED"]==false))
              return true;

          BaseType::validate($value);
          $asArr=$this->asArray($value);
          
          extract($asArr);
          if($day==0 && $month==0 && $year==0 && (!isset($this->definition["REQUIRED"]) || $this->definition["REQUIRED"]==false))
              return true;
          if(!checkdate($month,$day,$year))
          {
              throw new BaseTypeException(BaseTypeException::ERR_INVALID);
          }
          
          if($this->definition["STARTYEAR"])
          {
              if(intval($year)<intval($this->definition["STARTYEAR"]))
                  throw new DateTimeTypeException(DateTimeTypeException::ERR_START_YEAR,array("year"=>$this->definition["STARTYEAR"]));
          }
          if($this->definition["ENDYEAR"])
          {
              if(intval($year)>intval($this->definition["ENDYEAR"]))
                  throw new DateTimeTypeException(DateTimeTypeException::ERR_END_YEAR,array("year"=>$this->definition["ENDYEAR"]));
          }

          if(intval($hour)<0 || intval($hour)>23)
              throw new DateTimeTypeException(DateTimeTypeException::ERR_WRONG_HOUR);
          if(intval($minutes)<0 || intval($minutes)>59)
              throw new DateTimeTypeException(DateTimeTypeException::ERR_WRONG_MINUTE);
          if(intval($seconds)<0 || intval($seconds)>59)
              throw new DateTimeTypeException(DateTimeTypeException::ERR_WRONG_SECOND);
              
          $timestamp=$this->getTimestamp($value,$asArr);
          $curTimestamp=time();
          if($this->definition["STRICTLYPAST"] && $curTimestamp < $timestamp)
              throw new DateTimeTypeException(DateTimeTypeException::ERR_STRICTLY_PAST);
          if($this->definition["STRICTLYFUTURE"] && $curTimestamp > $timestamp)
              throw new DateTimeException(DateTimeTypeException::ERR_STRICTLY_FUTURE);

          BaseType::postValidate($value);

          return true;
      }
      function hasValue()
      {
          if(!$this->valueSet)
              return false;
          return $this->value!="" && $this->value!="0000-00-00 00:00:00";
      }
      function asArray($val=null)
      {
          if(!$val)$val=$this->value;

          $parts=explode(" ",$val);
          @list($result["year"],$result["month"],$result["day"])=explode("-",$parts[0]);
          if($parts[1])          
              @list($result["hour"],$result["minutes"],$result["seconds"])=explode(":",$parts[1]);
          else
              @list($result["hour"],$result["minutes"],$result["seconds"])=array(0,0,0);
          return $result;
      }

      static function getValueFromTimestamp($timestamp=null) {
        return date(DateTimeType::DATE_FORMAT, $timestamp?$timestamp:time()); 
      } 

      
      public function getTimestamp($value=null) {


          if($this->definition["TIMEZONE"]=="UTC")
          {
              $utcTz=new \DateTimeZone("UTC");
              $date=new \DateTime($value,$utcTz);
          }
          else
              $date = new \DateTime($value);

        $ret = $date->format("U"); 
        return ($ret < 0 ? 0 : $ret); 
      }

      public static function offsetToLocalDate($date,$offset) 
      { 
          return DateTimeType::offsetToTimezoneDate($date,$offset,date_default_timezone_get());
      }

      public static function offsetToUTCDate($date,$offset)
      {
          return DateTimeType::offsetToTimezoneDate($date,$offset,"UTC");
      }
      public static function offsetToTimezoneDate($date,$offset,$timezone)
      {          
          $gmtTz=new \DateTimeZone("UTC");
          // first, date + offset are converted to UTC.
          $srcDateTime=new \DateTime($date,$gmtTz);
         
          $secs=intval($srcDateTime->format('U'))+$offset;
          $srcDateTime->setTimestamp($secs);

          if($timezone!="UTC")
          {
              $localTz=new \DateTimeZone($timezone);
              $localTime=new \DateTime($date,$localTz);
              $localOffset=$localTz->getOffset($localTime);
              $srcDateTime->setTimestamp(intval($srcDateTime->format("U"))+$localOffset);
          }
          return $srcDateTime->format(DateTimeType::DATE_FORMAT);
      }
      public static function serverToUTCDate($date=null)
      {
          if(!$date)
              $date=date(DateTimeType::DATE_FORMAT);

          $localTz=new \DateTimeZone(date_default_timezone_get());
          $utcTz=new \DateTimeZone("UTC");
          $mvDateTime=new \DateTime($date,$localTz);
          $offset=$localTz->getOffset($mvDateTime);
          return date(DateTimeType::DATE_FORMAT,$mvDateTime->format("U")-$offset);
      }
      function getLocalizedString()
      {
          return date(DateTimeType::DATE_FORMAT_EU,$this->getTimestamp());
      }
  }

  class DateTimeTypeHTMLSerializer extends BaseTypeHTMLSerializer
  {
      function serialize($type)
      {               
          // Unserialize always will return UTC dates.
          
          switch($type->definition["TIMEZONE"])
          {
          case "SERVER":
              {
                  $val= DateTimeType::serverToUTCDate($type->getValue());
                  return $val;
              }break;
          case "UTC":
          case "CLIENT":
              {
                  return $type->getValue();

              }break;          
          }          
      }

      function unserialize($type,$value)
      {

          if($value==null)
              return;

          if($value=='')
          {

             return $type->clear();
          }

          // Unserialize a date coming from the client.
          // Obviously, basic syntax checking is done here.
          $parts=explode(" ",$value);
          @list($result["year"],$result["month"],$result["day"])=explode("-",$parts[0]);
          if($parts[1])          
              @list($result["hour"],$result["minutes"],$result["seconds"])=explode(":",$parts[1]);
          else
              @list($result["hour"],$result["minutes"],$result["seconds"])=array(0,0,0);

          
          if(!checkdate($result["month"],$result["day"],$result["year"]))
              throw new BaseTypeException(BaseTypeException::ERR_INVALID);
          
          switch($type->definition["TIMEZONE"])
          {
          case "SERVER":
              {                  
                  global $oCurrentUser;
                  $newVal=DateTimeType::offsetToLocalDate($value,$oCurrentUser->getUTCOffset());
                  $type->validate($newVal);
                  $type->setValue($newVal);
              }break;
          case "UTC":
              {
                  global $oCurrentUser;
                  $newVal=DateTimeType::offsetToUTCDate($value,$oCurrentUser->getUTCOffset());
                  $type->validate($newVal);
                  $type->setValue($newVal);
              }break;
          case "CLIENT":
          default:
              {
                  $type->validate($value);
                  $type->setValue($value);
              }
          }

      }

  }

