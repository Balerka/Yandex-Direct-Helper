<?php

namespace Balerka\YandexDirectHelper;

use Biplane\YandexDirect\Api\V5\Contract\AttributionModelEnum;
use Biplane\YandexDirect\Api\V5\Reports;
use Biplane\YandexDirect\ReportServiceFactory;
use Illuminate\Support\Carbon;

class ReportService
{
    public Reports $report;

    public function __construct(Configuration $configuration)
    {
        $this->report = (new ReportServiceFactory())->createService($configuration->get());
    }

    public function createReportRequest(array $fields, int $days = 28, ?array $goals = null, bool $VAT = false): Reports\ReportRequest
    {
        $startDate = Carbon::now()->subDays($days)->format('Y-m-d');
        $endDate = Carbon::yesterday()->format('Y-m-d');

        $criteria = Reports\SelectionCriteria::create()
//            ->setFilter([
//                Reports\FilterItem::create('CampaignId', 'IN', $this->localCampaigns),
//            ])
            ->setDateFrom($startDate)
            ->setDateTo($endDate);

        $reportDefinition = Reports\ReportDefinition::create()
            ->setReportName('Report_' . uniqid())
            ->setReportType(Reports\ReportTypeEnum::CAMPAIGN_PERFORMANCE_REPORT)
            ->setDateRangeType(Reports\DateRangeTypeEnum::CUSTOM_DATE)
            ->setFieldNames($fields)
            ->setSelectionCriteria($criteria)
            ->setIncludeVAT($VAT);

        if ($goals) {
            $reportDefinition->setGoals($goals)
                ->setAttributionModels([AttributionModelEnum::AUTO]);
        }

        if (in_array(Reports\FieldEnum::DATE, $fields)) {
            $reportDefinition->setOrderBy([
                Reports\OrderBy::create(Reports\FieldEnum::DATE, 'ASCENDING'),
            ]);
        }

        return Reports\ReportRequestBuilder::create()
            ->setReportDefinition($reportDefinition)
            ->returnMoneyInMicros(false)
            ->skipReportHeader(true)
            ->getReportRequest();
    }

    public function parseReportResult(string $result, array $fields): array
    {
        $lines = explode("\n", $result);

        array_shift($lines);

        $keys = $fields;
        $parsedData = [];

        foreach ($lines as $line) {
            if (!empty($line)) {
                $values = explode("\t", $line);

                if (count($keys) !== count($values)) {
                    // Пропускаем строки с несовпадающим количеством элементов
                    continue;
                }

                $parsedData[] = array_combine($keys, explode("\t", $line));
            }
        }

        return $parsedData;
    }
}