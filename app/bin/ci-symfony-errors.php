<?php

declare(strict_types=1);

const MASK = '[MASQUE]';

function sanitizeMessage(string $message): string
{
    $sanitized = preg_replace(
        [
            '#([?&][A-Za-z0-9_.%~-]+)=([^&\\s"\'<>]+)#',
            '#\\b(?:Bearer|Basic)\\s+[A-Za-z0-9._~+/=-]+#i',
            '#((?:postgres(?:ql)?|mysql|smtp|smtps)://[^:/\\s]+:)[^@\\s]+@#i',
            '#\\b(PHPSESSID=)[^;\\s]+#i',
            '#[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}#i',
            '#\\b(?:\\d{1,3}\\.){3}\\d{1,3}\\b#',
            '#\\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\b#i',
            '#\\b[0-9a-f]{32,}\\b#i',
            '#(Key \\([^)]{1,80}\\))=\\([^)]*\\)#i',
        ],
        [
            '$1='.MASK,
            MASK,
            '$1'.MASK.'@',
            '$1'.MASK,
            MASK,
            MASK,
            MASK,
            MASK,
            '$1=('.MASK.')',
        ],
        $message,
    ) ?? MASK;

    return mb_strimwidth($sanitized, 0, 500, '…');
}

function safeLocation(string $file, int $line): string
{
    $applicationRoot = dirname(__DIR__).DIRECTORY_SEPARATOR;
    $relativeFile = str_starts_with($file, $applicationRoot)
        ? substr($file, strlen($applicationRoot))
        : basename($file);

    return sprintf('%s:%d', $relativeFile, $line);
}

$errors = [];
foreach (glob(dirname(__DIR__).'/var/log/prod*.log') ?: [] as $logFile) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $record = json_decode($line, true);
        if (!is_array($record) || ($record['level'] ?? 0) < 400) {
            continue;
        }

        $exception = $record['context']['exception'] ?? null;
        if (!is_array($exception)) {
            continue;
        }

        $errors[] = sprintf(
            '%s: %s (%s)',
            (string) ($exception['class'] ?? 'Exception'),
            sanitizeMessage((string) ($exception['message'] ?? 'Message indisponible')),
            safeLocation((string) ($exception['file'] ?? ''), (int) ($exception['line'] ?? 0)),
        );
    }
}

foreach (array_slice($errors, -10) as $error) {
    fwrite(STDERR, $error.PHP_EOL);
}
