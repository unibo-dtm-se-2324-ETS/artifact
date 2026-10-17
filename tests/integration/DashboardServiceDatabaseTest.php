<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * Integration tests for the dashboard totals, charts and budget progress.
 * The clock is fixed to 15 May 2026, 12:00.
 */
final class DashboardServiceDatabaseTest extends DatabaseTestCase
{
    private int $now;

    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/dashboard-service.php';
        $this->now = strtotime('2026-05-15 12:00:00');
    }

    private function category(int $id, int $userId, string $name): void
    {
        mysqli_query(self::$con, sprintf("INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (%d, %d, '%s')", $id, $userId, $name));
    }

    private function expense(int $userId, string $item, float $cost, string $date, int $categoryId = 0, string $currency = 'USD', string $createdAt = '2026-01-01 00:00:00'): void
    {
        mysqli_query(
            self::$con,
            sprintf(
                "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, CreatedAt) VALUES (%d, '%s', '%s', %F, '%s', %d, %s)",
                $userId, $date, $item, $cost, $currency, $categoryId, $createdAt === 'NOW()' ? 'NOW()' : "'" . $createdAt . "'"
            )
        );
    }

    private function seedMonth(): void
    {
        $this->category(1, 1, 'Food');
        $this->category(2, 1, 'Bills');
        $this->expense(1, 'A', 10, '2026-05-15', 1, 'USD', 'NOW()');
        $this->expense(1, 'B', 20, '2026-05-14', 1, 'USD', '2026-05-14 08:00:00');
        $this->expense(1, 'C', 30, '2026-05-10', 2, 'USD', '2026-05-10 08:00:00');
        $this->expense(1, 'D', 40, '2026-05-05', 0, 'USD', '2026-05-05 08:00:00');
        $this->expense(1, 'E', 100, '2026-04-20', 1, 'USD', '2026-04-20 08:00:00');
        $this->expense(1, 'F', 5, '2026-01-15', 1, 'USD', '2026-01-15 08:00:00');
        $this->expense(1, 'G', 7, '2026-05-15', 1, 'EUR', '2026-05-15 08:00:00');
        $this->expense(2, 'H', 999, '2026-05-15', 0, 'USD', '2026-05-15 08:00:00');
    }

    // totals

    public function testTotalsForEveryPeriod(): void
    {
        $this->seedMonth();

        $sums = dashboard_build(self::$con, 1, 'USD', $this->now)['sums'];

        $this->assertEquals(10, $sums['last24h']);
        $this->assertEquals(10, $sums['today']);
        $this->assertEquals(20, $sums['yesterday']);
        $this->assertEquals(60, $sums['week']);
        $this->assertEquals(40, $sums['previousWeek']);
        $this->assertEquals(100, $sums['month']);
        $this->assertEquals(100, $sums['previousMonth']);
        $this->assertEquals(205, $sums['year']);
        $this->assertEquals(205, $sums['total']);
    }

    public function testTotalsAreSeparatedByCurrencyAndByUser(): void
    {
        $this->seedMonth();

        $this->assertEquals(7, dashboard_build(self::$con, 1, 'EUR', $this->now)['sums']['total']);
        $this->assertEquals(999, dashboard_build(self::$con, 2, 'USD', $this->now)['sums']['total']);
        $this->assertEquals(0, dashboard_build(self::$con, 1, 'GBP', $this->now)['sums']['total']);
    }

    public function testThePreviousMonthIsRightOnTheLastDayOfAMonth(): void
    {
        $this->expense(1, 'Feb', 12, '2026-02-10');
        $this->expense(1, 'Mar', 33, '2026-03-20');

        $sums = dashboard_build(self::$con, 1, 'USD', strtotime('2026-03-31 12:00:00'))['sums'];

        $this->assertEquals(12, $sums['previousMonth']);
        $this->assertEquals(33, $sums['month']);
    }

    public function testChangeTextsAndCards(): void
    {
        $this->seedMonth();

        $data = dashboard_build(self::$con, 1, 'USD', $this->now);

        $this->assertSame('+50%', $data['weeklyChangeText']);
        $this->assertSame('+0%', $data['monthlyChangeText']);
        $this->assertSame(array('Last 24 Hours', 'Last 7 Days', 'This Month', 'Total'), array_column($data['metricCards'], 'label'));
        $this->assertSame(array(null, '+50%', '+0%', null), array_column($data['metricCards'], 'chip'));
        $this->assertSame('All records in USD', $data['metricCards'][3]['note']);
        $this->assertStringContainsString('May 14', $data['metricCards'][0]['note']);
        $this->assertSame('May 2026', $data['selectedMonthLabel']);
    }

    public function testQuickStats(): void
    {
        $this->seedMonth();

        $stats = dashboard_build(self::$con, 1, 'USD', $this->now)['quickStats'];

        $this->assertSame(array('Today', 'Yesterday', 'This Year', 'Active Days', 'Avg / Active Day'), array_column($stats, 'label'));
        $this->assertSame('10.00 $', $stats[0]['value']);
        $this->assertSame('20.00 $', $stats[1]['value']);
        $this->assertSame('205.00 $', $stats[2]['value']);
        $this->assertSame('4', $stats[3]['value']);
        $this->assertSame('25.00 $', $stats[4]['value']);
    }

    public function testActiveDaysAndTheAverageForADayWithSpending(): void
    {
        $this->seedMonth();

        $data = dashboard_build(self::$con, 1, 'USD', $this->now);

        $this->assertSame(4, $data['activeDays']);
        $this->assertEquals(25, $data['avgPerActiveDay']);
    }

    // charts

    public function testTheDailyChartHasOneValuePerDayOfTheMonth(): void
    {
        $this->seedMonth();

        $data = dashboard_build(self::$con, 1, 'USD', $this->now);

        $this->assertCount(31, $data['dayLabels']);
        $this->assertSame('1', $data['dayLabels'][0]);
        $this->assertSame('31', $data['dayLabels'][30]);
        $this->assertEquals(40, $data['dayValues'][4]);
        $this->assertEquals(30, $data['dayValues'][9]);
        $this->assertEquals(20, $data['dayValues'][13]);
        $this->assertEquals(10, $data['dayValues'][14]);
        $this->assertEquals(0, $data['dayValues'][0]);
        $this->assertEquals(100, array_sum($data['dayValues']));
    }

    public function testTheCategoryChartShowsTheTopCategoriesWithTheirSegments(): void
    {
        $this->seedMonth();

        $data = dashboard_build(self::$con, 1, 'USD', $this->now);
        $segments = $data['topCategorySegments'];

        $this->assertEquals(100, $data['topCategoryTotal']);
        $this->assertCount(3, $segments);
        $this->assertSame('Uncategorized', $segments[0]['label']);
        $this->assertEquals(40, $segments[0]['value']);
        $this->assertSame('#18c7b8', $segments[0]['color']);
        $this->assertEquals(0, $segments[0]['offset']);
        $this->assertLessThan(0, $segments[1]['offset']);

        $circumference = 2 * pi() * 84;
        $this->assertEqualsWithDelta($circumference, array_sum(array_column($segments, 'dash')), 0.0001);
        $this->assertEqualsWithDelta($circumference, $segments[0]['dash'] + $segments[0]['gap'], 0.0001);
    }

    public function testTheCategoryChartIsLimitedToFiveCategories(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->category($i, 1, 'Cat' . $i);
            $this->expense(1, 'x' . $i, $i * 10, '2026-05-02', $i);
        }

        $segments = dashboard_build(self::$con, 1, 'USD', $this->now)['topCategorySegments'];

        $this->assertCount(5, $segments);
        $this->assertSame(array('Cat7', 'Cat6', 'Cat5', 'Cat4', 'Cat3'), array_column($segments, 'label'));
    }

    public function testCurrenciesWithoutSpendingAreLeftOutOfTheCurrencyTable(): void
    {
        $this->seedMonth();

        $used = dashboard_build(self::$con, 1, 'USD', $this->now)['currencyTotalsUsed'];

        $this->assertSame(array('USD', 'EUR'), array_keys($used));
        $this->assertEquals(205, $used['USD']);
        $this->assertEquals(7, $used['EUR']);
    }

    public function testTheLatestExpenseIsTheLastOneRecorded(): void
    {
        $this->seedMonth();

        $latest = dashboard_build(self::$con, 1, 'USD', $this->now)['latestExpense'];

        $this->assertSame('A', $latest['ExpenseItem']);
        $this->assertSame('Food', $latest['CategoryName']);
    }

    // budgets

    public function testBudgetProgressStatusesAndTotals(): void
    {
        $this->category(1, 1, 'Food');
        $this->category(2, 1, 'Bills');
        $this->category(3, 1, 'Rent');
        $this->category(4, 1, 'Travel');
        $this->category(5, 1, 'Gym');
        $this->category(6, 1, 'Idle');
        $this->expense(1, 'f', 30, '2026-05-02', 1);
        $this->expense(1, 'b', 30, '2026-05-03', 2);
        $this->expense(1, 'r', 45, '2026-05-04', 3);
        $this->expense(1, 't', 5, '2026-05-05', 4);
        $this->expense(1, 'old', 500, '2026-04-05', 6);
        foreach (array(array(1, 25), array(2, 40), array(3, 50), array(5, 20)) as $budget) {
            mysqli_query(self::$con, sprintf("INSERT INTO tblbudgets (UserId, CategoryId, BudgetMonth, Currency, BudgetAmount) VALUES (1, %d, '2026-05', 'USD', %d)", $budget[0], $budget[1]));
        }

        $data = dashboard_build(self::$con, 1, 'USD', $this->now);
        $byName = array_column($data['budgetRows'], null, 'CategoryName');

        $this->assertSame(array('Rent', 'Bills', 'Food', 'Gym', 'Travel'), array_column($data['budgetRows'], 'CategoryName'));
        $this->assertSame('danger', $byName['Food']['status_class']);
        $this->assertSame('Over budget', $byName['Food']['status_text']);
        $this->assertEquals(-5, $byName['Food']['remaining']);
        $this->assertSame('safe', $byName['Bills']['status_class']);
        $this->assertSame('75% used', $byName['Bills']['status_text']);
        $this->assertSame('warn', $byName['Rent']['status_class']);
        $this->assertSame('safe', $byName['Gym']['status_class']);
        $this->assertSame('0% used', $byName['Gym']['status_text']);
        $this->assertSame('No budget', $byName['Travel']['status_text']);
        $this->assertSame(100, $byName['Food']['progress']);
        $this->assertEquals(135, $data['budgetTotal']);
        $this->assertEquals(110, $data['budgetSpent']);
        $this->assertEquals(25, $data['budgetRemaining']);
        $this->assertSame(1, $data['overBudgetCount']);
        $this->assertEqualsWithDelta(81.48, $data['budgetProgress'], 0.01);
    }

    // a user without data

    public function testADashboardWithoutAnyDataIsEmptyButComplete(): void
    {
        $data = dashboard_build(self::$con, 1, 'USD', $this->now);

        $this->assertSame(array_fill(0, 9, 0), array_values(array_map('intval', $data['sums'])));
        $this->assertSame(array(), $data['topCategorySegments']);
        $this->assertSame(array(), $data['budgetRows']);
        $this->assertSame(array(), $data['currencyTotalsUsed']);
        $this->assertNull($data['latestExpense']);
        $this->assertSame(0, $data['activeDays']);
        $this->assertSame(0, $data['avgPerActiveDay']);
        $this->assertSame('No change', $data['weeklyChangeText']);
        $this->assertSame(0, $data['overBudgetCount']);
        $this->assertCount(4, $data['metricCards']);
        $this->assertCount(5, $data['quickStats']);
        $this->assertSame(0, $data['budgetProgress']);
    }

    public function testTheClockDefaultsToTheCurrentTime(): void
    {
        $this->expense(1, 'Now', 11, date('Y-m-d'), 0, 'USD', 'NOW()');

        $data = dashboard_build(self::$con, 1, 'USD');

        $this->assertEquals(11, $data['sums']['today']);
        $this->assertEquals(11, $data['sums']['last24h']);
        $this->assertSame(date('F Y'), $data['selectedMonthLabel']);
    }
}
