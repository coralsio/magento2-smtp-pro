<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\ResourceModel\Email\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Corals\SMTP\Model\Email\Logger;
use Corals\SMTP\Model\ResourceModel\Email\Log as LogResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Logger::class, LogResource::class);
    }
}