<?php

namespace Ladecadanse\Utils;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mailing {

    private $mail;
    private $errorMsg = "Message could not be sent. Mailer Error: ";

    public function __construct()
     {
        $this->mail = new PHPMailer();
        $this->mail->SMTPDebug = (int) EMAIL_AUTH_SMTPDEBUG;
        $this->mail->isSMTP();
        $this->mail->Host = EMAIL_AUTH_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = EMAIL_AUTH_USERNAME;
        $this->mail->Password = EMAIL_AUTH_PASSWORD;
        $this->mail->SMTPSecure = EMAIL_AUTH_SMTPSECURE;
        $this->mail->Port = (int) EMAIL_AUTH_PORT;
        $this->mail->CharSet = 'utf-8';
    }

    public function toAdmin(string $title, string $body, ?string $replyToEmail): bool
    {
        $this->mail->From = EMAIL_SITE;
        $this->mail->FromName = EMAIL_SITE_NAME;
        if (!empty($replyToEmail))
        {
            $this->mail->addReplyTo($replyToEmail);
        }

        $this->mail->Subject 	= "[La décadanse] ".$title;
        $this->mail->Body = $body;
        $this->mail->AddAddress(EMAIL_ADMIN, EMAIL_ADMIN_NAME);
        try
        {
            if (!$this->mail->Send()) {
                echo $this->errorMsg . $this->mail->ErrorInfo;
                return false;
            }
        } catch (Exception)
        {
            echo $this->errorMsg . $this->mail->ErrorInfo;
            return false;
        }

        return true;
    }

    public function toUser(string $to, string $title, string $body, array $replyTo = [], ?string $attachementPath = null): bool
    {
        $this->mail->From = EMAIL_SITE;
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
            if (!$this->mail->Send()) {
                echo $this->errorMsg . $this->mail->ErrorInfo;
                return false;
            }
        } catch (Exception)
        {
            echo $this->errorMsg . $this->mail->ErrorInfo;
            return false;
        }

        return true;
    }
}