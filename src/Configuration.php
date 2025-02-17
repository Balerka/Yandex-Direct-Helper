<?php

namespace Balerka\LaravelYandexDirectStrategyAutoUpdater;

use Biplane\YandexDirect\ConfigBuilder;

class Configuration
{
    public static function get()
    {
        return ConfigBuilder::create()
            ->setAccessToken(config('services.yandex-direct.token'))
            ->setClientLogin(config('services.yandex-direct.login'))
            ->setLocale(config('app.locale'))
            ->getConfig();
    }
}