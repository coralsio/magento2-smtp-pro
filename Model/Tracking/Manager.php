<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Tracking;

use Corals\SMTP\Helper\Config;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Manager
{
    private Config $config;
    private ResourceConnection $resource;
    private LoggerInterface $logger;
    
    const TRACKING_PIXEL = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    
    public function __construct(
        Config $config,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->resource = $resource;
        $this->logger = $logger;
    }
    
    /**
     * Add tracking to HTML email
     */
    public function addTrackingToEmail(string $htmlBody, string $emailId): string
    {
        if (!$this->config->isTrackingEnabled()) {
            return $htmlBody;
        }
        
        // Add open tracking pixel
        if ($this->config->isTrackOpensEnabled()) {
            $htmlBody = $this->addOpenTrackingPixel($htmlBody, $emailId);
        }
        
        // Replace links for click tracking
        if ($this->config->isTrackClicksEnabled()) {
            $htmlBody = $this->replaceLinksForTracking($htmlBody, $emailId);
        }
        
        return $htmlBody;
    }
    
    /**
     * Add open tracking pixel
     */
    private function addOpenTrackingPixel(string $htmlBody, string $emailId): string
    {
        $trackingUrl = $this->generateTrackingUrl('open', $emailId);
        $pixel = sprintf('<img src="%s" width="1" height="1" style="display:none;" alt="" />', $trackingUrl);
        
        // Add before </body> tag if exists, otherwise at the end
        if (stripos($htmlBody, '</body>') !== false) {
            $htmlBody = str_ireplace('</body>', $pixel . '</body>', $htmlBody);
        } else {
            $htmlBody .= $pixel;
        }
        
        return $htmlBody;
    }
    
    /**
     * Replace links for click tracking
     */
    private function replaceLinksForTracking(string $htmlBody, string $emailId): string
    {
        // Find all links
        $pattern = '/<a\s+(?:[^>]*?\s+)?href="([^"]+)"([^>]*)>/i';
        
        $htmlBody = preg_replace_callback($pattern, function($matches) use ($emailId) {
            $originalUrl = $matches[1];
            
            // Skip mailto, tel, and anchors
            if (strpos($originalUrl, 'mailto:') === 0 || 
                strpos($originalUrl, 'tel:') === 0 ||
                strpos($originalUrl, '#') === 0) {
                return $matches[0];
            }
            
            $trackingUrl = $this->generateClickTrackingUrl($originalUrl, $emailId);
            return '<a href="' . $trackingUrl . '"' . $matches[2] . '>';
        }, $htmlBody);
        
        return $htmlBody;
    }
    
    /**
     * Generate tracking URL
     */
    private function generateTrackingUrl(string $type, string $emailId, ?string $data = null): string
    {
        $baseUrl = $this->getBaseUrl();
        $params = [
            'type' => $type,
            'id' => $this->encodeTrackingId($emailId),
            'ts' => time()
        ];
        
        if ($data) {
            $params['data'] = base64_encode($data);
        }
        
        // Generate signature for security
        $params['sig'] = $this->generateSignature($params);
        
        return $baseUrl . 'corals_smtp/track/pixel?' . http_build_query($params);
    }
    
    /**
     * Generate click tracking URL
     */
    private function generateClickTrackingUrl(string $originalUrl, string $emailId): string
    {
        return $this->generateTrackingUrl('click', $emailId, $originalUrl);
    }
    
    /**
     * Track email open
     */
    public function trackOpen(string $emailId): void
    {
        if (!$this->config->isTrackOpensEnabled()) {
            return;
        }
        
        try {
            $connection = $this->resource->getConnection();
            $tableName = $this->resource->getTableName('corals_smtp_tracking');
            
            // Check if already tracked
            $select = $connection->select()
                ->from($tableName)
                ->where('email_id = ?', $emailId)
                ->where('event_type = ?', 'open')
                ->limit(1);
            
            $existing = $connection->fetchRow($select);
            
            if (!$existing) {
                // First open
                $connection->insert($tableName, [
                    'email_id' => $emailId,
                    'event_type' => 'open',
                    'event_data' => json_encode([
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Update open count
                $connection->update(
                    $tableName,
                    [
                        'event_count' => new \Zend_Db_Expr('event_count + 1'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    ['tracking_id = ?' => $existing['tracking_id']]
                );
            }
            
            $this->updateEmailStatus($emailId, 'opened');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track email open', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Track email click
     */
    public function trackClick(string $emailId, string $url): void
    {
        if (!$this->config->isTrackClicksEnabled()) {
            return;
        }
        
        try {
            $connection = $this->resource->getConnection();
            $tableName = $this->resource->getTableName('corals_smtp_tracking');
            
            $connection->insert($tableName, [
                'email_id' => $emailId,
                'event_type' => 'click',
                'event_data' => json_encode([
                    'url' => $url,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->updateEmailStatus($emailId, 'clicked');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track email click', [
                'email_id' => $emailId,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Track bounce
     */
    public function trackBounce(string $emailId, string $bounceType, string $reason): void
    {
        if (!$this->config->isTrackBouncesEnabled()) {
            return;
        }
        
        try {
            $connection = $this->resource->getConnection();
            $trackingTable = $this->resource->getTableName('corals_smtp_tracking');
            $bounceTable = $this->resource->getTableName('corals_smtp_bounce');
            
            // Add to tracking
            $connection->insert($trackingTable, [
                'email_id' => $emailId,
                'event_type' => 'bounce',
                'event_data' => json_encode([
                    'type' => $bounceType,
                    'reason' => $reason
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Get email details from log
            $logTable = $this->resource->getTableName('corals_smtp_log');
            $email = $connection->fetchRow(
                $connection->select()
                    ->from($logTable, ['to'])
                    ->where('message_id = ?', $emailId)
                    ->limit(1)
            );
            
            if ($email) {
                // Add to bounce table
                $connection->insert($bounceTable, [
                    'email' => $email['to'],
                    'bounce_type' => $bounceType,
                    'reason' => $reason,
                    'message_id' => $emailId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->updateEmailStatus($emailId, 'bounced');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track bounce', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update email status in log
     */
    private function updateEmailStatus(string $emailId, string $status): void
    {
        try {
            $connection = $this->resource->getConnection();
            $tableName = $this->resource->getTableName('corals_smtp_log');
            
            $connection->update(
                $tableName,
                ['status' => $status],
                ['message_id = ?' => $emailId]
            );
        } catch (\Exception $e) {
            // Log but don't throw
            $this->logger->warning('Failed to update email status', [
                'email_id' => $emailId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get tracking statistics
     */
    public function getStatistics(?string $emailId = null, ?array $dateRange = null): array
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('corals_smtp_tracking');
        
        $select = $connection->select()
            ->from($tableName, [
                'event_type',
                'total' => new \Zend_Db_Expr('COUNT(*)'),
                'unique' => new \Zend_Db_Expr('COUNT(DISTINCT email_id)')
            ])
            ->group('event_type');
        
        if ($emailId) {
            $select->where('email_id = ?', $emailId);
        }
        
        if ($dateRange) {
            if (isset($dateRange['from'])) {
                $select->where('created_at >= ?', $dateRange['from']);
            }
            if (isset($dateRange['to'])) {
                $select->where('created_at <= ?', $dateRange['to']);
            }
        }
        
        $stats = $connection->fetchAll($select);
        
        $result = [
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'bounces' => 0
        ];
        
        foreach ($stats as $stat) {
            switch ($stat['event_type']) {
                case 'open':
                    $result['opens'] = $stat['total'];
                    $result['unique_opens'] = $stat['unique'];
                    break;
                case 'click':
                    $result['clicks'] = $stat['total'];
                    $result['unique_clicks'] = $stat['unique'];
                    break;
                case 'bounce':
                    $result['bounces'] = $stat['total'];
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Encode tracking ID
     */
    private function encodeTrackingId(string $id): string
    {
        return base64_encode($id . '|' . time());
    }
    
    /**
     * Decode tracking ID
     */
    public function decodeTrackingId(string $encoded): ?string
    {
        try {
            $decoded = base64_decode($encoded);
            list($id, $timestamp) = explode('|', $decoded);
            
            // Check if tracking link is not too old (30 days)
            if (time() - $timestamp > 2592000) {
                return null;
            }
            
            return $id;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Generate signature for security
     */
    private function generateSignature(array $params): string
    {
        ksort($params);
        $string = implode('|', $params) . '|' . $this->getSecretKey();
        return hash('sha256', $string);
    }
    
    /**
     * Verify signature
     */
    public function verifySignature(array $params): bool
    {
        $signature = $params['sig'] ?? '';
        unset($params['sig']);
        
        return $signature === $this->generateSignature($params);
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl(): string
    {
        // This should be injected via DI in production
        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
    }
    
    /**
     * Get secret key for signatures
     */
    private function getSecretKey(): string
    {
        // This should come from config in production
        return hash('sha256', 'corals_smtp_tracking_secret');
    }
}