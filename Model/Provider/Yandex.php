<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Yandex extends AbstractProvider
{
    const SMTP_HOST = 'smtp.yandex.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'alternate_ports' => [25, 465, 587],
            'note' => 'Enable SMTP access in Yandex Mail settings'
        ];
    }
}