<?php
/**
 * Usage:
 *      Mailer::init( ... );
 *      $isSent = Mailer::send( ... );
 *
 *      For mail templates syntax see "View.php"
 *      Depends on vendors/phpmailer/mailer.php
 */
class Mailer
{
    protected static $_templateBaseDir;
    protected static $_senderEmail;
    protected static $_senderName;
    protected static $_sysRecipients;
    protected static $_enabled;
    protected static $_inited;

    static function init(string $templateBaseDir, string $senderEmail, string $senderName, array $sysRecipients, bool $enabled = true)
    {
        if (static::$_inited++) { throw new Err("Class [%s] already initialized", __CLASS__); }

        if (!is_dir($templateBaseDir)) {
            throw new Err("Not a dir [$templateBaseDir]");
        }
        static::$_templateBaseDir = $templateBaseDir;

        if (!$senderEmail) {
            throw new Err("Empty sender email");
        }
        static::$_senderEmail = $senderEmail;

        if (!$senderName) {
            throw new Err("Empty sender name");
        }
        static::$_senderName = $senderName;

        if (!$sysRecipients) {
            throw new Err("Empty system recipients");
        }
        static::$_sysRecipients = $sysRecipients;

        static::$_enabled = $enabled;

        require_once dirname(__FILE__) . '/../vendors/phpmailer/mailer.php';
    }

    static function send(string $subject, string $templatePath, array $macros, array $recipients)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        if (!$templatePath) {
            throw new Err("Empty template path");
        }
        if (!$recipients) {
            throw new Err("Empty recipients for mail [$templatePath]");
        }

        $content = (new View(static::$_templateBaseDir . '/' . $templatePath))
            ->set($macros)
            ->render();

        if (!static::$_enabled) {
            Log::write('emailed', "Recipients: " . join(', ', $recipients) . " -- Subject: $subject -- Content: $content");
            return true;
        }

        $mail = new PHPMailer();

        $mail->Timeout  = 10;
        $mail->CharSet  = 'UTF-8';
        $mail->From     = static::$_senderEmail;
        $mail->FromName = static::$_senderName;
        $mail->Subject  = $subject;

        $mail->MsgHTML($content);

        foreach ($recipients as $recipient) {
            $mail->AddAddress($recipient);
        }

        $isSent = false;
        $attempts = 3;
        while($attempts--) {
            try {
                if (!$mail->Send()) {
                    throw new Err($mail->ErrorInfo);
                }
                $isSent = true;
                break;
            }
            catch (Exception $e) {
                usleep(10000);
                continue;
            }
        }

        if (!$isSent) {
            throw new Err("Failed to send email with subject [$subject] to [%s]. Error: ", join(', ', $recipients), $e->getMessage());
        }
    }

    // NOTE: E-mail template "template.phtml" must exists
    static function sendHtml(string $subject, string $content, array $recipients)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::send(
            $subject, 'template', ['content' => $content], $recipients
        );
    }

    // NOTE: E-mail template "notify.phtml" must exists
    static function notify(string $subject, string $message)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        $serverTime = F::formatUtime(time());
        static::send(
            "$subject -- $serverTime", 'notify', ['message' => $message], static::$_sysRecipients
        );
    }

    static function notifyError(string $subject, string $message)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::notify("ERROR: $subject", $message);
    }

    static function notifyWarning(string $subject, string $message)
    {
        if (!static::$_inited) { throw new Err("Class [%s] was not initialized with init()", __CLASS__); }

        static::notify("WARNING: $subject", $message);
    }
}