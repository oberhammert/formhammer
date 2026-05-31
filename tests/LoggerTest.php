<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['formhammer_test_options'] = [];
        $GLOBALS['formhammer_registered_actions'] = [];
        $GLOBALS['formhammer_scheduled_events'] = [];
        $GLOBALS['formhammer_scheduled_event_meta'] = [];
    }

    public function testRegisterSchedulesDailyCleanup(): void
    {
        $logger = new Formhammer_Logger(
            wpdb: new Formhammer_Test_Wpdb(),
            clock: static fn (): int => 1_700_000_000
        );

        $logger->register();

        self::assertCount(1, $GLOBALS['formhammer_registered_actions']);
        self::assertSame('formhammer_log_cleanup', $GLOBALS['formhammer_registered_actions'][0]['hook']);
        self::assertSame(1_700_000_000, $GLOBALS['formhammer_scheduled_events']['formhammer_log_cleanup']);
        self::assertSame('daily', $GLOBALS['formhammer_scheduled_event_meta']['formhammer_log_cleanup']['recurrence']);
    }

    public function testLogWritesMinimalEntryWhenEnabled(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_log_enabled'] = true;
        $wpdb = new Formhammer_Test_Wpdb();
        $logger = new Formhammer_Logger(
            wpdb: $wpdb,
            clock: static fn (): int => 1_700_000_000
        );

        $logger->log('cf7-12', Formhammer_Validation_Result::block('score_threshold_block', 40));

        self::assertCount(1, $wpdb->inserts);
        self::assertSame('wp_formhammer_log', $wpdb->inserts[0]['table']);
        self::assertSame([
            'logged_at' => '2023-11-14 22:13:20',
            'form_id' => 'cf7-12',
            'verdict' => 'BLOCK',
            'score' => 40,
        ], $wpdb->inserts[0]['data']);
    }

    public function testLogSkipsWhenDisabled(): void
    {
        $wpdb = new Formhammer_Test_Wpdb();
        $logger = new Formhammer_Logger(
            wpdb: $wpdb,
            clock: static fn (): int => 1_700_000_000
        );

        $logger->log('cf7-12', Formhammer_Validation_Result::pass('pass'));

        self::assertSame([], $wpdb->inserts);
    }

    public function testCleanupDeletesEntriesOlderThanRetentionWindow(): void
    {
        $GLOBALS['formhammer_test_options']['formhammer_log_retention'] = 7;
        $wpdb = new Formhammer_Test_Wpdb();
        $logger = new Formhammer_Logger(
            wpdb: $wpdb,
            clock: static fn (): int => 1_700_000_000
        );

        $logger->cleanup();

        self::assertCount(1, $wpdb->queries);
        self::assertSame(
            "DELETE FROM wp_formhammer_log WHERE logged_at < '2023-11-07 22:13:20'",
            $wpdb->queries[0]
        );
    }
}

final class Formhammer_Test_Wpdb
{
    public string $prefix = 'wp_';
    public array $inserts = [];
    public array $queries = [];

    public function insert(string $table, array $data, array $formats = []): int
    {
        $this->inserts[] = [
            'table' => $table,
            'data' => $data,
            'formats' => $formats,
        ];

        return 1;
    }

    public function query(string $query): int
    {
        $this->queries[] = $query;

        return 1;
    }

    public function prepare(string $query, string $value): string
    {
        return str_replace('%s', "'" . $value . "'", $query);
    }
}
