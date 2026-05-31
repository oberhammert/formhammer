<?php

declare(strict_types=1);

final class Formhammer_Logger
{
    private const CLEANUP_HOOK = 'formhammer_log_cleanup';
    private const DEFAULT_RETENTION_DAYS = 7;
    private const DAY_IN_SECONDS = 86400;

    private object|null $wpdb;
    private \Closure $clock;

    public function __construct(?object $wpdb = null, ?callable $clock = null)
    {
        if ($wpdb === null && isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
            $wpdb = $GLOBALS['wpdb'];
        }

        $this->wpdb = $wpdb;
        $this->clock = \Closure::fromCallable($clock ?? 'time');
    }

    public function register(): void
    {
        add_action(self::CLEANUP_HOOK, [$this, 'cleanup'], 10, 0);
        $this->create_table();

        if (
            function_exists('wp_next_scheduled')
            && function_exists('wp_schedule_event')
            && wp_next_scheduled(self::CLEANUP_HOOK) === false
        ) {
            wp_schedule_event(($this->clock)(), 'daily', self::CLEANUP_HOOK);
        }
    }

    public function log(string $form_id, Formhammer_Validation_Result $result): void
    {
        if (!$this->is_enabled() || $this->wpdb === null || !method_exists($this->wpdb, 'insert')) {
            return;
        }

        $this->wpdb->insert(
            $this->table_name(),
            [
                'logged_at' => gmdate('Y-m-d H:i:s', ($this->clock)()),
                'form_id' => $form_id,
                'verdict' => $result->verdict(),
                'score' => $result->score(),
            ],
            ['%s', '%s', '%s', '%d']
        );
    }

    public function cleanup(): void
    {
        if ($this->wpdb === null || !method_exists($this->wpdb, 'query')) {
            return;
        }

        $retention_days = $this->option_int('formhammer_log_retention', self::DEFAULT_RETENTION_DAYS);
        $cutoff = gmdate('Y-m-d H:i:s', ($this->clock)() - ($retention_days * self::DAY_IN_SECONDS));
        $query = sprintf('DELETE FROM %s WHERE logged_at < %%s', $this->table_name());

        if (method_exists($this->wpdb, 'prepare')) {
            $query = $this->wpdb->prepare($query, $cutoff);
        } else {
            $query = str_replace('%s', "'" . $cutoff . "'", $query);
        }

        $this->wpdb->query($query);
    }

    private function is_enabled(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        return (bool) get_option('formhammer_log_enabled', false);
    }

    private function option_int(string $option, int $default): int
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        $value = get_option($option, $default);

        return is_numeric($value) ? max(0, (int) $value) : $default;
    }

    private function table_name(): string
    {
        $prefix = '';

        if ($this->wpdb !== null && isset($this->wpdb->prefix) && is_string($this->wpdb->prefix)) {
            $prefix = $this->wpdb->prefix;
        }

        return $prefix . 'formhammer_log';
    }

    private function create_table(): void
    {
        if ($this->wpdb === null || !method_exists($this->wpdb, 'query')) {
            return;
        }

        $this->wpdb->query(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (logged_at datetime NOT NULL, form_id varchar(191) NOT NULL, verdict varchar(20) NOT NULL, score int NOT NULL)',
                $this->table_name()
            )
        );
    }
}
