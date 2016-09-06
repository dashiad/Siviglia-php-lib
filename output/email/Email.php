<?php
namespace lib\output\email;

include_once(LIBPATH."/output/email/swift/swift_required.php");


class Email
{
    function SendTemplate($to,$subject,$layoutDir,$layout,$params,$lang,$emailer,$cc,$from = null)
    {
        global $ALLOW_OUTBOUND_EMAILS;
        $ALLOW_OUTBOUND_EMAILS=true;
        if (!$ALLOW_OUTBOUND_EMAILS && !(isset($_SESSION['ALLOW_OUTBOUND_EMAILS']) && $_SESSION['ALLOW_OUTBOUND_EMAILS'])) {
            return;
        }

        include_once(LIBPATH."/output/html/templating/_T.php");


        $subject=\_T::t($subject,$lang);
        $layout=$layoutDir."/".$layout.".wid";
        global $EMAILERS;

        $email=$to;
        if(defined("DEVELOPMENT") && DEVELOPMENT==1)
        {
            if(defined("DEVELOPMENT_EMAIL")){
                $email=DEVELOPMENT_EMAIL;
                $cc=null;
            }
            else{
                return;
            }
        }
        if ($email=="info@percentil.com"){
            $emailer="contactoweb";
        }

        $this->Send($EMAILERS[$emailer],
            $layout,
            $lang,$subject,
            $params,$email,$email,$from);
        if($cc)
        {
            $this->Send($EMAILERS[$emailer],
                $layout,
                $lang,$subject,
                $params,$cc,$cc,$from);
        }
        else {
            if(defined("DEVELOPMENT_CC_EMAIL")) {
                $this->Send($EMAILERS[$emailer],
                    $layout,
                    $lang,$subject,
                    $params,DEVELOPMENT_CC_EMAIL,DEVELOPMENT_CC_EMAIL,$from);
            }
        }
    }

