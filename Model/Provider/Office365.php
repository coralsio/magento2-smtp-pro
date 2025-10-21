<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Office365 extends AbstractProvider
{
    const SMTP_HOST = 'smtp.office365.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login'
        ];
    }
    
    public function validateCredentials(string $username, string $password): bool
    {
        // Office 365 requires full email as username
        return filter_var($username, FILTER_VALIDATE_EMAIL) !== false && !empty($password);
    }
}