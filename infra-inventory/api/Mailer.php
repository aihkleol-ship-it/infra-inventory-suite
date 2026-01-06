<?php
// api/Mailer.php

class Mailer {
    private $pdo;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $enc; // tls, ssl, or null
    private $fromEmail;
    private $fromName;
    private $debug = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->host = $settings['smtp_host'] ?? 'localhost';
        $this->port = $settings['smtp_port'] ?? 25;
        $this->user = $settings['smtp_user'] ?? '';
        $this->pass = $settings['smtp_pass'] ?? '';
        $this->enc  = $settings['smtp_encryption'] ?? ''; // tls, ssl
        $this->fromEmail = $settings['smtp_from_email'] ?? 'noreply@inventory.local';
        $this->fromName  = $settings['smtp_from_name'] ?? 'InfraInventory';
    }

    public function send($to, $subject, $htmlBody) {
        try {
            $socket = $this->connect();
            $this->auth($socket);
            
            // Email Headers
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: =?UTF-8?B?".base64_encode($this->fromName)."?= <{$this->fromEmail}>\r\n";
            $headers .= "To: <$to>\r\n";
            $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
            $headers .= "Date: " . date("r") . "\r\n";

            $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
            $this->sendCommand($socket, "RCPT TO: <$to>");
            $this->sendCommand($socket, "DATA");
            
            // Send Content
            fwrite($socket, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");
            $this->readResponse($socket); // Expect 250 OK

            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            return ["success" => true, "message" => "Sent via SMTP"];

        } catch (Exception $e) {
            return ["success" => false, "message" => "SMTP Error: " . $e->getMessage()];
        }
    }

    // --- Internal Helpers ---

    private function connect() {
        $protocol = ($this->enc === 'ssl') ? 'ssl://' : '';
        $server = $protocol . $this->host;
        $errno = 0; $errstr = '';
        
        $socket = fsockopen($server, $this->port, $errno, $errstr, 15);
        if (!$socket) throw new Exception("Could not connect: $errstr ($errno)");
        
        $this->readResponse($socket); // Greeting
        $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);

        // Handle TLS upgrade
        if ($this->enc === 'tls') {
            $this->sendCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS negotiation failed");
            }
            $this->sendCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
        }

        return $socket;
    }

    private function auth($socket) {
        if (!empty($this->user) && !empty($this->pass)) {
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->user));
            $this->sendCommand($socket, base64_encode($this->pass));
        }
    }

    private function sendCommand($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
        $this->readResponse($socket);
    }

    private function readResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        // Basic error checking (4xx or 5xx codes)
        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("Server Error ($code): $response");
        }
        return $response;
    }
}
?>