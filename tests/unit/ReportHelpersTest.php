<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/report-helpers.php';

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
        $this->assertSame('250.00 €', report_money(250, 'EUR'));
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

    public function testReportCurrencySymbolsAndUnknownCurrencyFallback(): void
    {
        $expected = array('USD' => '$', 'EUR' => '€', 'IQD' => 'IQD', 'GBP' => '£', 'AED' => 'AED', 'SAR' => 'SAR');

        foreach ($expected as $code => $symbol) {
            $this->assertSame($symbol, report_currency_symbol($code));
        }
        $this->assertSame('JPY', report_currency_symbol('JPY'));
    }

    public function testReportMoneyHandlesStringsAndThousandsSeparators(): void
    {
        $this->assertSame('0.00 $', report_money('abc', 'USD'));
        $this->assertSame('1,234.50 IQD', report_money('1234.5', 'IQD'));
    }

    public function testReportSelectedCurrencyReadsGetWhenPostIsMissing(): void
    {
        $_GET['currency'] = ' gbp ';

        $this->assertSame('GBP', report_selected_currency('currency'));
    }

    public function testReportSelectedCurrencyRejectsUnsupportedPostValue(): void
    {
        $_POST['currency'] = 'jpy';
        $_GET['currency'] = 'GBP';

        $this->assertSame('USD', report_selected_currency('currency'));
    }

    public function testReportHtmlEscapingHandlesNullAndNumbers(): void
    {
        $this->assertSame('', report_h(null));
        $this->assertSame('7', report_h(7));
    }

    public function testReportHtmlEscapingProtectsSpecialCharacters(): void
    {
        $this->assertSame(
            '&quot;Food&quot; &amp; Transport',
            report_h('"Food" & Transport')
        );
    }
}
