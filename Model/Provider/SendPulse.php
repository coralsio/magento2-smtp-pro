<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class SendPulse extends AbstractProvider
{
    const SMTP_HOST = 'smtp-pulse.com';
    const SMTP_PORT = 2525;
    const SMTP_ENCRYPTION = 'ssl';
    const API_ENDPOINT = 'https://api.sendpulse.com/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'alternate_ports' => [465, 2525],
            'note' => 'Generate SMTP credentials in SendPulse account settings'
        ];
    }
}