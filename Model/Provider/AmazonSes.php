<?php
namespace Corals\SMTP\Model\Provider;

class AmazonSes
{
    public function getConfig()
    {
        return [
            'host' => 'email-smtp.us-east-1.amazonaws.com',
            'port' => 587,
            'auth' => 'login',
            'ssl' => 'tls'
        ];
    }
}