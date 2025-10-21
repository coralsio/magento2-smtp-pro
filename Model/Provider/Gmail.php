<?php
namespace Corals\SMTP\Model\Provider;

class Gmail
{
    public function getConfig()
    {
        return [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'auth' => 'login',
            'ssl' => 'tls'
        ];
    }
}