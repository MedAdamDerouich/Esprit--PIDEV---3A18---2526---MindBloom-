<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoi d'email de réinitialisation de mot de passe via Gmail SMTP.
 * Port PHP de EmailService.java#sendPasswordResetEmail (MindBloom-USERetRDV).
 */
class PasswordResetEmailService
{
    private const GMAIL_EMAIL        = 'mindbloom.platform@gmail.com';
    private const GMAIL_APP_PASSWORD = 'tmdetyjtfnylfqln';
    private const SMTP_HOST          = 'smtp.gmail.com';
    private const SMTP_PORT          = 587;
    private const SENDER_NAME        = 'MindBloom';

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function sendPasswordResetEmail(string $toEmail, string $toName, string $code): void
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $mail->isSMTP();
        $mail->Host       = self::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = self::GMAIL_EMAIL;
        $mail->Password   = self::GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = self::SMTP_PORT;
        $mail->SMTPOptions = [
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];

        $mail->setFrom(self::GMAIL_EMAIL, self::SENDER_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'MindBloom — Réinitialisation de votre mot de passe';

        $logoPath = $this->projectDir . '/public/images/mindbloom_logo.png';
        if (is_file($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'mindbloom_logo', 'mindbloom_logo.png');
        }

        $mail->Body    = $this->buildHtml($toName, $code);

        $mail->send();
        $this->logger->info('Email reset envoyé à : ' . $toEmail);
    }

    private function buildHtml(string $name, string $code): string
    {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>
body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f6f9;margin:0;padding:20px;}
.wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(0,0,0,.10);overflow:hidden;}
.hdr{background:linear-gradient(135deg,#4A90E2 0%,#2563EB 100%);padding:28px 40px;text-align:center;}
.hdr img{display:block;margin:0 auto 10px;max-width:120px;height:auto;}
.hdr h1{color:#fff;margin:0;font-size:24px;letter-spacing:1px;font-weight:800;}
.hdr p{color:rgba(255,255,255,.85);margin:4px 0 0;font-size:13px;}
.body{padding:36px 40px;}
.body p{color:#444;font-size:15px;line-height:1.8;margin:0 0 14px;}
.code-box{background:#EEF6FF;border:2px dashed #4A90E2;border-radius:14px;text-align:center;padding:24px 20px;margin:28px 0;}
.code{font-size:42px;font-weight:900;color:#2C3E50;letter-spacing:14px;font-family:monospace;}
.hint{font-size:12px;color:#7F8C8D;margin-top:10px;}
.warn{background:#FFF8E1;border-left:4px solid #F39C12;padding:12px 16px;border-radius:8px;font-size:13px;color:#7D6608;margin-top:8px;}
.ftr{background:#f4f6f9;text-align:center;padding:18px;font-size:11px;color:#95A5A6;border-top:1px solid #EAECEE;}
</style></head><body>
<div class="wrap">
  <div class="hdr"><img src="cid:mindbloom_logo" alt="MindBloom"><h1>MindBloom</h1><p>Plateforme de bien-être mental</p></div>
  <div class="body">
    <p>Bonjour <strong>{$name}</strong>,</p>
    <p>Vous avez demandé la réinitialisation de votre mot de passe.<br>
       Voici votre code de vérification à usage unique :</p>
    <div class="code-box">
      <div class="code">{$code}</div>
      <div class="hint">Ce code expire dans <strong>15 minutes</strong></div>
    </div>
    <p>Saisissez ce code dans l'application MindBloom pour créer un nouveau mot de passe.</p>
    <div class="warn">Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Votre mot de passe reste inchangé.</div>
  </div>
  <div class="ftr">© 2026 MindBloom — Ne répondez pas à cet email automatique.</div>
</div></body></html>
HTML;
    }
}
