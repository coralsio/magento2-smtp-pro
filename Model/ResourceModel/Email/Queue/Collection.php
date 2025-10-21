<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\ResourceModel\Email\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Corals\SMTP\Model\Email\Queue as QueueModel;
use Corals\SMTP\Model\ResourceModel\Email\Queue as QueueResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(QueueModel::class, QueueResource::class);
    }
}