<?php

namespace Balerka;

use Biplane\YandexDirect\Config;
use Biplane\YandexDirect\ConfigBuilder;

class Configuration
{
    private string $token;
    private string $login;
    private string $locale;

    public function __construct($login, $token, $locale = 'ru')
    {
        $this->token = $token;
        $this->login = $login;
        $this->locale = $locale;
    }

    public function get(): Config
    {
        return ConfigBuilder::create()
            ->setAccessToken($this->token)
            ->setClientLogin($this->login)
            ->setLocale($this->locale)
            ->getConfig();
    }
}