<?php

namespace Balerka\YandexDirectHelper;

use Biplane\YandexDirect\Api\V5\Campaigns;
use Biplane\YandexDirect\Api\V5\Contract;
use Biplane\YandexDirect\Api\V5\Contract\UnifiedCampaignNetworkStrategyTypeEnum;
use Biplane\YandexDirect\Api\V5\Reports;
use Biplane\YandexDirect\ApiServiceFactory;
use Psr\Http\Client\ClientExceptionInterface;

class Strategy
{
    private Campaigns $campaignService;

    public function __construct(Configuration $configuration)
    {
        $this->campaignService = (new ApiServiceFactory())->createService($configuration->get(), Campaigns::class);
    }

    public function update($campaign = null, $multiplier = 1): ?array
    {
        $campaigns = $this->getStrategies($campaign);

        foreach ($campaigns as $campaign) {
            $biddingStrategy = $campaign->UnifiedCampaign->BiddingStrategy;

            foreach (['Network', 'Search'] as $placement) {
                $strategyTypeMethod = "get$placement";
                $strategyType = $biddingStrategy->$strategyTypeMethod()->getBiddingStrategyType();

                if ($this->isStrategyAdjustable($strategyType)) {
                    $costs[] = $this->changeStrategy($campaign, $strategyType, $multiplier, $placement);
                    $this->updateCampaign($campaign);
                }
            }
        }

        return $costs ?? null;
    }

    private function isStrategyAdjustable(string $type): bool
    {
        return $type !== Contract\UnifiedCampaignNetworkStrategyTypeEnum::SERVING_OFF
            && $type !== Contract\UnifiedCampaignNetworkStrategyTypeEnum::NETWORK_DEFAULT;
    }

    private function getMultiplier($earnings, $expenses, $profit = 0.2, $inaccuracy = 0.05): ?float
    {
        if ($earnings <= 0 || $expenses <= 0) {
            return null;
        }

        $CRR = $expenses / $earnings;
        $targetCRR = 1 - $profit;
        $maxCRR = $targetCRR + $inaccuracy;
        $minCRR = $targetCRR - $inaccuracy;

        if ($CRR > $targetCRR + 1 && $CRR > $maxCRR && $CRR > $minCRR) {
            return null;
        }

        return 1 - ($profit - (1 - $CRR));
    }

    private function getStrategies(...$campaigns): ?array
    {
        $filter = Contract\CampaignsSelectionCriteria::create()
            ->setIds($campaigns);

        $request = Contract\GetCampaignsRequest::create()
            ->setSelectionCriteria($filter)
            ->setFieldNames([Contract\CampaignFieldEnum::ID])
            ->setUnifiedCampaignFieldNames([
                Contract\UnifiedCampaignFieldEnum::BIDDING_STRATEGY
            ]);

        return $this->campaignService->get($request)->getCampaigns();
    }

    private function changeStrategy(&$campaign, string $type, float $multiplier, string $placement): ?array
    {
        $types = [
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPC => 'AverageCpc',
//            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPA => 'AverageCpa',
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION => 'PayForConversion',
            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CONVERSION_RATE => 'WbMaximumConversionRate',
//            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CLICKS => 'WbMaximumClicks',
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CRR => 'AverageCrr',
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION_CRR => 'PayForConversionCrr',
        ];

        $costs = [
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPC => 'AverageCpc',
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPA => 'Cpa',
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION => 'Cpa',
            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CONVERSION_RATE => '',
//            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CLICKS => Reports\FieldEnum::CLICKS,
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CRR => '',
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION_CRR => '',
        ];

        $fields = [
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPC => Reports\FieldEnum::AVG_CPC,
//            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CPA => Reports\FieldEnum::AVG,
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION => Reports\FieldEnum::COST_PER_CONVERSION,
            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CONVERSION_RATE => Reports\FieldEnum::CONVERSION_RATE,
//            UnifiedCampaignNetworkStrategyTypeEnum::WB_MAXIMUM_CLICKS => Reports\FieldEnum::CLICKS,
            UnifiedCampaignNetworkStrategyTypeEnum::AVERAGE_CRR => Reports\FieldEnum::CONVERSION_RATE,
            UnifiedCampaignNetworkStrategyTypeEnum::PAY_FOR_CONVERSION_CRR => Reports\FieldEnum::CONVERSION_RATE,
        ];

        if (!key_exists($type, $types)) {
            return null;
        }

        $strategy = $campaign->UnifiedCampaign->BiddingStrategy->{'get' . $placement}()->{'get' . $types[$type]}();

        if (($weeklySpendLimit = $strategy->getWeeklySpendLimit()) && $multiplier > 1) {
            $strategy->setWeeklySpendLimit((int)round($weeklySpendLimit * $multiplier, -6));
            return null;
        }

        $averageCost = $this->getAverageCostFromStatistics($campaign->getId(), $fields[$type], [$strategy->getGoalId()]);

        if ($averageCost === null || $averageCost <= 0) {
            return null;
        }

        $oldCost = $strategy->{'get' . $costs[$type]}();
        $newCost = (int)($averageCost * $multiplier * 1000000);

        $strategy->{'set' . $costs[$type]}($newCost);

        return ['old' => $oldCost / 1000000, 'new' => $newCost / 1000000];
    }

    private function getAverageCostFromStatistics(int $campaignId, string $averageField = Reports\FieldEnum::AVG_CPC, ?array $goals = null): ?float
    {
        $reportService = (new ReportService());

        $reportRequest = $reportService->createReportRequest([
            Reports\FieldEnum::CAMPAIGN_ID,
            Reports\FieldEnum::AGE,
            Reports\FieldEnum::GENDER,
            $averageField,
        ], 28, $goals);

        try {
            $result = $reportService->report->getReady($reportRequest);

            $lines = $reportService->parseReportResult($result->getAsString(), ['campaign', 'age', 'gender', 'avg']);

            $filteredData = array_filter($lines, fn($row) => $row['campaign'] == $campaignId);

            //оставляем самую высокооплачиваемую аудиторию
            $filteredData = array_filter($filteredData, fn($row) => $row['gender'] == 'GENDER_MALE' || $row['gender'] == 'GENDER_FEMALE');
            $filteredData = array_filter($filteredData, fn($row) => $row['age'] == 'AGE_55');

            if (empty($filteredData)) {
                return null;
            }

            return array_shift($filteredData)['avg'];
        } catch (ClientExceptionInterface $e) {
            return null;
        }
    }

    private function updateCampaign($campaign): void
    {
        $campaign = Contract\CampaignUpdateItem::create()
            ->setId($campaign->getId())
            ->setUnifiedCampaign(Contract\UnifiedCampaignUpdateItem::create()
                ->setBiddingStrategy($campaign->UnifiedCampaign->BiddingStrategy)
            );

        $request = Contract\UpdateCampaignsRequest::create()
            ->setCampaigns([$campaign]);


        $this->campaignService->update($request);
    }

}
