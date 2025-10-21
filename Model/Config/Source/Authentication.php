<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Authentication implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'login', 'label' => __('LOGIN')],
            ['value' => 'plain', 'label' => __('PLAIN')],
            ['value' => 'cram-md5', 'label' => __('CRAM-MD5')],
            ['value' => 'oauth2', 'label' => __('OAuth 2.0')],
            ['value' => 'xoauth2', 'label' => __('XOAUTH2')],
            ['value' => 'ntlm', 'label' => __('NTLM')]
        ];
    }
}