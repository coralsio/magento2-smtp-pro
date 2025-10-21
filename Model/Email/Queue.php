<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Email;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Corals\SMTP\Model\ResourceModel\Email\Queue as QueueResource;
use Corals\SMTP\Model\ResourceModel\Email\Queue\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Queue extends AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    protected DateTime $dateTime;
    protected CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        CollectionFactory $collectionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateTime = $dateTime;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init(QueueResource::class);
    }

    public function add(array $emailData): void
    {
        $this->setData([
            'to' => $emailData['to'] ?? '',
            'from' => $emailData['from'] ?? '',
            'subject' => $emailData['subject'] ?? '',
            'body' => $emailData['body'] ?? '',
            'message_serialized' => $emailData['message'] ?? '',
            'status' => self::STATUS_PENDING,
            'priority' => $emailData['priority'] ?? 3,
            'attempts' => 0,
            'scheduled_at' => $emailData['scheduled_at'] ?? $this->dateTime->gmtDate(),
            'created_at' => $this->dateTime->gmtDate()
        ])->save();
    }

    public function getPendingEmails(int $limit): \Magento\Framework\Data\Collection\AbstractDb
    {
        $collection = $this->collectionFactory->create();
        
        $collection->addFieldToFilter('status', ['in' => [self::STATUS_PENDING, self::STATUS_FAILED]])
            ->addFieldToFilter('scheduled_at', ['lteq' => $this->dateTime->gmtDate()])
            ->setOrder('priority', 'ASC')
            ->setOrder('scheduled_at', 'ASC')
            ->setPageSize($limit);
        
        return $collection;
    }

    public function markAsProcessing(): void
    {
        $this->setStatus(self::STATUS_PROCESSING)
            ->setProcessedAt($this->dateTime->gmtDate())
            ->save();
    }

    public function markAsSent(): void
    {
        $this->setStatus(self::STATUS_SENT)
            ->setSentAt($this->dateTime->gmtDate())
            ->save();
    }

    public function markAsFailed(string $error, int $maxRetries = 3): void
    {
        $attempts = (int)$this->getAttempts() + 1;
        
        if ($attempts >= $maxRetries) {
            $this->setStatus(self::STATUS_FAILED);
        } else {
            $this->setStatus(self::STATUS_PENDING);
            $nextAttempt = $this->dateTime->gmtTimestamp() + (60 * 5 * $attempts);
            $this->setScheduledAt($this->dateTime->gmtDate(null, $nextAttempt));
        }
        
        $this->setAttempts($attempts)
            ->setLastError($error)
            ->save();
    }

    public function cleanOldQueues(int $days = 30): int
    {
        $collection = $this->collectionFactory->create();
        
        $date = $this->dateTime->gmtTimestamp() - ($days * 86400);
        $collection->addFieldToFilter('created_at', ['lt' => $this->dateTime->gmtDate(null, $date)])
            ->addFieldToFilter('status', ['in' => [self::STATUS_SENT, self::STATUS_FAILED]]);
        
        $count = $collection->getSize();
        
        foreach ($collection as $item) {
            $item->delete();
        }
        
        return $count;
    }
}