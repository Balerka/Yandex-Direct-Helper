<?php

namespace Balerka;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Biplane\YandexDirect\Api\V5\Reports;

class Statistics
{
    private ReportService $reportService;
    public string $startDate;
    public string $endDate;

    public function __construct(Configuration $configuration, $campaigns = null, string $startDate = '-28 days', string $endDate = 'yesterday')
    {
        $this->reportService = new ReportService($configuration, $campaigns);
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * @throws Exception
     */
    public function get(): ?array
    {
        $reportRequest = $this->reportService->createReportRequest([
            Reports\FieldEnum::CAMPAIGN_ID,
            Reports\FieldEnum::DATE,
            Reports\FieldEnum::CLICKS,
            Reports\FieldEnum::COST,
        ], $this->startDate, $this->endDate, VAT: true);

        try {
            $result = $this->reportService->report->getReady($reportRequest);

            return $this->reportService->parseReportResult($result->getAsString(), ['campaign', 'date', 'clicks', 'sum']);
        } catch (ClientExceptionInterface $e) {
            throw new Exception('YandexDirect report error: ' . $e->getMessage());
        }
    }
}