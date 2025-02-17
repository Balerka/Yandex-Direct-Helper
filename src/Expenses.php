<?php

namespace Balerka\LaravelYandexDirectHelper;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Biplane\YandexDirect\Api\V5\Reports;

class Expenses
{
    /**
     * @throws Exception
     */
    public function get(): ?array
    {
        $reportService = (new ReportService());

        $reportRequest = $reportService->createReportRequest([
            Reports\FieldEnum::CAMPAIGN_ID,
            Reports\FieldEnum::DATE,
            Reports\FieldEnum::CLICKS,
            Reports\FieldEnum::COST,
        ], VAT: true);

        try {
            $result = $reportService->report->getReady($reportRequest);

            return $reportService->parseReportResult($result->getAsString(), ['campaign', 'date', 'clicks', 'sum']);
        } catch (ClientExceptionInterface $e) {
            throw new Exception('YandexDirect report error: ' . $e->getMessage());

//            Log::error('YandexDirect report error: ' . $e->getMessage());
//            return null;
        }
    }
}