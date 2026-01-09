<?php
// gateway/api/Mailer.php
class Mailer {
    private $pdo;
    private $host; private $port; private $user; private $pass; private $enc; 
    private $fromEmail; private $fromName;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM gateway_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->host = $settings['smtp_host'] ?? 'localhost';
        $this->port = $settings['smtp_port'] ?? 25;
        $this->user = $settings['smtp_user'] ?? '';
        $this->pass = $settings['smtp_pass'] ?? '';
        $this->enc  = $settings['smtp_encryption'] ?? ''; // e.g., 'tls', 'ssl'
        $this->fromEmail = $settings['smtp_from_email'] ?? 'noreply@gateway.local';
        $this->fromName  = $settings['smtp_from_name'] ?? 'InfraGateway';
    }

    public function send($to, $subject, $htmlBody) {
        // (Copy the exact same send logic from the previous V5.1 Mailer.php here)
        // For brevity in this file block, I'll summarize the key lines, 
        // but in practice, you use the full fsockopen/STARTTLS logic provided earlier.
        
        try {
            $protocol = ($this->enc === 'ssl') ? 'ssl://' : '';
            $socket = fsockopen($protocol . $this->host, $this->port, $errno, $errstr, 15);
            if (!$socket) throw new Exception("Connect failed: $errstr");
            
            $this->readResponse($socket);
            $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            
            if ($this->enc === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            }
            
            if ($this->user && $this->pass) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($this->user));
                $this->sendCommand($socket, base64_encode($this->pass));
            }

            $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\nTo: <$to>\r\n";
            $headers .= "Subject: $subject\r\n";

            $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
            $this->sendCommand($socket, "RCPT TO: <$to>");
            $this->sendCommand($socket, "DATA");
            fwrite($socket, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");
            $this->readResponse($socket);
            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return ["success" => true, "message" => "Sent"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    private function sendCommand($socket, $cmd) { fwrite($socket, $cmd . "\r\n"); return $this->readResponse($socket); }
    private function readResponse($socket) {
        $r=""; while($s=fgets($socket,515)){$r.=$s; if(substr($s,3,1)==" ")break;}
        if(substr($r,0,1)!='2' && substr($r,0,1)!='3') throw new Exception("SMTP Error: $r");
        return $r;
    }
}
?>