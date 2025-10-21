<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class ElasticEmail extends AbstractProvider
{
    const SMTP_HOST = 'smtp.elasticemail.com';
    const SMTP_PORT = 2525;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.elasticemail.com/v2/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'username_note' => 'Use account email',
            'password_note' => 'Use account password or API key'
        ];
    }
}