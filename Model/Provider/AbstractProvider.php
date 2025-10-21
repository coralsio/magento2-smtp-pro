<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Provider;

abstract class AbstractProvider
{
    /**
     * Get provider SMTP configuration
     */
    abstract public function getConfiguration(): array;
    
    /**
     * Validate provider-specific credentials
     */
    public function validateCredentials(string $username, string $password): bool
    {
        return !empty($username) && !empty($password);
    }
    
    /**
     * Get provider-specific headers
     */
    public function getCustomHeaders(): array
    {
        return [];
    }
    
    /**
     * Provider-specific email preparation
     */
    public function prepareEmail(array &$emailData): void
    {
        // Override in provider if needed
    }
}