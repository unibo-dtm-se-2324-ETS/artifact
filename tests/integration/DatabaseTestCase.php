<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need a MySQL database.
 *
 * They run against a separate throw-away database (default: detsdb_test) that
 * is created from database/database.sql. The real application database is
 * never touched. When no MySQL server is reachable the tests are skipped.
 *
 * Environment variables: TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?mysqli $con = null;

    public static function setUpBeforeClass(): void
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $pass = getenv('TEST_DB_PASS') ?: '';
        $name = getenv('TEST_DB_NAME') ?: 'detsdb_test';

        if ($name === 'detsdb') {
            self::fail('Refusing to run the tests against the real database.');
        }

        $con = @mysqli_connect($host, $user, $pass);
        if (!$con) {
            return;
        }

        mysqli_query($con, "CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4");
        mysqli_select_db($con, $name);

        $statements = explode(';', (string)file_get_contents(__DIR__ . '/../../database/database.sql'));
        foreach ($statements as $statement) {
            if (stripos(ltrim($statement), 'CREATE TABLE') === 0) {
                mysqli_query($con, $statement);
            }
        }

        self::$con = $con;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$con) {
            mysqli_close(self::$con);
            self::$con = null;
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    protected function setUp(): void
    {
        if (!self::$con) {
            $this->markTestSkipped('No MySQL test database is available.');
        }

        foreach (array('tbluser', 'tblexpense', 'tblcategories', 'tblrecurring', 'tblbudgets', 'tblitems') as $table) {
            mysqli_query(self::$con, "TRUNCATE TABLE $table");
        }
    }

    /** @var string[] */
    private array $brokenTables = array();

    /**
     * Makes a table unavailable for the rest of the test, to check how the code behaves when the
     * database fails. The table is put back after the test.
     */
    protected function breakTable(string $table): void
    {
        mysqli_query(self::$con, "RENAME TABLE $table TO {$table}_broken");
        $this->brokenTables[] = $table;
    }

    protected function tearDown(): void
    {
        foreach ($this->brokenTables as $table) {
            mysqli_query(self::$con, "RENAME TABLE {$table}_broken TO $table");
        }
        $this->brokenTables = array();
    }

    protected function query(string $sql): array
    {
        $result = mysqli_query(self::$con, $sql);
        $this->assertNotFalse($result, mysqli_error(self::$con));

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}
