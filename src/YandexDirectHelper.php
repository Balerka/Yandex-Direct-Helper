<?php

namespace Balerka;

class YandexDirectHelper
{
    private Configuration $configuration;

    public function __construct($login, $token, $locale = 'ru')
    {
        $this->configuration = new Configuration($login, $token, $locale);
    }

    public function expenses(): Expenses
    {
        return new Expenses($this->configuration);
    }

    public function strategy(): Strategy
    {
        return new Strategy($this->configuration);
    }
}