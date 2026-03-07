<?php

namespace Ladecadanse\Utils;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mailing
{
    private PHPMailer $mail;
    private string $errorMsg = "Message could not be sent. Mailer Error: ";

    /** Longueur maximale autorisée pour le sujet */
    private const MAX_SUBJECT_LENGTH = 255;

    /** Longueur maximale autorisée pour le corps du message */
    private const MAX_BODY_LENGTH = 10000;

    public function __construct()
    {
        $this->mail = new PHPMailer();
        $this->mail->SMTPDebug  = (int) EMAIL_AUTH_SMTPDEBUG;
        $this->mail->isSMTP();
        $this->mail->Host       = EMAIL_AUTH_HOST;
        $this->mail->SMTPAuth   = EMAIL_SMTPAUTH;
        $this->mail->Username   = EMAIL_AUTH_USERNAME;
        $this->mail->Password   = EMAIL_AUTH_PASSWORD;
        $this->mail->SMTPSecure = EMAIL_AUTH_SMTPSECURE;
        $this->mail->Port       = (int) EMAIL_AUTH_PORT;
        $this->mail->CharSet    = 'utf-8';
    }

    /**
     * Envoie un mail à l'administrateur du site.
     *
     * @param string      $title        Sujet du mail (données potentiellement issues d'un formulaire)
     * @param string      $body         Corps du mail (données potentiellement issues d'un formulaire)
     * @param string|null $replyToEmail Adresse de réponse optionnelle
     */
    public function toAdmin(string $title, string $body, ?string $replyToEmail = null): bool
    {
        $this->mail->From     = EMAIL_SITE;
        $this->mail->FromName = EMAIL_SITE_NAME;

        if (!empty($replyToEmail)) {
            if (!PHPMailer::validateAddress($replyToEmail)) {
                error_log("Mailing::toAdmin — adresse replyTo invalide : " . $replyToEmail);
                return false;
            }
            $this->mail->addReplyTo($replyToEmail);
        }

        $this->mail->Subject = "[La décadanse] " . $this->sanitizeHeader($title);
        $this->mail->Body    = $this->sanitizeBody($body);
        $this->mail->IsHTML(false);
        $this->mail->AddAddress(EMAIL_ADMIN, EMAIL_ADMIN_NAME);

        return $this->send();
    }

    /**
     * Envoie un mail à un utilisateur.
     *
     * @param string      $to              Adresse email du destinataire
     * @param string      $title           Sujet du mail
     * @param string      $body            Corps du mail
     * @param array       $replyTo         Tableau optionnel ['email' => ..., 'name' => ...]
     * @param string|null $attachementPath Chemin absolu vers une pièce jointe
     */
    public function toUser(string $to, string $title, string $body, array $replyTo = [], ?string $attachementPath = null): bool
    {
        if (!PHPMailer::validateAddress($to)) {
            error_log("Mailing::toUser — adresse destinataire invalide : " . $to);
            return false;
        }

        $this->mail->From     = EMAIL_SITE;
        $this->mail->FromName = EMAIL_SITE_NAME;

        if (!empty($replyTo)) {
            if (!PHPMailer::validateAddress($replyTo['email'])) {
                error_log("Mailing::toUser — adresse replyTo invalide : " . $replyTo['email']);
                return false;
            }
            $this->mail->addReplyTo(
                $replyTo['email'],
                $this->sanitizeHeader($replyTo['name'] ?? '')
            );
        }

        $this->mail->Subject = $this->sanitizeHeader($title);
        $this->mail->Body    = $this->sanitizeBody($body);
        $this->mail->IsHTML(false);
        $this->mail->AddAddress($to, "");

        if (!empty($attachementPath)) {
            $resolvedPath = $this->resolveAttachmentPath($attachementPath);
            if ($resolvedPath === null) {
                error_log("Mailing::toUser — pièce jointe invalide ou hors répertoire autorisé : " . $attachementPath);
                return false;
            }
            $this->mail->addAttachment($resolvedPath);
        }

        return $this->send();
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Nettoie une valeur destinée à un en-tête SMTP (Subject, nom…).
     * Supprime les sauts de ligne qui permettraient une injection d'en-têtes,
     * et tronque à MAX_SUBJECT_LENGTH caractères.
     */
    private function sanitizeHeader(string $value): string
    {
        $value = preg_replace('/[\r\n\0]+/', '', $value) ?? '';
        return mb_substr(trim($value), 0, self::MAX_SUBJECT_LENGTH);
    }

    /**
     * Nettoie le corps d'un message en texte brut issu d'un formulaire public.
     * Supprime les balises HTML/PHP et tronque à MAX_BODY_LENGTH caractères.
     */
    private function sanitizeBody(string $body): string
    {
        $body = strip_tags($body);
        return mb_substr(trim($body), 0, self::MAX_BODY_LENGTH);
    }

    /**
     * Vérifie et résout le chemin d'une pièce jointe.
     * Retourne le chemin réel si valide, null sinon.
     *
     * Pour restreindre les pièces jointes à un répertoire précis,
     * définir la constante ALLOWED_ATTACH_DIR (ex: '/var/www/uploads').
     */
    private function resolveAttachmentPath(string $path): ?string
    {
        $realPath = realpath($path);

        if ($realPath === false || !is_file($realPath)) {
            return null;
        }

        if (defined('ALLOWED_ATTACH_DIR')) {
            $allowedDir = rtrim((string) ALLOWED_ATTACH_DIR, DIRECTORY_SEPARATOR);
            if (!str_starts_with($realPath, $allowedDir . DIRECTORY_SEPARATOR)) {
                return null;
            }
        }

        return $realPath;
    }

    /**
     * Centralise l'envoi et la gestion des erreurs.
     * Les erreurs sont loggées côté serveur, jamais exposées à l'utilisateur.
     */
    private function send(): bool
    {
        try {
            if (!$this->mail->send()) {
                error_log($this->errorMsg . $this->mail->ErrorInfo);
                return false;
            }
        } catch (Exception) {
            error_log($this->errorMsg . $this->mail->ErrorInfo);
            return false;
        } finally {
            // Réinitialise les destinataires et pièces jointes pour une éventuelle
            // réutilisation de l'instance dans la même requête
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            $this->mail->clearAttachments();
        }

        return true;
    }
}
