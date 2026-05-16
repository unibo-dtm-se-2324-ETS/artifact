<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/report-helpers.php';

final class ReportHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = array();
        $_POST = array();
    }

    public function testReportCurrencyOptionsContainSupportedCurrencies(): void
    {
        $this->assertSame(
            array('USD', 'EUR', 'IQD', 'GBP', 'AED', 'SAR'),
            report_currency_options()
        );
    }

    public function testReportMoneyFormatsAmountAndCurrency(): void
    {
        $this->assertSame('99.90 $', report_money(99.9, 'USD'));
        $this->assertSame('250.00 EUR', report_money(250, 'EUR'));
    }

    public function testReportSelectedCurrencyReadsPostBeforeGet(): void
    {
        $_GET['currency'] = 'GBP';
        $_POST['currency'] = 'eur';

        $this->assertSame('EUR', report_selected_currency('currency'));
    }

    public function testReportSelectedCurrencyFallsBackToUsdForInvalidOrMissingValues(): void
    {
        $this->assertSame('USD', report_selected_currency('currency'));

        $_GET['currency'] = 'invalid';

        $this->assertSame('USD', report_selected_currency('currency'));
    }

    public function testReportHtmlEscapingProtectsSpecialCharacters(): void
    {
        $this->assertSame(
            '&quot;Food&quot; &amp; Transport',
            report_h('"Food" & Transport')
        );
    }
}
