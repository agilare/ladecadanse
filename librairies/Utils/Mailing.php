<?php

namespace Ladecadanse\Utils;

use PHPMailer\PHPMailer\PHPMailer;

class Mailing {
    
    private $mail;
    
     public function __construct()
     {
        $this->mail = new PHPMailer(); 
        $this->mail->SMTPDebug = (int)EMAIL_AUTH_SMTPDEBUG;
        $this->mail->isSMTP();
        $this->mail->Host = EMAIL_AUTH_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = EMAIL_AUTH_USERNAME;
        $this->mail->Password = EMAIL_AUTH_PASSWORD;
        $this->mail->SMTPSecure = EMAIL_AUTH_SMTPSECURE;
        $this->mail->Port = (int)EMAIL_AUTH_PORT;        
        $this->mail->CharSet = 'utf-8';    
     }
     
    public function toAdmin(string $title, string $body, ?string $from): bool
    {
        $this->mail->From     = EMAIL_SITE;
        $this->mail->FromName = EMAIL_SITE_NAME;     
        if (!empty($from))
        {
            $this->mail->From     = $from;
            $this->mail->FromName = '';                
        }
          
        $this->mail->Subject 	= "[La décadanse] ".$title;
        $this->mail->Body = $body;
        $this->mail->AddAddress(EMAIL_ADMIN, EMAIL_ADMIN_NAME);
        try {        
            $this->mail->Send();
            return true;
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}";
            return false;
        }              
    } 
    
    public function toUser(string $to, string $title, string $body, array $replyTo = [], ?string $attachementPath = null): bool
    {
        $this->mail->From     = EMAIL_SITE;
        $this->mail->FromName = EMAIL_SITE_NAME;
        
        if (!empty($replyTo))
        {
            $this->mail->addReplyTo($replyTo['email'], $replyTo['name']);
        }
        
        $this->mail->Subject = $title;
        $this->mail->Body = $body;
        
        if (!empty($attachementPath))
        {
            $this->mail->addAttachment($attachementPath);
        }
        
        $this->mail->AddAddress($to, "");
        try {        
            $this->mail->Send();
            return true;
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}";
            return false;
        }  
    } 
}