<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Pepipost extends AbstractProvider
{
    const SMTP_HOST = 'smtp.pepipost.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.pepipost.com/v5/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'alternate_ports' => [25, 2525, 587],
            'username_note' => 'Use integration name as username',
            'password_note' => 'Use API key as password'
        ];
    }
}