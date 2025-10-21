<?php
declare(strict_types=1);

namespace Corals\SMTP\Block\Adminhtml\Logs;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Corals\SMTP\Model\ResourceModel\Email\Log\CollectionFactory;
use Corals\SMTP\Helper\Config;

class Index extends Template
{
    protected CollectionFactory $logCollectionFactory;
    protected Config $config;

    public function __construct(
        Context $context,
        CollectionFactory $logCollectionFactory,
        Config $config,
        array $data = []
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Get email logs collection
     */
    public function getLogsCollection()
    {
        $collection = $this->logCollectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(50); // Limit to 50 records per page
        return $collection;
    }

    /**
     * Get logs count
     */
    public function getLogsCount(): int
    {
        try {
            $collection = $this->logCollectionFactory->create();
            return $collection->getSize();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get dashboard URL
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('smtp-pro/dashboard/index');
    }

    /**
     * Get test email URL
     */
    public function getTestEmailUrl(): string
    {
        return $this->getUrl('smtp-pro/test/index');
    }

    /**
     * Get configuration URL
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/corals_smtp');
    }

    /**
     * Format status for display
     */
    public function getStatusLabel(string $status): string
    {
        switch ($status) {
            case 'sent':
                return '✅ Sent';
            case 'failed':
                return '❌ Failed';
            case 'pending':
                return '⏳ Pending';
            case 'queued':
                return '📝 Queued';
            default:
                return '❓ Unknown';
        }
    }

    /**
     * Get status CSS class
     */
    public function getStatusClass(string $status): string
    {
        switch ($status) {
            case 'sent':
                return 'status-success';
            case 'failed':
                return 'status-error';
            case 'pending':
                return 'status-pending';
            case 'queued':
                return 'status-queued';
            default:
                return 'status-unknown';
        }
    }

    /**
     * Format date for display
     */
    public function formatLogDate($date): string
    {
        if (!$date) {
            return '-';
        }
        
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format('M j, Y g:i A');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Truncate text for display
     */
    public function truncateText(?string $text, int $length = 50): string
    {
        if (!$text) {
            return '';
        }
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . '...';
    }

    /**
     * Check if logs table exists and has data
     */
    public function hasLogsData(): bool
    {
        try {
            $collection = $this->logCollectionFactory->create();
            $collection->setPageSize(1);
            return $collection->getSize() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if logs table exists
     */
    public function logsTableExists(): bool
    {
        try {
            $collection = $this->logCollectionFactory->create();
            $collection->setPageSize(1);
            $collection->load(); // This will throw exception if table doesn't exist
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}