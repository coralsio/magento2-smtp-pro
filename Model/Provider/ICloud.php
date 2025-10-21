<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class ICloud extends AbstractProvider
{
    const SMTP_HOST = 'smtp.mail.me.com';
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
            'username_note' => 'Use iCloud email address',
            'password_note' => 'Use app-specific password (not iCloud password)',
            'note' => 'Requires app-specific password from Apple ID settings'
        ];
    }
}