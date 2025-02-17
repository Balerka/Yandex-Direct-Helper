<?php

namespace Balerka;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Biplane\YandexDirect\Api\V5\Reports;

class Expenses
{
    private ReportService $reportService;

    public function __construct(Configuration $configuration, $campaigns = null)
    {
        $this->reportService = new ReportService($configuration, $campaigns);
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
        ], 28, VAT: true);

        try {
            $result = $this->reportService->report->getReady($reportRequest);

            return $this->reportService->parseReportResult($result->getAsString(), ['campaign', 'date', 'clicks', 'sum']);
        } catch (ClientExceptionInterface $e) {
            throw new Exception('YandexDirect report error: ' . $e->getMessage());
        }
    }
}