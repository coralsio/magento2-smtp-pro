<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

/**
 * Custom SMTP provider for user-defined SMTP servers
 */
class Custom extends AbstractProvider
{
    public function getConfiguration(): array
    {
        // Return empty array as custom provider uses user-defined settings
        return [
            'host' => null, // User must provide
            'port' => null, // User must provide
            'encryption' => null, // User must provide
            'auth_required' => null, // User defines
            'auth_type' => 'login',
            'note' => 'Configure all SMTP settings manually'
        ];
    }
    
    public function validateCredentials(string $username, string $password): bool
    {
        // For custom provider, just check if credentials are provided when needed
        // Actual validation happens during connection
        return true;
    }
}