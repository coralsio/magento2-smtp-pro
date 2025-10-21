<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Sendinblue extends AbstractProvider
{
    const SMTP_HOST = 'smtp-relay.sendinblue.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.sendinblue.com/v3/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'note' => 'Use your Sendinblue login email and SMTP password (not account password)'
        ];
    }
}