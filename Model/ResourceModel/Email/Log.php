<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\ResourceModel\Email;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('corals_smtp_log', 'log_id');
    }
}