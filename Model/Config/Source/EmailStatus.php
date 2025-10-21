<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EmailStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sent', 'label' => __('Sent')],
            ['value' => 'failed', 'label' => __('Failed')],
            ['value' => 'queued', 'label' => __('Queued')],
            ['value' => 'processing', 'label' => __('Processing')]
        ];
    }
}