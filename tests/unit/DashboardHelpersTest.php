<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the dashboard helpers that need no database.
 */
final class DashboardHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/dashboard-service.php';
    }

    #[DataProvider('changeProvider')]
    public function testChangeTextDescribesTheChangeBetweenTwoPeriods($current, $previous, string $expected): void
    {
        $this->assertSame($expected, dashboard_change_text($current, $previous));
    }

    public static function changeProvider(): array
    {
        return array(
            'nothing before, something now' => array(10, 0, 'New activity'),
            'nothing before or now' => array(0, 0, 'No change'),
            'negative previous is ignored' => array(5, -3, 'New activity'),
            'increase' => array(150, 100, '+50%'),
            'no change in amount' => array(100, 100, '+0%'),
            'decrease' => array(75, 100, '-25%'),
            'back to zero' => array(0, 40, '-100%'),
            'rounded' => array(10, 3, '+233%'),
            'string numbers' => array('30', '20', '+50%'),
        );
    }

    #[DataProvider('deltaClassProvider')]
    public function testDeltaClassPicksTheTrendColour(string $text, string $expected): void
    {
        $this->assertSame($expected, dashboard_delta_class($text));
    }

    public static function deltaClassProvider(): array
    {
        return array(
            'empty' => array('', 'flat'),
            'no change' => array('No change', 'flat'),
            'new activity' => array('New activity', 'up'),
            'increase' => array('+50%', 'up'),
            'decrease' => array('-25%', 'down'),
        );
    }
}
