<?php
declare(strict_types=1);

namespace Corals\SMTP\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Corals\SMTP\Helper\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\TransportInterfaceFactory;

class TestEmailCommand extends Command
{
    private TransportBuilder $transportBuilder;
    private Config $config;
    private StoreManagerInterface $storeManager;
    private State $appState;
    private EmailMessageInterfaceFactory $emailMessageFactory;
    private AddressConverter $addressConverter;
    private TransportInterfaceFactory $transportFactory;

    public function __construct(
        TransportBuilder $transportBuilder,
        Config $config,
        StoreManagerInterface $storeManager,
        State $appState,
        EmailMessageInterfaceFactory $emailMessageFactory,
        AddressConverter $addressConverter,
        TransportInterfaceFactory $transportFactory,
        string $name = null
    ) {
        parent::__construct($name);
        $this->transportBuilder = $transportBuilder;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        $this->emailMessageFactory = $emailMessageFactory;
        $this->addressConverter = $addressConverter;
        $this->transportFactory = $transportFactory;
    }

    protected function configure()
    {
        $this->setName('corals:smtp:test')
            ->setDescription('Test SMTP configuration by sending a test email')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Email address to send test email to'
            )
            ->addOption(
                'store',
                null,
                InputOption::VALUE_OPTIONAL,
                'Store ID to use for configuration',
                0
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        $storeId = (int)$input->getOption('store');

        $output->writeln('<info>Testing SMTP configuration...</info>');
        $output->writeln('');

        if (!$this->config->isEnabled($storeId)) {
            $output->writeln('<error>SMTP is not enabled for store ' . $storeId . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('Configuration:');
        $output->writeln('  Provider: ' . $this->config->getProvider($storeId));
        $output->writeln('  Host: ' . $this->config->getHost($storeId));
        $output->writeln('  Port: ' . $this->config->getPort($storeId));
        $output->writeln('  Encryption: ' . $this->config->getEncryption($storeId));
        $output->writeln('  From: ' . $this->config->getFromEmail($storeId) . ' (' . $this->config->getFromName($storeId) . ')');
        $output->writeln('');

        try {
            // Set the area code to avoid "Area code is not set" error
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
            
            $store = $this->storeManager->getStore($storeId);
            
            $output->writeln('Sending test email to ' . $email . '...');
            
            // Create a direct email message without templates
            $subject = 'SMTP Test Email from ' . $store->getName();
            $body = "This is a test email sent via Corals SMTP Pro.\n\n";
            $body .= "Configuration Details:\n";
            $body .= "Provider: " . $this->config->getProvider($storeId) . "\n";
            $body .= "Host: " . $this->config->getHost($storeId) . "\n";
            $body .= "Port: " . $this->config->getPort($storeId) . "\n";
            $body .= "Encryption: " . $this->config->getEncryption($storeId) . "\n";
            $body .= "Sent at: " . date('Y-m-d H:i:s') . "\n\n";
            $body .= "If you received this email, your SMTP configuration is working correctly!";

            // Create email message directly
            $fromEmail = $this->config->getFromEmail($storeId) ?: 'test@example.com';
            $fromName = $this->config->getFromName($storeId) ?: 'SMTP Test';
            
            $from = $this->addressConverter->convert($fromEmail, $fromName);
            $to = $this->addressConverter->convert($email);
            
            $emailMessage = $this->emailMessageFactory->create([
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'body' => $body
            ]);
            
            $transport = $this->transportFactory->create(['message' => $emailMessage]);

            $transport->sendMessage();
            
            $output->writeln('<info>✓ Test email sent successfully!</info>');
            $output->writeln('');
            
            if ($this->config->isDkimEnabled($storeId)) {
                $output->writeln('<comment>DKIM signing is enabled</comment>');
            }
            
            if ($this->config->isSpfCheckEnabled($storeId)) {
                $output->writeln('<comment>SPF checking is enabled</comment>');
            }
            
            if ($this->config->isDmarcCheckEnabled($storeId)) {
                $output->writeln('<comment>DMARC checking is enabled</comment>');
            }
            
            if ($this->config->isTrackingEnabled($storeId)) {
                $output->writeln('<comment>Email tracking is enabled</comment>');
            }
            
            if ($this->config->isQueueEnabled($storeId)) {
                $output->writeln('<comment>Email queue is enabled</comment>');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Failed to send test email</error>');
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            
            if ($output->isVerbose()) {
                $output->writeln('');
                $output->writeln('Stack trace:');
                $output->writeln($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }
    }
}