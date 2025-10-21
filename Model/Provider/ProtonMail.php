<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class ProtonMail extends AbstractProvider
{
    const SMTP_HOST = '127.0.0.1';
    const SMTP_PORT = 1025;
    const SMTP_ENCRYPTION = 'none';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'note' => 'Requires ProtonMail Bridge application running locally',
            'bridge_required' => true,
            'bridge_download' => 'https://protonmail.com/bridge'
        ];
    }
    
    public function validateCredentials(string $username, string $password): bool
    {
        // ProtonMail Bridge uses special bridge password
        return filter_var($username, FILTER_VALIDATE_EMAIL) !== false && 
               strlen($password) > 20; // Bridge passwords are typically long
    }
}