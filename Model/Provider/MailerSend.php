<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class MailerSend extends AbstractProvider
{
    const SMTP_HOST = 'smtp.mailersend.net';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.mailersend.com/v1/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'note' => 'Generate SMTP credentials in MailerSend dashboard'
        ];
    }
}