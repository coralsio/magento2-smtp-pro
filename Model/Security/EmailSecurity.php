<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Security;

use Corals\SMTP\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Email security manager for DKIM, SPF, and DMARC
 */
class EmailSecurity
{
    private Config $config;
    private LoggerInterface $logger;
    
    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Apply security features to email
     */
    public function secureEmail(array &$headers, string &$body): void
    {
        // Add DKIM signature if enabled
        if ($this->config->isDkimEnabled()) {
            $this->addDkimSignature($headers, $body);
        }
        
        // Add SPF alignment header
        if ($this->config->isSpfCheckEnabled()) {
            $this->addSpfHeaders($headers);
        }
        
        // Add DMARC alignment
        if ($this->config->isDmarcCheckEnabled()) {
            $this->addDmarcHeaders($headers);
        }
    }
    
    /**
     * Add DKIM signature
     */
    private function addDkimSignature(array &$headers, string $body): void
    {
        try {
            $privateKey = $this->config->getDkimPrivateKey();
            if (!$privateKey) {
                $this->logger->warning('DKIM enabled but private key not configured');
                return;
            }
            
            $domain = $this->config->getDkimDomain();
            $selector = $this->config->getDkimSelector();
            
            // Create signature
            $signature = $this->createDkimSignature(
                $domain,
                $selector, 
                $privateKey,
                $headers,
                $body
            );
            
            if ($signature) {
                $headers['DKIM-Signature'] = $signature;
                $this->logger->debug('DKIM signature added', [
                    'domain' => $domain,
                    'selector' => $selector
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to add DKIM signature', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create DKIM signature (simplified version)
     */
    private function createDkimSignature(
        string $domain,
        string $selector,
        string $privateKey,
        array $headers,
        string $body
    ): ?string {
        // This is a simplified implementation
        // In production, use a proper DKIM library
        
        $bodyHash = base64_encode(hash('sha256', $body, true));
        
        $dkimHeaders = [
            'v=1',
            'a=rsa-sha256',
            'd=' . $domain,
            's=' . $selector,
            't=' . time(),
            'bh=' . $bodyHash,
            'h=From:To:Subject:Date'
        ];
        
        return implode('; ', $dkimHeaders);
    }
    
    /**
     * Add SPF headers
     */
    private function addSpfHeaders(array &$headers): void
    {
        $headers['Sender'] = $this->config->getFromEmail();
        $headers['Return-Path'] = $this->config->getFromEmail();
    }
    
    /**
     * Add DMARC headers
     */
    private function addDmarcHeaders(array &$headers): void
    {
        // Ensure From domain alignment
        $fromEmail = $this->config->getFromEmail();
        if ($fromEmail) {
            $headers['From'] = $this->config->getFromName() . ' <' . $fromEmail . '>';
        }
    }
    
    /**
     * Verify email security
     */
    public function verifyEmailSecurity(string $email): array
    {
        $results = [
            'spf' => null,
            'dkim' => null,
            'dmarc' => null,
            'score' => 0
        ];
        
        $domain = substr($email, strpos($email, '@') + 1);
        
        // Check SPF
        if ($this->config->isSpfCheckEnabled()) {
            $results['spf'] = $this->checkSpfRecord($domain);
            if ($results['spf']['valid']) {
                $results['score'] += 30;
            }
        }
        
        // Check DKIM
        if ($this->config->isDkimEnabled()) {
            $results['dkim'] = $this->checkDkimRecord($domain);
            if ($results['dkim']['valid']) {
                $results['score'] += 35;
            }
        }
        
        // Check DMARC
        if ($this->config->isDmarcCheckEnabled()) {
            $results['dmarc'] = $this->checkDmarcRecord($domain);
            if ($results['dmarc']['valid']) {
                $results['score'] += 35;
            }
        }
        
        return $results;
    }
    
    /**
     * Check SPF record
     */
    private function checkSpfRecord(string $domain): array
    {
        try {
            $records = dns_get_record($domain, DNS_TXT);
            
            foreach ($records as $record) {
                if (strpos($record['txt'], 'v=spf1') === 0) {
                    return [
                        'valid' => true,
                        'record' => $record['txt'],
                        'message' => 'SPF record found'
                    ];
                }
            }
            
            return [
                'valid' => false,
                'record' => null,
                'message' => 'No SPF record found'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'record' => null,
                'message' => 'SPF check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check DKIM record
     */
    private function checkDkimRecord(string $domain): array
    {
        try {
            $selector = $this->config->getDkimSelector();
            $dkimDomain = $selector . '._domainkey.' . $domain;
            $records = @dns_get_record($dkimDomain, DNS_TXT);
            
            if ($records) {
                foreach ($records as $record) {
                    if (strpos($record['txt'], 'v=DKIM1') !== false) {
                        return [
                            'valid' => true,
                            'record' => $record['txt'],
                            'message' => 'DKIM record found',
                            'selector' => $selector
                        ];
                    }
                }
            }
            
            return [
                'valid' => false,
                'record' => null,
                'message' => 'No DKIM record found for selector: ' . $selector,
                'selector' => $selector
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'record' => null,
                'message' => 'DKIM check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check DMARC record
     */
    private function checkDmarcRecord(string $domain): array
    {
        try {
            $dmarcDomain = '_dmarc.' . $domain;
            $records = @dns_get_record($dmarcDomain, DNS_TXT);
            
            if ($records) {
                foreach ($records as $record) {
                    if (strpos($record['txt'], 'v=DMARC1') === 0) {
                        $policy = $this->parseDmarcPolicy($record['txt']);
                        
                        return [
                            'valid' => true,
                            'record' => $record['txt'],
                            'policy' => $policy,
                            'message' => 'DMARC record found'
                        ];
                    }
                }
            }
            
            return [
                'valid' => false,
                'record' => null,
                'policy' => null,
                'message' => 'No DMARC record found'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'record' => null,
                'policy' => null,
                'message' => 'DMARC check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse DMARC policy
     */
    private function parseDmarcPolicy(string $record): array
    {
        $policy = [
            'policy' => 'none',
            'subdomain_policy' => null,
            'percentage' => 100
        ];
        
        $tags = explode(';', $record);
        
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (strpos($tag, '=') !== false) {
                list($key, $value) = explode('=', $tag, 2);
                
                switch (trim($key)) {
                    case 'p':
                        $policy['policy'] = trim($value);
                        break;
                    case 'sp':
                        $policy['subdomain_policy'] = trim($value);
                        break;
                    case 'pct':
                        $policy['percentage'] = (int)trim($value);
                        break;
                }
            }
        }
        
        return $policy;
    }
    
    /**
     * Generate DNS records for email authentication
     */
    public function generateDnsRecords(): array
    {
        $domain = $this->config->getDkimDomain();
        $records = [];
        
        // SPF record
        $records['spf'] = [
            'type' => 'TXT',
            'host' => $domain,
            'value' => 'v=spf1 include:' . $this->config->getProvider() . '.com ~all',
            'description' => 'SPF record for email authentication'
        ];
        
        // DKIM record
        if ($this->config->isDkimEnabled()) {
            $selector = $this->config->getDkimSelector();
            $records['dkim'] = [
                'type' => 'TXT',
                'host' => $selector . '._domainkey.' . $domain,
                'value' => 'v=DKIM1; k=rsa; p=[YOUR_PUBLIC_KEY]',
                'description' => 'DKIM record for email signing'
            ];
        }
        
        // DMARC record
        $records['dmarc'] = [
            'type' => 'TXT',
            'host' => '_dmarc.' . $domain,
            'value' => 'v=DMARC1; p=quarantine; pct=100; rua=mailto:dmarc@' . $domain,
            'description' => 'DMARC policy record'
        ];
        
        return $records;
    }
}