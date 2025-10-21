<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Email;

use Magento\Framework\Model\AbstractModel;
use Corals\SMTP\Model\ResourceModel\Email\Log as LogResource;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Corals\SMTP\Model\ResourceModel\Email\Log\CollectionFactory;

class Logger extends AbstractModel
{
    protected DateTime $dateTime;
    protected CollectionFactory $collectionFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        DateTime $dateTime,
        CollectionFactory $collectionFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateTime = $dateTime;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init(LogResource::class);
    }

    public function logEmail(array $data): void
    {
        $this->setData([
            'to' => $data['to'] ?? '',
            'from' => $data['from'] ?? '',
            'cc' => $data['cc'] ?? '',
            'bcc' => $data['bcc'] ?? '',
            'reply_to' => $data['reply_to'] ?? '',
            'subject' => $data['subject'] ?? '',
            'body' => $data['body'] ?? '',
            'status' => $data['status'] ?? 'unknown',
            'error_message' => $data['error'] ?? null,
            'provider' => $data['provider'] ?? null,
            'duration' => $data['duration'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => $this->dateTime->gmtDate()
        ])->save();
    }

    public function getHourlyCount(): int
    {
        $collection = $this->collectionFactory->create();
        
        $hourAgo = $this->dateTime->gmtTimestamp() - 3600;
        $collection->addFieldToFilter('created_at', ['gteq' => $this->dateTime->gmtDate(null, $hourAgo)])
            ->addFieldToFilter('status', 'sent');
        
        return $collection->getSize();
    }

    public function getDailyStats(): array
    {
        $collection = $this->collectionFactory->create();
        
        $dayAgo = $this->dateTime->gmtTimestamp() - 86400;
        $collection->addFieldToFilter('created_at', ['gteq' => $this->dateTime->gmtDate(null, $dayAgo)]);
        
        $stats = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'providers' => []
        ];
        
        foreach ($collection as $log) {
            $stats['total']++;
            
            if ($log->getStatus() === 'sent') {
                $stats['sent']++;
            } elseif ($log->getStatus() === 'failed') {
                $stats['failed']++;
            }
            
            $provider = $log->getProvider();
            if ($provider) {
                if (!isset($stats['providers'][$provider])) {
                    $stats['providers'][$provider] = 0;
                }
                $stats['providers'][$provider]++;
            }
        }
        
        return $stats;
    }

    public function cleanOldLogs(int $days): int
    {
        $collection = $this->collectionFactory->create();
        
        $date = $this->dateTime->gmtTimestamp() - ($days * 86400);
        $collection->addFieldToFilter('created_at', ['lt' => $this->dateTime->gmtDate(null, $date)]);
        
        $count = $collection->getSize();
        
        foreach ($collection as $item) {
            $item->delete();
        }
        
        return $count;
    }

    public function getRecentLogs(int $limit = 100): \Magento\Framework\Data\Collection\AbstractDb
    {
        $collection = $this->collectionFactory->create();
        
        $collection->setOrder('created_at', 'DESC')
            ->setPageSize($limit);
        
        return $collection;
    }

    public function searchLogs(array $filters): \Magento\Framework\Data\Collection\AbstractDb
    {
        $collection = $this->collectionFactory->create();
        
        if (!empty($filters['to'])) {
            $collection->addFieldToFilter('to', ['like' => '%' . $filters['to'] . '%']);
        }
        
        if (!empty($filters['from'])) {
            $collection->addFieldToFilter('from', ['like' => '%' . $filters['from'] . '%']);
        }
        
        if (!empty($filters['subject'])) {
            $collection->addFieldToFilter('subject', ['like' => '%' . $filters['subject'] . '%']);
        }
        
        if (!empty($filters['status'])) {
            $collection->addFieldToFilter('status', $filters['status']);
        }
        
        if (!empty($filters['date_from'])) {
            $collection->addFieldToFilter('created_at', ['gteq' => $filters['date_from']]);
        }
        
        if (!empty($filters['date_to'])) {
            $collection->addFieldToFilter('created_at', ['lteq' => $filters['date_to']]);
        }
        
        $collection->setOrder('created_at', 'DESC');
        
        return $collection;
    }
}