<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class SparkPost extends AbstractProvider
{
    const SMTP_HOST = 'smtp.sparkpostmail.com';
    const SMTP_PORT = 587;
    const SMTP_ENCRYPTION = 'tls';
    const API_ENDPOINT = 'https://api.sparkpost.com/api/v1/';
    
    public function getConfiguration(): array
    {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'encryption' => self::SMTP_ENCRYPTION,
            'auth_required' => true,
            'auth_type' => 'login',
            'username_note' => 'Use "SMTP_Injection" as username',
            'password_note' => 'Use API key as password'
        ];
    }
    
    public function validateCredentials(string $username, string $password): bool
    {
        // SparkPost uses fixed username "SMTP_Injection"
        return $username === 'SMTP_Injection' && !empty($password);
    }
}