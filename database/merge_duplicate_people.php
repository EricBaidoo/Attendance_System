<?php
/**
 * Merge duplicate canonical people records by normalized email_key/phone_key.
 *
 * Usage:
 *   php database/merge_duplicate_people.php
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

function hasTable(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function stageRank(string $stage): int {
    $map = [
        'visitor' => 1,
        'new_convert' => 2,
        'member' => 3,
    ];

    $normalized = strtolower(trim($stage));
    return $map[$normalized] ?? 0;
}

function recomputeKeys(PDO $pdo): void {
    $hasEmailKey = hasColumn($pdo, 'people', 'email_key');
    $hasPhoneKey = hasColumn($pdo, 'people', 'phone_key');

    if (!$hasEmailKey && !$hasPhoneKey) {
        return;
    }

    $parts = [];
    if ($hasEmailKey) {
        $parts[] = "email_key = CASE WHEN email IS NULL OR TRIM(email) = '' THEN NULL ELSE LOWER(TRIM(email)) END";
    }
    if ($hasPhoneKey) {
        $parts[] = "phone_key = CASE WHEN phone IS NULL OR TRIM(phone) = '' THEN NULL ELSE REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') END";
    }

    if (!empty($parts)) {
        $sql = 'UPDATE people SET ' . implode(', ', $parts);
        $pdo->exec($sql);
    }
}

function mergePersonInto(PDO $pdo, int $sourceId, int $targetId): void {
    if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
        return;
    }

    $rowStmt = $pdo->prepare('SELECT id, full_name, email, phone, email_key, phone_key, current_stage, first_seen_at, last_seen_at FROM people WHERE id = ?');
    $rowStmt->execute([$sourceId]);
    $source = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $rowStmt->execute([$targetId]);
    $target = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$source || !$target) {
        return;
    }

    $sourceStage = (string)($source['current_stage'] ?? 'visitor');
    $targetStage = (string)($target['current_stage'] ?? 'visitor');
    $newStage = stageRank($sourceStage) > stageRank($targetStage) ? $sourceStage : $targetStage;

    $targetName = trim((string)($target['full_name'] ?? ''));
    $targetEmail = trim((string)($target['email'] ?? ''));
    $targetPhone = trim((string)($target['phone'] ?? ''));
    $targetEmailKey = trim((string)($target['email_key'] ?? ''));
    $targetPhoneKey = trim((string)($target['phone_key'] ?? ''));

    $sourceName = trim((string)($source['full_name'] ?? ''));
    $sourceEmail = trim((string)($source['email'] ?? ''));
    $sourcePhone = trim((string)($source['phone'] ?? ''));
    $sourceEmailKey = trim((string)($source['email_key'] ?? ''));
    $sourcePhoneKey = trim((string)($source['phone_key'] ?? ''));

    $mergedName = $targetName !== '' ? $targetName : ($sourceName !== '' ? $sourceName : null);
    $mergedEmail = $targetEmail !== '' ? $targetEmail : ($sourceEmail !== '' ? $sourceEmail : null);
    $mergedPhone = $targetPhone !== '' ? $targetPhone : ($sourcePhone !== '' ? $sourcePhone : null);
    $mergedEmailKey = $targetEmailKey !== '' ? $targetEmailKey : ($sourceEmailKey !== '' ? $sourceEmailKey : null);
    $mergedPhoneKey = $targetPhoneKey !== '' ? $targetPhoneKey : ($sourcePhoneKey !== '' ? $sourcePhoneKey : null);

    $updateTarget = $pdo->prepare(
        'UPDATE people
         SET full_name = ?,
             email = ?,
             phone = ?,
             email_key = ?,
             phone_key = ?,
             current_stage = ?,
             first_seen_at = LEAST(COALESCE(first_seen_at, NOW()), COALESCE(?, NOW())),
             last_seen_at = GREATEST(COALESCE(last_seen_at, NOW()), COALESCE(?, NOW()))
         WHERE id = ?'
    );
    $updateTarget->execute([
        $mergedName,
        $mergedEmail,
        $mergedPhone,
        $mergedEmailKey,
        $mergedPhoneKey,
        $newStage,
        $source['first_seen_at'] ?? null,
        $source['last_seen_at'] ?? null,
        $targetId,
    ]);

    foreach (['members', 'visitors', 'new_converts'] as $table) {
        if (hasColumn($pdo, $table, 'person_id')) {
            $sql = sprintf('UPDATE %s SET person_id = ? WHERE person_id = ?', $table);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId, $sourceId]);
        }
    }

    if (hasTable($pdo, 'person_lifecycle_events') && hasColumn($pdo, 'person_lifecycle_events', 'person_id')) {
        $stmt = $pdo->prepare('UPDATE person_lifecycle_events SET person_id = ? WHERE person_id = ?');
        $stmt->execute([$targetId, $sourceId]);
    }

    $deleteStmt = $pdo->prepare('DELETE FROM people WHERE id = ?');
    $deleteStmt->execute([$sourceId]);
}

function mergeByKey(PDO $pdo, string $keyColumn): int {
    $sql = "SELECT GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM people
            WHERE {$keyColumn} IS NOT NULL AND {$keyColumn} <> ''
            GROUP BY {$keyColumn}
            HAVING COUNT(*) > 1";
    $groups = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $merged = 0;
    foreach ($groups as $group) {
        $idsCsv = (string)($group['ids'] ?? '');
        if ($idsCsv === '') {
            continue;
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (count($ids) < 2) {
            continue;
        }

        $target = array_shift($ids);
        foreach ($ids as $source) {
            mergePersonInto($pdo, $source, $target);
            $merged++;
        }
    }

    return $merged;
}

if (!hasTable($pdo, 'people')) {
    fwrite(STDERR, "people table not found.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();

    recomputeKeys($pdo);

    $totalMerged = 0;
    $maxIterations = 10;
    for ($i = 0; $i < $maxIterations; $i++) {
        $before = $totalMerged;

        if (hasColumn($pdo, 'people', 'email_key')) {
            $totalMerged += mergeByKey($pdo, 'email_key');
        }

        if (hasColumn($pdo, 'people', 'phone_key')) {
            $totalMerged += mergeByKey($pdo, 'phone_key');
        }

        recomputeKeys($pdo);

        if ($totalMerged === $before) {
            break;
        }
    }

    $dupEmail = 0;
    $dupPhone = 0;

    if (hasColumn($pdo, 'people', 'email_key')) {
        $dupEmail = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT email_key FROM people WHERE email_key IS NOT NULL AND email_key <> '' GROUP BY email_key HAVING COUNT(*) > 1) t")->fetchColumn();
    }
    if (hasColumn($pdo, 'people', 'phone_key')) {
        $dupPhone = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT phone_key FROM people WHERE phone_key IS NOT NULL AND phone_key <> '' GROUP BY phone_key HAVING COUNT(*) > 1) t")->fetchColumn();
    }

    $peopleCount = (int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn();

    $pdo->commit();

    echo "Duplicate merge complete.\n";
    echo "Merged records: {$totalMerged}\n";
    echo "People rows now: {$peopleCount}\n";
    echo "Duplicate email groups remaining: {$dupEmail}\n";
    echo "Duplicate phone groups remaining: {$dupPhone}\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Merge failed: ' . $e->getMessage() . "\n");
    exit(1);
}
