<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class SocketLabs extends AbstractProvider
{
    const SMTP_HOST = 'smtp.socketlabs.com';
    const SMTP_PORT = 2525;
    const SMTP_ENCRYPTION = 'tls';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'alternate_ports' => [25, 2525, 587],
            'note' => 'Use Server ID as username and API key as password'
        ];
    }
}