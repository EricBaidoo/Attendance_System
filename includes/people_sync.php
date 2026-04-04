<?php

if (!function_exists('peopleSyncTableExists')) {
    function peopleSyncTableExists(PDO $pdo): bool {
        static $checked = false;
        static $exists = false;

        if ($checked) {
            return $exists;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'people'");
        $stmt->execute();

        $exists = ((int)$stmt->fetchColumn()) > 0;
        $checked = true;

        return $exists;
    }
}

if (!function_exists('peopleNormalizeEmail')) {
    function peopleNormalizeEmail(?string $email): ?string {
        $value = trim((string)$email);
        return $value === '' ? null : strtolower($value);
    }
}

if (!function_exists('peopleNormalizePhone')) {
    function peopleNormalizePhone(?string $phone): ?string {
        $value = preg_replace('/[^0-9]/', '', trim((string)$phone));
        return $value === '' ? null : $value;
    }
}

if (!function_exists('peopleStageRank')) {
    function peopleStageRank(string $stage): int {
        $map = [
            'visitor' => 1,
            'new_convert' => 2,
            'member' => 3,
        ];

        return $map[strtolower(trim($stage))] ?? 0;
    }
}

if (!function_exists('peopleHasColumn')) {
    function peopleHasColumn(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = strtolower($table . '.' . $column);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);

        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$key];
    }
}

if (!function_exists('peopleSyncResolveTable')) {
    function peopleSyncResolveTable(PDO $pdo, string $table): ?string {
        $table = strtolower(trim($table));

        $map = [
            'members' => 'member_roles',
            'visitors' => 'visitor_roles',
            'new_converts' => 'new_convert_roles',
            'member_roles' => 'member_roles',
            'visitor_roles' => 'visitor_roles',
            'new_convert_roles' => 'new_convert_roles',
        ];

        if (!isset($map[$table])) {
            return null;
        }

        $target = $map[$table];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$target]);

        return ((int)$stmt->fetchColumn()) > 0 ? $target : null;
    }
}

