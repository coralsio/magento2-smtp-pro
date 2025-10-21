<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Mailjet extends AbstractProvider
{
    const SMTP_HOST = 'in-v3.mailjet.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.mailjet.com/v3.1/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'username_note' => 'Use API Key as username',
            'password_note' => 'Use Secret Key as password'
        ];
    }
}