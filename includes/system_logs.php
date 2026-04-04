<?php

if (!function_exists('appLogDirectory')) {
    function appLogDirectory(): string {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
    }
}

if (!function_exists('appLogFiles')) {
    function appLogFiles(): array {
        $base_dir = appLogDirectory();

        return [
            'error' => $base_dir . DIRECTORY_SEPARATOR . 'error.log',
            'activity' => $base_dir . DIRECTORY_SEPARATOR . 'activity.log',
        ];
    }
}

if (!function_exists('appLogFileLabel')) {
    function appLogFileLabel(string $logType): string {
        return $logType === 'activity' ? 'Activity Log' : 'Error Log';
    }
}

if (!function_exists('appLogTail')) {
    function appLogTail(string $filePath, int $limit = 250): array {
        if ($limit < 1 || !is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        if (count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
        }

        return $lines;
    }
}

if (!function_exists('appLogFileMeta')) {
    function appLogFileMeta(string $filePath): array {
        return [
            'exists' => is_file($filePath),
            'readable' => is_readable($filePath),
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'modified' => is_file($filePath) ? filemtime($filePath) : false,
        ];
    }
}

if (!function_exists('appLogEntryDate')) {
    function appLogEntryDate(string $entry): ?string {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2})[ T]/', $entry, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

if (!function_exists('appLogFilterEntries')) {
    function appLogFilterEntries(array $entries, string $keyword = '', string $fromDate = '', string $toDate = ''): array {
        $keyword = trim($keyword);
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);

        if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = '';
        }
        if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $toDate = '';
        }

        if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return array_values(array_filter($entries, function (string $entry) use ($keyword, $fromDate, $toDate): bool {
            if ($keyword !== '' && stripos($entry, $keyword) === false) {
                return false;
            }

            if ($fromDate === '' && $toDate === '') {
                return true;
            }

            $entryDate = appLogEntryDate($entry);
            if ($entryDate === null) {
                return false;
            }

            if ($fromDate !== '' && $entryDate < $fromDate) {
                return false;
            }

            if ($toDate !== '' && $entryDate > $toDate) {
                return false;
            }

            return true;
        }));
    }
}
