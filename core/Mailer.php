<?php
/**
 * ABAppointments - Mailer (SMTP natif PHP avec socket)
 */
class Mailer {
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private string $lastError = '';

    public function __construct() {
        $db = Database::getInstance();
        $settings = $db->fetchAll("SELECT setting_key, setting_value FROM ab_settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'business_name'");
        $config = [];
        foreach ($settings as $s) {
            $config[$s['setting_key']] = $s['setting_value'];
        }

        $this->host = $config['smtp_host'] ?? '';
        $this->port = (int)($config['smtp_port'] ?? 587);
        $this->encryption = $config['smtp_encryption'] ?? 'tls';
        $this->username = $config['smtp_username'] ?? '';
        $this->password = $config['smtp_password'] ?? '';
        $this->fromEmail = $config['smtp_from_email'] ?? '';
        $this->fromName = $config['smtp_from_name'] ?? ($config['business_name'] ?? 'ABAppointments');
    }

    public function send(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
        if (empty($this->host)) {
            $this->lastError = 'SMTP non configuré';
            return false;
        }

        try {
            $boundary = md5(uniqid(time()));
            $headers = $this->buildHeaders($boundary);
            $body = $this->buildBody($htmlBody, $boundary);

            $socket = $this->connect();
            if (!$socket) return false;

            $this->smtpCommand($socket, '', '220');

            $this->smtpCommand($socket, "EHLO " . gethostname(), '250');

            if ($this->encryption === 'tls') {
                $this->smtpCommand($socket, "STARTTLS", '220');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->smtpCommand($socket, "EHLO " . gethostname(), '250');
            }

            if (!empty($this->username)) {
                $this->smtpCommand($socket, "AUTH LOGIN", '334');
                $this->smtpCommand($socket, base64_encode($this->username), '334');
                $this->smtpCommand($socket, base64_encode($this->password), '235');
            }

            $this->smtpCommand($socket, "MAIL FROM:<{$this->fromEmail}>", '250');
            $this->smtpCommand($socket, "RCPT TO:<{$to}>", '250');
            $this->smtpCommand($socket, "DATA", '354');

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
            $toHeader = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $to . '>' : $to;

            $message = "From: {$encodedFromName} <{$this->fromEmail}>\r\n";
            $message .= "To: {$toHeader}\r\n";
            $message .= "Subject: {$encodedSubject}\r\n";
            $message .= "Date: " . date('r') . "\r\n";
            $message .= "Message-ID: <" . uniqid() . "@" . gethostname() . ">\r\n";
            $message .= $headers . "\r\n";
            $message .= $body;

            // Escape dots at start of lines
            $message = str_replace("\r\n.", "\r\n..", $message);

            $this->smtpCommand($socket, $message . "\r\n.", '250');
            $this->smtpCommand($socket, "QUIT", '221');

            fclose($socket);
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function connect() {
        $protocol = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $protocol . $this->host . ':' . $this->port,
            $errno, $errstr, 30,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );

        if (!$socket) {
            $this->lastError = "Connexion SMTP impossible: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($socket, 30);
        return $socket;
    }

    private function smtpCommand($socket, string $command, string $expectedCode): string {
        if (!empty($command)) {
            fwrite($socket, $command . "\r\n");
        }
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        if (!str_starts_with($response, $expectedCode)) {
            throw new Exception("SMTP Error: expected {$expectedCode}, got: " . trim($response));
        }
        return $response;
    }

    private function buildHeaders(string $boundary): string {
        return "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    }

    private function buildBody(string $html, string $boundary): string {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->wrapHtmlTemplate($html))) . "\r\n";

        $body .= "--{$boundary}--";
        return $body;
    }

    private function wrapHtmlTemplate(string $content): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">'
            . '<div style="background:#e91e63;padding:20px;text-align:center;color:#ffffff;font-size:20px;font-weight:bold;">'
            . htmlspecialchars($this->fromName)
            . '</div>'
            . '<div style="padding:30px;">' . $content . '</div>'
            . '<div style="padding:15px;text-align:center;color:#999;font-size:12px;border-top:1px solid #eee;">'
            . htmlspecialchars($this->fromName) . ' - Système de rendez-vous'
            . '</div></div></body></html>';
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function sendTemplate(string $slug, string $to, array $variables, string $toName = ''): bool {
        $db = Database::getInstance();
        $template = $db->fetchOne("SELECT * FROM ab_email_templates WHERE slug = ? AND is_active = 1", [$slug]);
        if (!$template) {
            $this->lastError = "Template '$slug' non trouvé ou inactif";
            return false;
        }

        $subject = $template['subject'];
        $body = $template['body'];

        foreach ($variables as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return $this->send($to, $subject, $body, $toName);
    }
}