if (!function_exists('peopleFindByEmailKey')) {
    function peopleFindByEmailKey(PDO $pdo, string $emailNorm): ?int {
        if (peopleHasColumn($pdo, 'people', 'email_key')) {
            $stmt = $pdo->prepare('SELECT id FROM people WHERE email_key = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([$emailNorm]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM people WHERE LOWER(TRIM(email)) = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([$emailNorm]);
        }

        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }
}

if (!function_exists('peopleFindByPhoneKey')) {
    function peopleFindByPhoneKey(PDO $pdo, string $phoneNorm): ?int {
        if (peopleHasColumn($pdo, 'people', 'phone_key')) {
            $stmt = $pdo->prepare('SELECT id FROM people WHERE phone_key = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([$phoneNorm]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM people WHERE REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$phoneNorm]);
        }

        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }
}

if (!function_exists('peopleFindOrCreate')) {
    function peopleFindOrCreate(PDO $pdo, string $fullName, ?string $email, ?string $phone, string $stage): ?int {
        if (!peopleSyncTableExists($pdo)) {
            return null;
        }

        $fullName = trim($fullName);
        $emailNorm = peopleNormalizeEmail($email);
        $phoneNorm = peopleNormalizePhone($phone);

        $emailMatchId = $emailNorm !== null ? peopleFindByEmailKey($pdo, $emailNorm) : null;
        $phoneMatchId = $phoneNorm !== null ? peopleFindByPhoneKey($pdo, $phoneNorm) : null;

        if ($emailMatchId !== null && $phoneMatchId !== null && $emailMatchId !== $phoneMatchId) {
            throw new RuntimeException('Identity conflict: this email and phone are linked to different people records.');
        }

        $matchedId = $emailMatchId ?? $phoneMatchId;

        if ($matchedId === null && $emailNorm === null && $phoneNorm === null && $fullName !== '') {
            $findByName = $pdo->prepare('SELECT id FROM people WHERE LOWER(TRIM(full_name)) = LOWER(?) ORDER BY id ASC LIMIT 1');
            $findByName->execute([$fullName]);
            $nameId = $findByName->fetchColumn();
            $matchedId = $nameId === false ? null : (int)$nameId;
        }

        if ($matchedId !== null) {
            $findStmt = $pdo->prepare('SELECT id, current_stage FROM people WHERE id = ? LIMIT 1');
            $findStmt->execute([$matchedId]);
            $existing = $findStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $newStage = peopleStageRank($stage) > peopleStageRank((string)($existing['current_stage'] ?? ''))
                ? $stage
                : (string)($existing['current_stage'] ?? $stage);

            if (peopleHasColumn($pdo, 'people', 'email_key') && peopleHasColumn($pdo, 'people', 'phone_key')) {
                $updateSql = 'UPDATE people
                    SET full_name = CASE WHEN LENGTH(TRIM(?)) = 0 THEN full_name ELSE ? END,
                        email = COALESCE(email, ?),
                        phone = COALESCE(phone, ?),
                        email_key = COALESCE(email_key, ?),
                        phone_key = COALESCE(phone_key, ?),
                        current_stage = ?,
                        last_seen_at = NOW()
                    WHERE id = ?';
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$fullName, $fullName, $emailNorm, $phoneNorm, $emailNorm, $phoneNorm, $newStage, $matchedId]);
            } else {
                $updateSql = 'UPDATE people SET full_name = CASE WHEN LENGTH(TRIM(?)) = 0 THEN full_name ELSE ? END, email = COALESCE(email, ?), phone = COALESCE(phone, ?), current_stage = ?, last_seen_at = NOW() WHERE id = ?';
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$fullName, $fullName, $emailNorm, $phoneNorm, $newStage, $matchedId]);
            }

            return $matchedId;
        }

        if (peopleHasColumn($pdo, 'people', 'email_key') && peopleHasColumn($pdo, 'people', 'phone_key')) {
            $insertSql = 'INSERT INTO people (full_name, email, phone, email_key, phone_key, current_stage, first_seen_at, last_seen_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())';
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$fullName, $emailNorm, $phoneNorm, $emailNorm, $phoneNorm, $stage]);
        } else {
            $insertSql = 'INSERT INTO people (full_name, email, phone, current_stage, first_seen_at, last_seen_at) VALUES (?, ?, ?, ?, NOW(), NOW())';
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$fullName, $emailNorm, $phoneNorm, $stage]);
        }

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('peopleFindOrCreateCompat')) {
    function peopleFindOrCreateCompat(PDO $pdo, string $fullName, ?string $email, ?string $phone, string $stage): ?int {
        try {
            return peopleFindOrCreate($pdo, $fullName, $email, $phone, $stage);
        } catch (Exception $e) {
            $msg = (string)$e->getMessage();
            if (stripos($msg, "Unknown column 'p.phone'") === false) {
                throw $e;
            }
        }

        if (!peopleSyncTableExists($pdo)) {
            return null;
        }

        $fullName = trim($fullName);
        $emailNorm = peopleNormalizeEmail($email);
        $phoneNorm = peopleNormalizePhone($phone);

        $emailMatchId = $emailNorm !== null ? peopleFindByEmailKey($pdo, $emailNorm) : null;
        $phoneMatchId = $phoneNorm !== null ? peopleFindByPhoneKey($pdo, $phoneNorm) : null;

        if ($emailMatchId !== null && $phoneMatchId !== null && $emailMatchId !== $phoneMatchId) {
            throw new RuntimeException('Identity conflict: this email and phone are linked to different people records.');
        }

        $matchedId = $emailMatchId ?? $phoneMatchId;
        if ($matchedId !== null) {
            return $matchedId;
        }

        if ($emailNorm === null && $phoneNorm === null && $fullName !== '') {
            $findByName = $pdo->prepare('SELECT id FROM people WHERE LOWER(TRIM(full_name)) = LOWER(?) ORDER BY id ASC LIMIT 1');
            $findByName->execute([$fullName]);
            $nameId = $findByName->fetchColumn();
            if ($nameId !== false) {
                return (int)$nameId;
            }
        }

        $columns = [];
        $values = [];

        if (peopleHasColumn($pdo, 'people', 'full_name')) {
            $columns[] = 'full_name';
            $values[] = $fullName;
        }

        if (peopleHasColumn($pdo, 'people', 'email') && $emailNorm !== null) {
            $columns[] = 'email';
            $values[] = $emailNorm;
        }

        if (peopleHasColumn($pdo, 'people', 'phone') && $phoneNorm !== null) {
            $columns[] = 'phone';
            $values[] = $phoneNorm;
        }

        if (peopleHasColumn($pdo, 'people', 'email_key') && $emailNorm !== null) {
            $columns[] = 'email_key';
            $values[] = $emailNorm;
        }

        if (peopleHasColumn($pdo, 'people', 'phone_key') && $phoneNorm !== null) {
            $columns[] = 'phone_key';
            $values[] = $phoneNorm;
        }

        if (peopleHasColumn($pdo, 'people', 'current_stage')) {
            $columns[] = 'current_stage';
            $values[] = $stage;
        }

        if (peopleHasColumn($pdo, 'people', 'first_seen_at')) {
            $columns[] = 'first_seen_at';
            $values[] = date('Y-m-d H:i:s');
        }

        if (peopleHasColumn($pdo, 'people', 'last_seen_at')) {
            $columns[] = 'last_seen_at';
            $values[] = date('Y-m-d H:i:s');
        }

        if (empty($columns)) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO people (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('peopleLinkRecord')) {
    function peopleLinkRecord(PDO $pdo, string $table, int $recordId, int $personId): void {
        if (!peopleSyncTableExists($pdo)) {
            return;
        }

        $resolvedTable = peopleSyncResolveTable($pdo, $table);
        if ($resolvedTable === null) {
            return;
        }

        $sql = "UPDATE `{$resolvedTable}` SET person_id = ? WHERE id = ? AND (person_id IS NULL OR person_id = 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$personId, $recordId]);
    }
}

if (!function_exists('peopleRelinkRecord')) {
    function peopleRelinkRecord(PDO $pdo, string $table, int $recordId, int $personId): void {
        if (!peopleSyncTableExists($pdo)) {
            return;
        }

        $resolvedTable = peopleSyncResolveTable($pdo, $table);
        if ($resolvedTable === null) {
            return;
        }

        $sql = "UPDATE `{$resolvedTable}` SET person_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$personId, $recordId]);
    }
}

if (!function_exists('peopleSyncRecord')) {
    function peopleSyncRecord(PDO $pdo, string $table, int $recordId, string $fullName, ?string $email, ?string $phone, string $targetStage, ?string $eventType = null, ?string $notes = null): ?int {
        if (!peopleSyncTableExists($pdo)) {
            return null;
        }

        if ($recordId <= 0) {
            return null;
        }

        $personId = peopleFindOrCreateCompat($pdo, $fullName, $email, $phone, $targetStage);
        if (!$personId) {
            return null;
        }

        $resolvedTable = peopleSyncResolveTable($pdo, $table);
        if ($resolvedTable === null) {
            return null;
        }

        peopleRelinkRecord($pdo, $resolvedTable, $recordId, $personId);
        peopleEnsureStage($pdo, $personId, $targetStage);

        if (!empty($eventType)) {
            peopleAddLifecycleEvent($pdo, $personId, $eventType, $resolvedTable, $recordId, $notes);
        }

        return $personId;
    }
}

if (!function_exists('peopleAddLifecycleEvent')) {
    function peopleAddLifecycleEvent(PDO $pdo, int $personId, string $eventType, string $sourceTable, int $sourceId, ?string $notes = null): void {
        if (!peopleSyncTableExists($pdo)) {
            return;
        }

        try {
            $sql = 'INSERT IGNORE INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes) VALUES (?, ?, NOW(), ?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$personId, $eventType, $sourceTable, $sourceId, $notes]);
        } catch (Exception $e) {
            // Non-critical analytics event; skip hard failure.
        }
    }
}

if (!function_exists('peopleEnsureStage')) {
    function peopleEnsureStage(PDO $pdo, int $personId, string $targetStage): void {
        if (!peopleSyncTableExists($pdo) || $personId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('SELECT current_stage FROM people WHERE id = ? LIMIT 1');
        $stmt->execute([$personId]);
        $currentStage = (string)$stmt->fetchColumn();

        if ($currentStage === '') {
            return;
        }

        $newStage = peopleStageRank($targetStage) > peopleStageRank($currentStage)
            ? $targetStage
            : $currentStage;

        $update = $pdo->prepare('UPDATE people SET current_stage = ?, last_seen_at = NOW() WHERE id = ?');
        $update->execute([$newStage, $personId]);
    }
}
