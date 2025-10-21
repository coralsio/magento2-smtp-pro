<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Encryption implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'ssl', 'label' => __('SSL')],
            ['value' => 'tls', 'label' => __('TLS')],
            ['value' => 'starttls', 'label' => __('STARTTLS')]
        ];
    }
}