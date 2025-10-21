<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogLevel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'error', 'label' => __('Error Only')],
            ['value' => 'warning', 'label' => __('Warning and Above')],
            ['value' => 'info', 'label' => __('Info and Above')],
            ['value' => 'debug', 'label' => __('Debug (All)')],
        ];
    }
}