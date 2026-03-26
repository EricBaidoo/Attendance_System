<?php

require_once __DIR__ . '/../config/database.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
};

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

if (!$tableExists('people')) {
    fwrite(STDERR, "people table does not exist. Run db_updates.sql first.\n");
    exit(1);
}

$hasLifecycle = $tableExists('person_lifecycle_events');
$hasMembers = $tableExists('members');
$hasVisitors = $tableExists('visitors');
$hasConverts = $tableExists('new_converts');

if (!$hasMembers || !$hasVisitors || !$hasConverts) {
    fwrite(STDERR, "Required source tables are missing (members, visitors, new_converts).\n");
    exit(1);
}

$hasVisitorsBecameMember = $columnExists('visitors', 'became_member');
$hasPeopleEmailKey = $columnExists('people', 'email_key');
$hasPeoplePhoneKey = $columnExists('people', 'phone_key');

try {
    $pdo->beginTransaction();

    // Reset links so rebuilt people IDs can be re-mapped deterministically.
    $pdo->exec("UPDATE members SET person_id = NULL WHERE person_id IS NOT NULL");
    $pdo->exec("UPDATE visitors SET person_id = NULL WHERE person_id IS NOT NULL");
    $pdo->exec("UPDATE new_converts SET person_id = NULL WHERE person_id IS NOT NULL");

    if ($hasLifecycle) {
        $pdo->exec("DELETE FROM person_lifecycle_events");
    }

    // Replace people data fully from module source tables.
    $pdo->exec("DELETE FROM people");

    // 1) Members first (highest stage precedence)
    $pdo->exec(
        "INSERT INTO people (full_name, email, phone, alt_phone, gender, date_of_birth, location, occupation, current_stage, first_seen_at, last_seen_at)
         SELECT
            m.name,
            NULLIF(TRIM(m.email), ''),
            NULLIF(TRIM(m.phone), ''),
            NULLIF(TRIM(m.phone2), ''),
            NULLIF(TRIM(m.gender), ''),
            m.dob,
            NULLIF(TRIM(m.location), ''),
            NULLIF(TRIM(m.occupation), ''),
            'member',
            COALESCE(m.date_joined, m.created_at, NOW()),
            COALESCE(m.created_at, NOW())
         FROM members m
         WHERE COALESCE(TRIM(m.name), '') <> ''
           AND NOT EXISTS (
               SELECT 1 FROM people p
               WHERE (
                    NULLIF(TRIM(m.email), '') IS NOT NULL
                    AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email))
               )
               OR (
                    NULLIF(TRIM(m.phone), '') IS NOT NULL
                    AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '')
               )
           )"
    );

    // 2) New converts next
    $pdo->exec(
        "INSERT INTO people (full_name, email, phone, current_stage, first_seen_at, last_seen_at)
         SELECT
            nc.name,
            NULLIF(TRIM(nc.email), ''),
            NULLIF(TRIM(nc.phone), ''),
            CASE WHEN COALESCE(nc.status, '') = 'converted_to_member' THEN 'member' ELSE 'new_convert' END,
            COALESCE(nc.date_converted, nc.created_at, NOW()),
            COALESCE(nc.created_at, NOW())
         FROM new_converts nc
         WHERE COALESCE(TRIM(nc.name), '') <> ''
           AND NOT EXISTS (
               SELECT 1 FROM people p
               WHERE (
                    NULLIF(TRIM(nc.email), '') IS NOT NULL
                    AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email))
               )
               OR (
                    NULLIF(TRIM(nc.phone), '') IS NOT NULL
                    AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '')
               )
           )"
    );

    // 3) Visitors last
    $visitorMemberClause = $hasVisitorsBecameMember ? " OR COALESCE(v.became_member, 'no') = 'yes'" : "";
    $pdo->exec(
        "INSERT INTO people (full_name, email, phone, current_stage, first_seen_at, last_seen_at)
         SELECT
            v.name,
            NULLIF(TRIM(v.email), ''),
            NULLIF(TRIM(v.phone), ''),
            CASE
                WHEN COALESCE(v.status, '') IN ('converted_to_member', 'converted')" . $visitorMemberClause . " THEN 'member'
                WHEN COALESCE(v.status, '') IN ('converted_to_convert', 'converted_to_new_convert') THEN 'new_convert'
                ELSE 'visitor'
            END,
            COALESCE(v.created_at, v.date, NOW()),
            COALESCE(v.created_at, v.date, NOW())
         FROM visitors v
         WHERE COALESCE(TRIM(v.name), '') <> ''
           AND NOT EXISTS (
               SELECT 1 FROM people p
               WHERE (
                    NULLIF(TRIM(v.email), '') IS NOT NULL
                    AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email))
               )
               OR (
                    NULLIF(TRIM(v.phone), '') IS NOT NULL
                    AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '')
               )
           )"
    );

    // Optional normalized key backfill for strict uniqueness mode.
    if ($hasPeopleEmailKey || $hasPeoplePhoneKey) {
        $sets = [];
        if ($hasPeopleEmailKey) {
            $sets[] = "email_key = CASE WHEN NULLIF(TRIM(email), '') IS NULL THEN NULL ELSE LOWER(TRIM(email)) END";
        }
        if ($hasPeoplePhoneKey) {
            $sets[] = "phone_key = CASE WHEN NULLIF(TRIM(phone), '') IS NULL THEN NULL ELSE REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') END";
        }
        if (!empty($sets)) {
            $pdo->exec("UPDATE people SET " . implode(', ', $sets));
        }
    }

    // Re-link module tables by best available identity match.
    $pdo->exec(
        "UPDATE members m
         SET m.person_id = (
            SELECT p.id
            FROM people p
            WHERE (
                NULLIF(TRIM(m.email), '') IS NOT NULL
                AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email))
            )
            OR (
                NULLIF(TRIM(m.phone), '') IS NOT NULL
                AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '')
            )
            OR (
                COALESCE(TRIM(m.name), '') <> ''
                AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(m.name))
            )
            ORDER BY
                CASE
                    WHEN NULLIF(TRIM(m.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email)) THEN 1
                    WHEN NULLIF(TRIM(m.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '') THEN 2
                    ELSE 3
                END,
                p.id
            LIMIT 1
         )"
    );

    $pdo->exec(
        "UPDATE new_converts nc
         SET nc.person_id = (
            SELECT p.id
            FROM people p
            WHERE (
                NULLIF(TRIM(nc.email), '') IS NOT NULL
                AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email))
            )
            OR (
                NULLIF(TRIM(nc.phone), '') IS NOT NULL
                AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '')
            )
            OR (
                COALESCE(TRIM(nc.name), '') <> ''
                AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(nc.name))
            )
            ORDER BY
                CASE
                    WHEN NULLIF(TRIM(nc.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email)) THEN 1
                    WHEN NULLIF(TRIM(nc.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '') THEN 2
                    ELSE 3
                END,
                p.id
            LIMIT 1
         )"
    );

    $pdo->exec(
        "UPDATE visitors v
         SET v.person_id = (
            SELECT p.id
            FROM people p
            WHERE (
                NULLIF(TRIM(v.email), '') IS NOT NULL
                AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email))
            )
            OR (
                NULLIF(TRIM(v.phone), '') IS NOT NULL
                AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '')
            )
            OR (
                COALESCE(TRIM(v.name), '') <> ''
                AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(v.name))
            )
            ORDER BY
                CASE
                    WHEN NULLIF(TRIM(v.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email)) THEN 1
                    WHEN NULLIF(TRIM(v.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '') THEN 2
                    ELSE 3
                END,
                p.id
            LIMIT 1
         )"
    );

    if ($hasLifecycle) {
        $pdo->exec(
            "INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
             SELECT v.person_id, 'visitor_checked_in', COALESCE(v.created_at, v.date, NOW()), 'visitors', v.id, 'Rebuilt from visitors table'
             FROM visitors v
             WHERE v.person_id IS NOT NULL"
        );

        $pdo->exec(
            "INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
             SELECT nc.person_id, 'became_new_convert', COALESCE(nc.date_converted, nc.created_at, NOW()), 'new_converts', nc.id, 'Rebuilt from new_converts table'
             FROM new_converts nc
             WHERE nc.person_id IS NOT NULL"
        );

        $pdo->exec(
            "INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
             SELECT m.person_id, 'became_member', COALESCE(m.date_joined, m.created_at, NOW()), 'members', m.id, 'Rebuilt from members table'
             FROM members m
             WHERE m.person_id IS NOT NULL"
        );
    }

    $pdo->commit();

    $counts = [
        'people' => (int)$pdo->query("SELECT COUNT(*) FROM people")->fetchColumn(),
        'members_linked' => (int)$pdo->query("SELECT COUNT(*) FROM members WHERE person_id IS NOT NULL")->fetchColumn(),
        'visitors_linked' => (int)$pdo->query("SELECT COUNT(*) FROM visitors WHERE person_id IS NOT NULL")->fetchColumn(),
        'converts_linked' => (int)$pdo->query("SELECT COUNT(*) FROM new_converts WHERE person_id IS NOT NULL")->fetchColumn(),
        'dup_email_groups' => (int)$pdo->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(email)) e FROM people WHERE email IS NOT NULL AND TRIM(email) <> '' GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1) x")->fetchColumn(),
        'dup_phone_groups' => (int)$pdo->query("SELECT COUNT(*) FROM (SELECT REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') p FROM people WHERE phone IS NOT NULL AND TRIM(phone) <> '' GROUP BY REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') HAVING COUNT(*) > 1) x")->fetchColumn(),
    ];

    echo "People rebuild complete.\n";
    echo "People rows: {$counts['people']}\n";
    echo "Members linked: {$counts['members_linked']}\n";
    echo "Visitors linked: {$counts['visitors_linked']}\n";
    echo "New converts linked: {$counts['converts_linked']}\n";
    echo "Duplicate email groups: {$counts['dup_email_groups']}\n";
    echo "Duplicate phone groups: {$counts['dup_phone_groups']}\n";

    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Rebuild failed: " . $e->getMessage() . "\n");
    exit(1);
}
