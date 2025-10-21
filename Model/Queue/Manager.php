<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Queue;

use Corals\SMTP\Helper\Config;
use Corals\SMTP\Model\MailSender;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Manager
{
    private Config $config;
    private MailSender $mailSender;
    private ResourceConnection $resource;
    private LoggerInterface $logger;
    
    public function __construct(
        Config $config,
        MailSender $mailSender,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->mailSender = $mailSender;
        $this->resource = $resource;
        $this->logger = $logger;
    }
    
    /**
     * Add email to queue
     */
    public function addToQueue(array $emailData): int
    {
        if (!$this->config->isQueueEnabled()) {
            throw new \Exception('Queue is not enabled');
        }
        
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_queue');
        
        $data = [
            'to' => is_array($emailData['to']) ? implode(',', $emailData['to']) : $emailData['to'],
            'from' => $emailData['from'] ?? $this->config->getFromEmail(),
            'cc' => isset($emailData['cc']) ? (is_array($emailData['cc']) ? implode(',', $emailData['cc']) : $emailData['cc']) : null,
            'bcc' => isset($emailData['bcc']) ? (is_array($emailData['bcc']) ? implode(',', $emailData['bcc']) : $emailData['bcc']) : null,
            'subject' => $emailData['subject'],
            'body_html' => $emailData['html_body'] ?? null,
            'body_text' => $emailData['text_body'] ?? null,
            'priority' => $emailData['priority'] ?? 5,
            'status' => 'pending',
            'attempts' => 0,
            'provider' => $this->config->getProvider(),
            'store_id' => $emailData['store_id'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => $emailData['scheduled_at'] ?? date('Y-m-d H:i:s')
        ];
        
        $connection->insert($tableName, $data);
        return (int)$connection->lastInsertId();
    }
    
    /**
     * Process queue
     */
    public function processQueue(): array
    {
        if (!$this->config->isQueueEnabled()) {
            return ['processed' => 0, 'failed' => 0, 'message' => 'Queue is disabled'];
        }
        
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_queue');
        
        $batchSize = $this->config->getQueueBatchSize();
        $maxRetries = $this->config->getRetryAttempts();
        $retryDelay = $this->config->getRetryDelay();
        
        // Get pending emails
        $select = $connection->select()
            ->from($tableName)
            ->where('status = ?', 'pending')
            ->where('scheduled_at <= ?', date('Y-m-d H:i:s'))
            ->where('attempts < ?', $maxRetries)
            ->order('priority ASC')
            ->order('created_at ASC')
            ->limit($batchSize);
        
        $emails = $connection->fetchAll($select);
        
        $processed = 0;
        $failed = 0;
        
        foreach ($emails as $email) {
            try {
                // Apply rate limiting
                if ($this->config->getRateLimit() > 0) {
                    $this->applyRateLimit();
                }
                
                // Check blacklist/whitelist
                if (!$this->isEmailAllowed($email['to'])) {
                    $this->updateQueueStatus($email['queue_id'], 'blocked', 'Email blocked by blacklist');
                    continue;
                }
                
                // Send email
                $result = $this->mailSender->sendMail(
                    $email['to'],
                    $email['subject'],
                    $email['body_html'] ?: '',
                    $email['body_text'] ?: '',
                    $email['from']
                );
                
                if ($result) {
                    $this->updateQueueStatus($email['queue_id'], 'sent');
                    $processed++;
                } else {
                    throw new \Exception('Failed to send email');
                }
                
            } catch (\Exception $e) {
                $this->handleFailedEmail($email, $e->getMessage(), $retryDelay);
                $failed++;
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
            'message' => "Processed $processed emails, $failed failed"
        ];
    }
    
    /**
     * Update queue status
     */
    private function updateQueueStatus(int $queueId, string $status, ?string $error = null): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_queue');
        
        $data = [
            'status' => $status,
            'processed_at' => date('Y-m-d H:i:s')
        ];
        
        if ($error) {
            $data['error_message'] = $error;
        }
        
        $connection->update($tableName, $data, ['queue_id = ?' => $queueId]);
    }
    
    /**
     * Handle failed email
     */
    private function handleFailedEmail(array $email, string $error, int $retryDelay): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_queue');
        
        $attempts = $email['attempts'] + 1;
        $maxRetries = $this->config->getRetryAttempts();
        
        $data = [
            'attempts' => $attempts,
            'last_attempt_at' => date('Y-m-d H:i:s'),
            'error_message' => $error
        ];
        
        if ($attempts >= $maxRetries) {
            $data['status'] = 'failed';
            $this->logFailure($email, $error);
            
            // Try fallback provider if enabled
            if ($this->config->isFallbackEnabled()) {
                $this->tryFallbackProvider($email);
            }
        } else {
            // Schedule retry
            $data['scheduled_at'] = date('Y-m-d H:i:s', time() + ($retryDelay * 60));
        }
        
        $connection->update($tableName, $data, ['queue_id = ?' => $email['queue_id']]);
    }
    
    /**
     * Try fallback provider
     */
    private function tryFallbackProvider(array $email): void
    {
        try {
            $fallbackProvider = $this->config->getFallbackProvider();
            
            if ($fallbackProvider && $fallbackProvider !== $this->config->getProvider()) {
                $this->logger->info('Attempting fallback provider', [
                    'provider' => $fallbackProvider,
                    'email_id' => $email['queue_id']
                ]);
                
                // Temporarily switch provider
                $originalProvider = $this->config->getProvider();
                $this->config->setCurrentProvider($fallbackProvider);
                
                $result = $this->mailSender->sendMail(
                    $email['to'],
                    $email['subject'],
                    $email['body_html'] ?: '',
                    $email['body_text'] ?: ''
                );
                
                // Restore original provider
                $this->config->setCurrentProvider($originalProvider);
                
                if ($result) {
                    $this->updateQueueStatus($email['queue_id'], 'sent_fallback');
                    $this->logger->info('Email sent via fallback provider', [
                        'provider' => $fallbackProvider,
                        'email_id' => $email['queue_id']
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Fallback provider failed', [
                'error' => $e->getMessage(),
                'email_id' => $email['queue_id']
            ]);
        }
    }
    
    /**
     * Check if email is allowed (blacklist/whitelist)
     */
    private function isEmailAllowed(string $email): bool
    {
        $blacklist = $this->config->getBlacklist();
        $whitelist = $this->config->getWhitelist();
        
        // Check whitelist first (if exists, only whitelist is allowed)
        if (!empty($whitelist)) {
            foreach ($whitelist as $pattern) {
                if ($this->matchesPattern($email, $pattern)) {
                    return true;
                }
            }
            return false; // Not in whitelist
        }
        
        // Check blacklist
        foreach ($blacklist as $pattern) {
            if ($this->matchesPattern($email, $pattern)) {
                return false; // In blacklist
            }
        }
        
        return true; // Not blacklisted
    }
    
    /**
     * Match email against pattern (supports wildcards)
     */
    private function matchesPattern(string $email, string $pattern): bool
    {
        // Convert pattern to regex
        $pattern = str_replace(['*', '?'], ['.*', '.'], $pattern);
        return (bool)preg_match('/^' . $pattern . '$/i', $email);
    }
    
    /**
     * Apply rate limiting
     */
    private function applyRateLimit(): void
    {
        $rateLimit = $this->config->getRateLimit(); // emails per minute
        if ($rateLimit <= 0) {
            return;
        }
        
        // Calculate delay between emails
        $delayMicroseconds = (60 / $rateLimit) * 1000000;
        usleep((int)$delayMicroseconds);
    }
    
    /**
     * Log failure
     */
    private function logFailure(array $email, string $error): void
    {
        $this->logger->error('Email permanently failed', [
            'to' => $email['to'],
            'subject' => $email['subject'],
            'error' => $error,
            'attempts' => $email['attempts']
        ]);
        
        // Add to bounce table if applicable
        if (strpos(strtolower($error), 'bounce') !== false) {
            $this->recordBounce($email['to'], $error);
        }
    }
    
    /**
     * Record bounce
     */
    private function recordBounce(string $email, string $reason): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_bounce');
        
        try {
            $connection->insert($tableName, [
                'email' => $email,
                'bounce_type' => $this->detectBounceType($reason),
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record bounce', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Detect bounce type
     */
    private function detectBounceType(string $reason): string
    {
        $lowerReason = strtolower($reason);
        
        if (strpos($lowerReason, 'mailbox') !== false || 
            strpos($lowerReason, 'not exist') !== false ||
            strpos($lowerReason, 'unknown') !== false) {
            return 'hard';
        }
        
        if (strpos($lowerReason, 'quota') !== false ||
            strpos($lowerReason, 'full') !== false ||
            strpos($lowerReason, 'temporary') !== false) {
            return 'soft';
        }
        
        return 'unknown';
    }
    
    /**
     * Clean old queue entries
     */
    public function cleanOldEntries(): int
    {
        $retentionDays = $this->config->getLogRetentionDays();
        
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_queue');
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));
        
        $deleted = $connection->delete($tableName, [
            'status IN (?)' => ['sent', 'failed', 'blocked'],
            'processed_at < ?' => $cutoffDate
        ]);
        
        $this->logger->info("Cleaned $deleted old queue entries");
        
        return $deleted;
    }
}