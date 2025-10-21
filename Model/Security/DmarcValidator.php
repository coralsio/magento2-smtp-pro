<?php
declare(strict_types=1);

namespace Corals\SMTP\Model\Security;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

class DmarcValidator
{
    private LoggerInterface $logger;
    private SpfValidator $spfValidator;

    public function __construct(
        LoggerInterface $logger,
        SpfValidator $spfValidator
    ) {
        $this->logger = $logger;
        $this->spfValidator = $spfValidator;
    }

    public function validate(string $fromEmail, bool $dkimEnabled, bool $spfEnabled): void
    {
        if (empty($fromEmail) || strpos($fromEmail, '@') === false) {
            throw new MailException(new Phrase('Invalid from email address'));
        }

        $domain = substr($fromEmail, strpos($fromEmail, '@') + 1);
        $dmarcRecord = $this->getDmarcRecord($domain);

        if (empty($dmarcRecord)) {
            $this->logger->info('No DMARC record found for domain', ['domain' => $domain]);
            return;
        }

        $policy = $this->parseDmarcRecord($dmarcRecord);

        if ($policy['p'] === 'none') {
            $this->logger->debug('DMARC policy is set to none, validation passed', [
                'domain' => $domain,
                'policy' => $policy
            ]);
            return;
        }

        $alignmentPassed = false;

        if ($spfEnabled && $this->checkSpfAlignment($fromEmail, $policy)) {
            $alignmentPassed = true;
        }

        if ($dkimEnabled && $this->checkDkimAlignment($domain, $policy)) {
            $alignmentPassed = true;
        }

        if (!$alignmentPassed) {
            if ($policy['p'] === 'reject') {
                throw new MailException(
                    new Phrase('DMARC validation failed for domain %1 with reject policy', [$domain])
                );
            } elseif ($policy['p'] === 'quarantine') {
                $this->logger->warning('DMARC validation failed with quarantine policy', [
                    'domain' => $domain,
                    'policy' => $policy
                ]);
            }
        }

        $this->logger->debug('DMARC validation completed', [
            'domain' => $domain,
            'policy' => $policy,
            'passed' => $alignmentPassed
        ]);
    }

    public function getDmarcRecord(string $domain): ?string
    {
        try {
            $dmarcDomain = '_dmarc.' . $domain;
            $txtRecords = dns_get_record($dmarcDomain, DNS_TXT);
            
            foreach ($txtRecords as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=DMARC1') === 0) {
                    return $record['txt'];
                }
            }

            $parts = explode('.', $domain);
            while (count($parts) > 2) {
                array_shift($parts);
                $parentDomain = implode('.', $parts);
                $dmarcDomain = '_dmarc.' . $parentDomain;
                
                $txtRecords = dns_get_record($dmarcDomain, DNS_TXT);
                foreach ($txtRecords as $record) {
                    if (isset($record['txt']) && strpos($record['txt'], 'v=DMARC1') === 0) {
                        return $record['txt'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve DMARC record', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function parseDmarcRecord(string $dmarcRecord): array
    {
        $policy = [
            'v' => 'DMARC1',
            'p' => 'none',
            'sp' => null,
            'rua' => null,
            'ruf' => null,
            'adkim' => 'r',
            'aspf' => 'r',
            'pct' => 100,
            'fo' => '0'
        ];

        $tags = preg_split('/;\s*/', $dmarcRecord);

        foreach ($tags as $tag) {
            if (empty($tag)) {
                continue;
            }

            $parts = explode('=', $tag, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                if (array_key_exists($key, $policy)) {
                    if ($key === 'pct') {
                        $policy[$key] = (int)$value;
                    } else {
                        $policy[$key] = $value;
                    }
                }
            }
        }

        return $policy;
    }

    private function checkSpfAlignment(string $fromEmail, array $policy): bool
    {
        $domain = substr($fromEmail, strpos($fromEmail, '@') + 1);
        $spfRecord = $this->spfValidator->getSpfRecord($domain);
        
        if (empty($spfRecord)) {
            return false;
        }

        $alignment = $policy['aspf'] ?? 'r';
        
        if ($alignment === 's') {
            return true;
        }

        $organizationalDomain = $this->getOrganizationalDomain($domain);
        $spfDomain = $this->getSpfDomain($spfRecord);
        
        if ($spfDomain) {
            $spfOrgDomain = $this->getOrganizationalDomain($spfDomain);
            return $organizationalDomain === $spfOrgDomain;
        }

        return true;
    }

    private function checkDkimAlignment(string $domain, array $policy): bool
    {
        $alignment = $policy['adkim'] ?? 'r';
        
        if ($alignment === 's') {
            return true;
        }

        return true;
    }

    private function getOrganizationalDomain(string $domain): string
    {
        $publicSuffixList = [
            'com', 'org', 'net', 'gov', 'edu', 'mil',
            'co.uk', 'org.uk', 'ac.uk', 'gov.uk',
            'co.jp', 'or.jp', 'ne.jp',
            'com.au', 'org.au', 'gov.au',
            'de', 'fr', 'it', 'es', 'nl', 'be', 'ch', 'at'
        ];

        $parts = explode('.', $domain);
        $count = count($parts);

        if ($count <= 2) {
            return $domain;
        }

        for ($i = 1; $i < $count; $i++) {
            $suffix = implode('.', array_slice($parts, $i));
            if (in_array($suffix, $publicSuffixList)) {
                return implode('.', array_slice($parts, $i - 1));
            }
        }

        return implode('.', array_slice($parts, -2));
    }

    private function getSpfDomain(string $spfRecord): ?string
    {
        if (preg_match('/include:([^\s]+)/', $spfRecord, $matches)) {
            return $matches[1];
        }

        if (preg_match('/redirect=([^\s]+)/', $spfRecord, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function generateDmarcRecord(string $domain, string $reportEmail): string
    {
        $dmarcRecord = sprintf(
            'v=DMARC1; p=quarantine; sp=quarantine; rua=mailto:%s; ruf=mailto:%s; adkim=r; aspf=r; pct=100; fo=1',
            $reportEmail,
            $reportEmail
        );
        
        return sprintf('_dmarc.%s IN TXT "%s"', $domain, $dmarcRecord);
    }

    public function sendDmarcReport(array $reportData, string $reportEmail): void
    {
        $this->logger->info('DMARC report generated', [
            'report' => $reportData,
            'email' => $reportEmail
        ]);
    }
}