    function setDefaultWidgetPath($path)
    {
        include_once(LIBPATH."/output/html/templating/TemplateParser2.php");
        \CLayoutManager::setDefaultWidgetPath($path);
    }
	function Send($config,$template,$lang,$subject, $templateVars,$to,$toName = null,$from = null,$fromName = null,$fileAttachment = null,$modeSMTP = null,$templatePath = null)
	{

        /* DEFINICIONES DE EMAIL */
        /*global $EMAILERS;
        $EMAILERS=array(
        "default"=>array(
        "MAIL_DOMAIN"=>"percentil.com",
        "MAIL_USER_NAME"=>"Percentil",
        "MAIL_SERVER"=>"smtp.gmail.com",
        "MAIL_USER"=>"dashiad@gmail.com",
        "MAIL_PASSWD"=>"1witamin",
        "USE_SMTP_ENCRIPTION"=>"ssl",
        "SMTP_PORT"=>465,
        "MAIL_METHOD"=>"SMTP", //SMTP
        "MAIL_TYPE"=>1);
        )
        );
            "default"=>array("DOMAIN"=>"percentil.com",
                "MAIL_SERVER"=>"mail1.alojamientotecnico.com",
                "MAIL_USER"=>"info@percentil.com",
                "MAIL_PASSWD"=>"923723iuydww",
                "USE_SMTP_ENCRIPTION"=>"off",
                "SMTP_PORT"=>25,
                "MAIL_TYPE"=>3 // ??
            )
        );*/
        $configuration = $config;

        if(!$config["SMTP_PORT"])
            $config["SMTP_PORT"]="25";

        // Sending an e-mail can be of vital importance for the merchant, when his password is lost for example, so we must not die but do our best to send the e-mail
        if (!isset($from))
            $from = $config["MAIL_USER"];

        // $fromName is not that important, no need to die if it is not valid
        if (!isset($fromName))
            $fromName = $config["MAIL_USER_NAME"];

        if (!is_array($templateVars))
            $templateVars = array();
        \Registry::$registry["emailParams"]=$templateVars;

        \Swift_Preferences::getInstance()->setCharset('utf-8');
        switch($config["MAIL_METHOD"])
        {
            case 'SMTP':
            {
                $transport = \Swift_SmtpTransport::newInstance($config["MAIL_SERVER"], $config["SMTP_PORT"]?$config["SMTP_PORT"]:25);
                if($config['USE_SMTP_ENCRIPTION'])
                {
                    $transport->setEncryption($config['USE_SMTP_ENCRIPTION']);
                }
                if($config["MAIL_USER"])
                {
                    $transport->setUsername($config['MAIL_USER']);
                    $transport->setPassword($config['MAIL_PASSWD']);
                }

            }break;
            default:
                {
                    $transport = \Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
                }
        }

        $rendered=$this->renderTemplate($template,$templatePath,$lang);
        $subs=array("from","fromName","to","toName","subject");
        for($k=0;$k<count($subs);$k++)
        {
            $m=preg_match("/@@".strtoupper($subs[$k]).":([^@]*)@@/",$rendered,$result);
            if($m && count($result)>0)
            {
                ${$subs[$k]}=$result[1];
                $rendered=str_replace("@@".strtoupper($subs[$k]).":".$subject."@@","",$rendered);
            }
        }
        $mailer = \Swift_Mailer::newInstance($transport);
        //$logger = new \Swift_Plugins_Loggers_EchoLogger();
        //$mailer->registerPlugin(new \Swift_Plugins_LoggerPlugin($logger));

        $message = \Swift_Message::newInstance("=?UTF-8?B?".base64_encode($subject)."?=");
        $message->setFrom(array($from=>$fromName));


        if(!is_array($to))
        {
            if ($toName == null)
                $toName = $to;
            $to=array($to=>$toName);
        }
		/* Construct multiple recipients list if needed */
        $failed=array();
        $numSent=0;
        try
        {
			foreach ($to as $key => $addr)
			{
				$to_name = null;
				$addr = trim($addr);
				if (is_array($toName))
				{
					if ($toName && is_array($toName))
						$to_name = $toName[$key];
				}
				if ($to_name == null)
					$to_name = $addr;
                $message->setCharset('utf-8');
                $message->setTo(array($addr=>'=?UTF-8?B?'.base64_encode($to_name).'?='));
                // Lo pongo aqui para que sea posible personalizar
                $message->setBody($rendered,'text/html');

                if ($fileAttachment && isset($fileAttachment['content']) && isset($fileAttachment['name']) && isset($fileAttachment['mime']))
                {
                    $attach=\Swift_Attachment::newInstance($fileAttachment['content'], $fileAttachment['name'], $fileAttachment['mime']);
                    $message->attach($attach);
                }

                $numSent+=$mailer->send($message,$failed);
            }

          }catch(\Exception $e)
        {
//            $logger->dump();
        }
        return $numSent;

	}



    function renderTemplate($inputTemplate,$widgetPath,$lang)
    {
        include_once(LIBPATH."/output/html/templating/TemplateParser2.php");
        include_once(LIBPATH."/output/html/templating/TemplateHTMLParser.php");

        $oLParser=new \CLayoutHTMLParserManager();
        $oManager=new \CLayoutManager(PROJECTPATH."/..","html",$widgetPath,array("L"=>array("lang"=>$lang,"LANGPATH"=>PROJECTPATH."/../custom/lib/templating/lang/")),$lang);

        $dName=dirname($inputTemplate);
        $fName=basename($inputTemplate);
        $parts=str_replace(PROJECTPATH,"",$dName);
        $target=PROJECTPATH."/".$parts."/cache/".$fName."/".$lang."/";
        $definition=array("LAYOUT"=>$inputTemplate,
            "CACHE_SUFFIX"=>"php","TARGET"=>$target);
        ob_start();
        try{
            $oManager->renderLayout($definition,$oLParser,true);
        }
        catch(\Exception $e)
        {
            ob_get_clean();
            var_dump($e);
            exit();
        }
        $res=ob_get_clean();
        return $res;
    }
}
