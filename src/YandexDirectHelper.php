<?php

namespace Balerka;

class YandexDirectHelper
{
    private Configuration $configuration;

    public function __construct($login, $token, $locale = 'ru')
    {
        $this->configuration = new Configuration($login, $token, $locale);
    }

    public function statistics(array $campaigns = null, string $startDate = '-28 days', string $endDate = 'yesterday'): Statistics
    {
        return new Statistics($this->configuration, $campaigns, $startDate, $endDate);
    }

    public function strategy(): Strategy
    {
        return new Strategy($this->configuration);
    }
}