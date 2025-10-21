<?php
namespace Corals\SMTP\Plugin\Mail;

use Magento\Framework\Mail\Template\TransportBuilder;
use Corals\SMTP\Helper\Config;

class TransportBuilderPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * After plugin for getTransport method
     *
     * @param TransportBuilder $subject
     * @param \Magento\Framework\Mail\TransportInterface $result
     * @return \Magento\Framework\Mail\TransportInterface
     */
    public function afterGetTransport(
        TransportBuilder $subject,
        $result
    ) {
        return $result;
    }
}