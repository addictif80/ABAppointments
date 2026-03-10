<?php
/**
 * WebPanel - Mailer (SMTP)
 */
class Mailer {
    private $lastError = '';

    public function send($to, $subject, $htmlBody, $fromName = null, $fromEmail = null) {
        $host = wp_setting('smtp_host');
        $port = (int)wp_setting('smtp_port', 587);
        $user = wp_setting('smtp_user');
        $pass = wp_setting('smtp_pass');
        $encryption = wp_setting('smtp_encryption', 'tls');
        $fromEmail = $fromEmail ?: wp_setting('smtp_from_email', $user);
        $fromName = $fromName ?: wp_setting('smtp_from_name', 'WebPanel');

        if (empty($host) || empty($user)) {
            $this->lastError = 'SMTP not configured';
            return false;
        }

        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$socket) {
                $this->lastError = "Connection failed: $errstr ($errno)";
                return false;
            }

            stream_set_timeout($socket, 10);
            $this->readResponse($socket);

            $this->sendCommand($socket, "EHLO " . gethostname());

            if ($encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . gethostname());
            }

            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($user));
            $this->sendCommand($socket, base64_encode($pass));

            $this->sendCommand($socket, "MAIL FROM:<$fromEmail>");
            $this->sendCommand($socket, "RCPT TO:<$to>");
            $this->sendCommand($socket, "DATA");

            $boundary = md5(uniqid(time()));
            $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
            $headers .= "To: <$to>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";

            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($textBody)) . "\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--$boundary--\r\n";

            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if (isset($socket) && is_resource($socket)) fclose($socket);
            return false;
        }
    }

    public function sendTemplate($to, $templateSlug, $variables = []) {
        $db = Database::getInstance();
        $template = $db->fetchOne("SELECT * FROM wp_email_templates WHERE slug = ? AND is_active = 1", [$templateSlug]);
        if (!$template) return false;

        $subject = $template['subject'];
        $body = $template['body_html'];

        $variables['site_name'] = wp_setting('site_name');
        $variables['site_url'] = wp_setting('site_url');
        $variables['company_name'] = wp_setting('company_name');

        foreach ($variables as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        // Wrap in styled HTML
        $styledBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;">';
        $styledBody .= $body;
        $styledBody .= '<hr style="margin-top:30px;border:none;border-top:1px solid #eee"><p style="font-size:12px;color:#999;">' . wp_setting('company_name') . '</p>';
        $styledBody .= '</body></html>';

        return $this->send($to, $subject, $styledBody);
    }

    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Error $code: $response");
        }
        return $response;
    }

    public function getLastError() {
        return $this->lastError;
    }
}
