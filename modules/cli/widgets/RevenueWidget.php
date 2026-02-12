<?php
namespace modules\cli\widgets;

use Craft;
use craft\base\Widget;
use craft\web\View;
use modules\cli\CliModule;

class RevenueWidget extends Widget
{
    public ?int $colspan = 2;

    public static function displayName(): string
    {
        return 'Total Revenue (High Performance)';
    }

    public static function icon(): ?string
    {
        return 'piggy-bank';
    }

    public function getBodyHtml(): ?string
    {
        $revenue = CliModule::getInstance()->revenue->getRevenue();

        $totalRevenue = number_format((float)($revenue['totalRevenue'] ?? 0), 2, '.', ',');
        $totalPaid = number_format((float)($revenue['totalPaid'] ?? 0), 2, '.', ',');
        $totalRefunded = number_format((float)($revenue['totalRefunded'] ?? 0), 2, '.', ',');
        $netRevenue = number_format((float)($revenue['totalPaid'] ?? 0) - (float)($revenue['totalRefunded'] ?? 0), 2, '.', ',');
        $orderCount = number_format((int)($revenue['orderCount'] ?? 0));
        $paidOrderCount = number_format((int)($revenue['paidOrderCount'] ?? 0));
        $currency = $revenue['currency'] ?? 'EUR';
        $lastUpdated = $revenue['dateUpdated'] ?? 'Never';

        // Get the view and set template mode to SITE
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $html = $view->renderTemplate('cli/widgets/revenue', [
            'totalRevenue' => $totalRevenue,
            'totalPaid' => $totalPaid,
            'totalRefunded' => $totalRefunded,
            'netRevenue' => $netRevenue,
            'orderCount' => $orderCount,
            'paidOrderCount' => $paidOrderCount,
            'currency' => $currency,
            'lastUpdated' => $lastUpdated,
        ]);

        // Restore the old template mode
        $view->setTemplateMode($oldMode);

        return $html;
    }
}