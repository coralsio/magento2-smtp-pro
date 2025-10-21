<?php
declare(strict_types=1);

namespace Corals\SMTP\Model;

use Corals\SMTP\Helper\Config;
use Psr\Log\LoggerInterface;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use Laminas\Mime\Mime;

class MailSender
{
    private Config $config;
    private LoggerInterface $logger;
    
    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Send email using Laminas Mail
     */
    public function sendMail(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $attachments = []
    ): bool {
        if (!$this->config->isEnabled()) {
            throw new \Exception('SMTP is not enabled in configuration');
        }
        
        // Use HTTP API for SMTP2GO provider
        if ($this->config->getProvider() === 'smtp2go') {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $smtp2go = $objectManager->create(\Corals\SMTP\Model\Provider\Smtp2go::class);
            
            $result = $smtp2go->sendViaApi(
                $to,
                $subject,
                $htmlBody,
                $textBody,
                $fromEmail,
                $fromName,
                $attachments
            );
            
            if (!$result['success']) {
                $this->logEmail($to, $subject, $fromEmail ?: $this->config->getFromEmail(), 'failed', $result['message']);
                throw new \Exception($result['message']);
            }
            
            // Log successful email
            $this->logEmail($to, $subject, $fromEmail ?: $this->config->getFromEmail(), 'sent', null, $result['email_id'] ?? null);
            
            return true;
        }
        
        try {
            // Create message
            $message = new Message();
            $message->setEncoding('UTF-8');
            
            // Set subject
            $message->setSubject($subject);
            
            // Set from
            $fromEmail = $fromEmail ?: $this->config->getFromEmail();
            $fromName = $fromName ?: $this->config->getFromName();
            if ($fromName) {
                $message->setFrom($fromEmail, $fromName);
            } else {
                $message->setFrom($fromEmail);
            }
            
            // Set recipients (support multiple)
            $recipients = is_array($to) ? $to : explode(',', $to);
            foreach ($recipients as $recipient) {
                $message->addTo(trim($recipient));
            }
            
            // Set reply-to if configured
            $replyTo = $this->config->getReplyTo();
            if ($replyTo) {
                $message->setReplyTo($replyTo);
            }
            
            // Create MIME message with both HTML and text parts
            $htmlPart = new MimePart($htmlBody);
            $htmlPart->type = Mime::TYPE_HTML;
            $htmlPart->charset = 'utf-8';
            $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            
            $textPart = new MimePart($textBody ?: strip_tags($htmlBody));
            $textPart->type = Mime::TYPE_TEXT;
            $textPart->charset = 'utf-8';
            $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            
            // Create MIME message
            $mimeMessage = new MimeMessage();
            $mimeMessage->setParts([$textPart, $htmlPart]);
            
            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $attachmentPart = new MimePart(fopen($attachment['path'], 'r'));
                    $attachmentPart->type = $attachment['type'] ?? 'application/octet-stream';
                    $attachmentPart->filename = $attachment['name'] ?? basename($attachment['path']);
                    $attachmentPart->disposition = Mime::DISPOSITION_ATTACHMENT;
                    $attachmentPart->encoding = Mime::ENCODING_BASE64;
                    $mimeMessage->addPart($attachmentPart);
                }
            }
            
            $message->setBody($mimeMessage);
            
            // Configure SMTP transport
            $options = $this->getSmtpOptions();
            $transport = new SmtpTransport($options);
            
            // Send the message
            $transport->send($message);
            
            // Log success
            $this->logger->info('Email sent successfully via Laminas Mail', [
                'to' => $to,
                'subject' => $subject,
                'from' => $fromEmail
            ]);
            
            // Log to database
            $this->logEmail($to, $subject, $fromEmail, 'sent');
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Laminas Mail Error: ' . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Mail sending failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get SMTP options for Laminas Mail
     */
    private function getSmtpOptions(): SmtpOptions {
        $config = [
            'host' => $this->config->getHost(),
            'port' => $this->config->getPort(),
        ];
        
        // Set connection class based on encryption
        $encryption = $this->config->getEncryption();
        if ($encryption === 'ssl') {
            $config['connection_class'] = 'smtp';
            $config['connection_config'] = [
                'ssl' => 'ssl'
            ];
        } elseif ($encryption === 'tls') {
            $config['connection_class'] = 'smtp';
            $config['connection_config'] = [
                'ssl' => 'tls'
            ];
        }
        
        // Set authentication if configured
        if ($this->config->getUsername()) {
            $config['connection_class'] = 'login';
            $config['connection_config'] = [
                'username' => $this->config->getUsername(),
                'password' => $this->config->getPassword(),
            ];
            
            // Add SSL/TLS if configured with authentication
            if ($encryption === 'ssl') {
                $config['connection_config']['ssl'] = 'ssl';
            } elseif ($encryption === 'tls') {
                $config['connection_config']['ssl'] = 'tls';
            }
        }
        
        return new SmtpOptions($config);
    }
    
    /**
     * Log email to database
     */
    private function logEmail(
        string $to,
        string $subject,
        string $from,
        string $status,
        ?string $error = null,
        ?string $messageId = null
    ): void {
        if (!$this->config->isLoggingEnabled()) {
            return;
        }
        
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('corals_smtp_log');
            
            $data = [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'from' => $from ?: $this->config->getFromEmail(),
                'subject' => $subject,
                'status' => $status,
                'provider' => $this->config->getProvider(),
                'message_id' => $messageId,
                'error_message' => $error,
                'created_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            
            $connection->insert($tableName, $data);
            
            $this->logger->info('Email logged', [
                'to' => $to,
                'subject' => $subject,
                'status' => $status
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to log email', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test SMTP connection
     */
    public function testConnection(): array {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];
        
        try {
            // Simple connection test using fsockopen
            $host = $this->config->getHost();
            $port = $this->config->getPort();
            
            $connection = @fsockopen($host, $port, $errno, $errstr, 10);
            
            if ($connection) {
                $response = fgets($connection, 1024);
                fclose($connection);
                
                $result['success'] = true;
                $result['message'] = 'Successfully connected to SMTP server';
                $result['details'] = [
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $this->config->getEncryption(),
                    'authentication' => $this->config->getUsername() ? 'Yes' : 'No',
                    'server_response' => trim($response)
                ];
            } else {
                $result['message'] = "Failed to connect to SMTP server: $errstr";
                $result['details']['error'] = "$errstr (Error #$errno)";
            }
            
        } catch (\Exception $e) {
            $result['message'] = 'Connection test failed: ' . $e->getMessage();
            $result['details']['error'] = $e->getMessage();
        }
        
        return $result;
    }
}