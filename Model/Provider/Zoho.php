<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

class Zoho extends AbstractProvider
{
    const SMTP_HOST = 'smtp.zoho.com';
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
            'alternate_hosts' => [
                'smtp.zoho.eu',     // Europe
                'smtp.zoho.in',     // India
                'smtp.zoho.com.au', // Australia
                'smtp.zoho.jp'      // Japan
            ],
            'note' => 'Use region-specific host if needed'
        ];
    }
}