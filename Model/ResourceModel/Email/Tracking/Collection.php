<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\ResourceModel\Email\Tracking;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Corals\SMTP\Model\Email\Tracker;
use Corals\SMTP\Model\ResourceModel\Email\Tracking as TrackingResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Tracker::class, TrackingResource::class);
    }
}