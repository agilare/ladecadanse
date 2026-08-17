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

    /** Préfixe du sujet des copies envoyées à l'admin quand EMAIL_COPY_TO_ADMIN est actif */
    private const COPY_SUBJECT_PREFIX = '[COPY] ';

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

        $resolvedPath = null;
        if (!empty($attachementPath)) {
            $resolvedPath = $this->resolveAttachmentPath($attachementPath);
            if ($resolvedPath === null) {
                error_log("Mailing::toUser — pièce jointe invalide ou hors répertoire autorisé : " . $attachementPath);
                return false;
            }
            $this->mail->addAttachment($resolvedPath);
        }

        $sent = $this->send();

        $this->copyToAdmin($to, $replyTo, $resolvedPath);

        return $sent;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Renvoie à EMAIL_ADMIN une copie du mail qui vient d'être adressé à un utilisateur,
     * avec un sujet préfixé COPY_SUBJECT_PREFIX. Activé par EMAIL_COPY_TO_ADMIN, destiné à
     * de courtes périodes de monitoring de la circulation des messages et de leur rendu.
     *
     * Le corps n'est pas modifié pour que la copie rende exactement comme l'original ;
     * le destinataire d'origine est reporté dans l'en-tête X-Original-To.
     *
     * Doit être appelée après send(), qui a déjà réinitialisé destinataires,
     * reply-to et pièces jointes. L'échec d'une copie n'affecte pas l'envoi d'origine.
     *
     * @param string      $to           Destinataire du mail d'origine
     * @param array       $replyTo      Reply-to du mail d'origine, déjà validé
     * @param string|null $resolvedPath Pièce jointe du mail d'origine, chemin déjà résolu
     */
    private function copyToAdmin(string $to, array $replyTo = [], ?string $resolvedPath = null): void
    {
        if (!defined('EMAIL_COPY_TO_ADMIN') || !EMAIL_COPY_TO_ADMIN) {
            return;
        }

        // le mail était déjà adressé à l'admin (ex: signalement depuis event/send.php)
        if (strcasecmp($to, EMAIL_ADMIN) === 0) {
            return;
        }

        $this->mail->Subject = self::COPY_SUBJECT_PREFIX . $this->mail->Subject;
        $this->mail->AddAddress(EMAIL_ADMIN, EMAIL_ADMIN_NAME);
        $this->mail->addCustomHeader('X-Original-To', $this->sanitizeHeader($to));

        if (!empty($replyTo)) {
            $this->mail->addReplyTo($replyTo['email'], $this->sanitizeHeader($replyTo['name'] ?? ''));
        }

        if ($resolvedPath !== null) {
            $this->mail->addAttachment($resolvedPath);
        }

        $this->send("Mailing::copyToAdmin — ");

        $this->mail->clearCustomHeaders();
    }

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
     *
     * @param string $context Préfixe de log, pour distinguer l'échec d'une copie de monitoring
     *                        de celui du mail d'origine
     */
    private function send(string $context = ''): bool
    {
        try {
            if (!$this->mail->send()) {
                error_log($context . $this->errorMsg . $this->mail->ErrorInfo);
                return false;
            }
        } catch (Exception) {
            error_log($context . $this->errorMsg . $this->mail->ErrorInfo);
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
