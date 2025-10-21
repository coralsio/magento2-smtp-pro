<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

use Corals\SMTP\Helper\Config;
use Psr\Log\LoggerInterface;

class Smtp2go
{
    const API_ENDPOINT = 'https://api.smtp2go.com/v3/email/send';
    
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
     * Send email via SMTP2GO HTTP API
     */
    public function sendViaApi(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $attachments = []
    ): array {
        $fromEmail = $fromEmail ?: $this->config->getFromEmail();
        $fromName = $fromName ?: $this->config->getFromName();
        
        // Prepare API payload
        $payload = [
            'api_key' => $this->config->getPassword(), // API key is stored as password
            'to' => is_array($to) ? $to : [$to],
            'sender' => $fromName ? "$fromName <$fromEmail>" : $fromEmail,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody ?: strip_tags($htmlBody)
        ];
        
        // Add attachments if any
        if (!empty($attachments)) {
            $payload['attachments'] = [];
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $payload['attachments'][] = [
                        'filename' => $attachment['name'] ?? basename($attachment['path']),
                        'fileblob' => base64_encode(file_get_contents($attachment['path'])),
                        'mimetype' => $attachment['type'] ?? 'application/octet-stream'
                    ];
                }
            }
        }
        
        // Make API request
        $ch = curl_init(self::API_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('SMTP2GO API cURL error', ['error' => $error]);
            return [
                'success' => false,
                'message' => 'Network error: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['data']['succeeded']) && $result['data']['succeeded'] > 0) {
            $this->logger->info('Email sent via SMTP2GO API', [
                'to' => $to,
                'subject' => $subject,
                'email_id' => $result['data']['email_id'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'email_id' => $result['data']['email_id'] ?? null,
                'request_id' => $result['request_id'] ?? null
            ];
        } else {
            $errorMsg = $result['data']['error'] ?? $result['data']['failures'][0]['reason'] ?? 'Unknown error';
            
            $this->logger->error('SMTP2GO API error', [
                'http_code' => $httpCode,
                'error' => $errorMsg,
                'response' => $response
            ]);
            
            return [
                'success' => false,
                'message' => 'SMTP2GO API Error: ' . $errorMsg,
                'http_code' => $httpCode,
                'response' => $result
            ];
        }
    }
    
    /**
     * Test SMTP2GO API connection
     */
    public function testApiConnection(): array {
        $apiKey = $this->config->getPassword();
        
        if (!$apiKey) {
            return [
                'success' => false,
                'message' => 'API key not configured'
            ];
        }
        
        // Test with a simple API call
        $payload = [
            'api_key' => $apiKey
        ];
        
        $ch = curl_init('https://api.smtp2go.com/v3/users/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['data'])) {
            return [
                'success' => true,
                'message' => 'API connection successful',
                'account_email' => $result['data']['email'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API authentication failed',
                'error' => $result['data']['error'] ?? 'Invalid API key'
            ];
        }
    }
}