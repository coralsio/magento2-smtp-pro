<?php
declare(strict_types=1);

namespace Corals\SMTP\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Corals\SMTP\Helper\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Dashboard extends Template
{
    protected Config $config;
    protected ResourceConnection $resourceConnection;
    protected TimezoneInterface $timezone;

    public function __construct(
        Context $context,
        Config $config,
        ResourceConnection $resourceConnection,
        TimezoneInterface $timezone,
        array $data = []
    ) {
        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
        $this->timezone = $timezone;
        parent::__construct($context, $data);
    }

    /**
     * Get SMTP configuration status
     */
    public function isSmtpEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get current provider
     */
    public function getCurrentProvider(): string
    {
        return $this->config->getProvider() ?: 'not_configured';
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayName(): string
    {
        $provider = $this->getCurrentProvider();
        $providers = [
            'gmail' => 'Gmail',
            'sendgrid' => 'SendGrid',
            'mailgun' => 'Mailgun',
            'amazon_ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'custom' => 'Custom SMTP',
            'not_configured' => 'Not Configured'
        ];
        
        return $providers[$provider] ?? $provider;
    }

    /**
     * Get SMTP host
     */
    public function getSmtpHost(): string
    {
        return $this->config->getHost() ?: 'Not configured';
    }

    /**
     * Get SMTP port
     */
    public function getSmtpPort(): string
    {
        $port = $this->config->getPort();
        return $port ? (string)$port : 'Not configured';
    }

    /**
     * Get configuration URL
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'corals_smtp']);
    }

    /**
     * Get test email URL
     */
    public function getTestEmailUrl(): string
    {
        return $this->getUrl('smtp-pro/test/index');
    }

    /**
     * Get logs URL
     */
    public function getLogsUrl(): string
    {
        return $this->getUrl('smtp-pro/logs/index');
    }

    /**
     * Get total emails sent today
     */
    public function getTodaysEmailCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            $today = $this->timezone->date()->format('Y-m-d');
            
            $select = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)'])
                ->where('DATE(created_at) = ?', $today)
                ->where('status = ?', 'sent');
                
            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total emails sent this week
     */
    public function getWeeklyEmailCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            // Get start of current week (Monday)
            $now = $this->timezone->date();
            $weekStart = $now->modify('monday this week')->format('Y-m-d');
            
            $select = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)'])
                ->where('DATE(created_at) >= ?', $weekStart)
                ->where('status = ?', 'sent');
                
            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total emails sent this month
     */
    public function getMonthlyEmailCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            // Get start of current month
            $now = $this->timezone->date();
            $monthStart = $now->format('Y-m-01');
            
            $select = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)'])
                ->where('DATE(created_at) >= ?', $monthStart)
                ->where('status = ?', 'sent');
                
            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get failed emails count
     */
    public function getFailedEmailCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            $select = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)'])
                ->where('status = ?', 'failed');
                
            return (int)$connection->fetchOne($select);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get delivery success rate
     */
    public function getSuccessRate(): float
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            $totalSelect = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)']);
            $totalEmails = (int)$connection->fetchOne($totalSelect);
            
            if ($totalEmails === 0) {
                return 100.0;
            }
            
            $sentSelect = $connection->select()
                ->from($tableName, ['count' => 'COUNT(*)'])
                ->where('status = ?', 'sent');
            $sentEmails = (int)$connection->fetchOne($sentSelect);
            
            return round(($sentEmails / $totalEmails) * 100, 1);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get recent email activity (last 7 days)
     */
    public function getRecentActivity(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            $weekAgo = $this->timezone->date()->modify('-7 days')->format('Y-m-d');
            
            $select = $connection->select()
                ->from($tableName, [
                    'date' => 'DATE(created_at)',
                    'sent' => 'SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END)',
                    'failed' => 'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END)'
                ])
                ->where('DATE(created_at) >= ?', $weekAgo)
                ->group('DATE(created_at)')
                ->order('date ASC');
                
            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top email providers usage
     */
    public function getProviderStats(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('corals_smtp_log');
            
            $select = $connection->select()
                ->from($tableName, [
                    'provider',
                    'count' => 'COUNT(*)',
                    'success_rate' => 'ROUND((SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1)'
                ])
                ->group('provider')
                ->order('count DESC')
                ->limit(5);
                
            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            return [];
        }
    }
}