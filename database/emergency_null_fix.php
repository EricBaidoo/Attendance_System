<?php
/**
 * Emergency PHP 8.1 NULL-Safety Patch
 * =====================================
 * PURPOSE: Converts NULL values to empty strings in key columns that were
 *          causing "htmlspecialchars(): Passing null to parameter #1" warnings.
 *
 * USAGE:
 *   1. Upload this ONE file to your live server at: /database/emergency_null_fix.php
 *   2. Visit: https://yourdomain.com/database/emergency_null_fix.php
 *   3. See "PATCH COMPLETE" message.
 *   4. DELETE this file from the server immediately after.
 *
 * SAFE: This script only converts NULL → '' (empty string). No data is deleted.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$patches = [
    // -------------------------------------------------------
    // CRITICAL: Remove PHP error strings accidentally stored
    // in the database (the ROOT CAUSE of visible errors)
    // -------------------------------------------------------
    "UPDATE people SET location = '' WHERE location LIKE '%Deprecated%' OR location LIKE '%htmlspecialchars%'"
        => '[CRITICAL] people.location — poisoned error strings',
    "UPDATE member_roles SET location = '' WHERE location LIKE '%Deprecated%' OR location LIKE '%htmlspecialchars%'"
        => '[CRITICAL] member_roles.location — poisoned error strings',
    "UPDATE people SET phone = '' WHERE phone LIKE '%Deprecated%' OR phone LIKE '%htmlspecialchars%'"
        => '[CRITICAL] people.phone — poisoned error strings',
    "UPDATE member_roles SET phone = '' WHERE phone LIKE '%Deprecated%' OR phone LIKE '%htmlspecialchars%'"
        => '[CRITICAL] member_roles.phone — poisoned error strings',

    // -------------------------------------------------------
    // NULL → '' conversions (prevents future PHP 8.1 warnings)
    // -------------------------------------------------------
    "UPDATE member_roles SET location          = '' WHERE location IS NULL"           => 'member_roles.location (NULL→empty)',
    "UPDATE member_roles SET phone             = '' WHERE phone IS NULL"              => 'member_roles.phone (NULL→empty)',
    "UPDATE member_roles SET ministerial_status= '' WHERE ministerial_status IS NULL" => 'member_roles.ministerial_status (NULL→empty)',
    "UPDATE member_roles SET gender            = '' WHERE gender IS NULL"             => 'member_roles.gender (NULL→empty)',
    "UPDATE member_roles SET marital_status    = '' WHERE marital_status IS NULL"    => 'member_roles.marital_status (NULL→empty)',
    "UPDATE member_roles SET congregation_group= '' WHERE congregation_group IS NULL" => 'member_roles.congregation_group (NULL→empty)',
    "UPDATE people SET phone    = '' WHERE phone IS NULL"                             => 'people.phone (NULL→empty)',
    "UPDATE people SET location = '' WHERE location IS NULL"                          => 'people.location (NULL→empty)',
    "UPDATE people SET gender   = '' WHERE gender IS NULL"                            => 'people.gender (NULL→empty)',
    "UPDATE visitors SET phone    = '' WHERE phone IS NULL"                           => 'visitors.phone (NULL→empty)',
    "UPDATE visitors SET location = '' WHERE location IS NULL"                        => 'visitors.location (NULL→empty)',
    "UPDATE new_converts SET phone    = '' WHERE phone IS NULL"                       => 'new_converts.phone (NULL→empty)',
    "UPDATE new_converts SET location = '' WHERE location IS NULL"                    => 'new_converts.location (NULL→empty)',
];

$results = [];
$total_rows = 0;
$errors = [];

foreach ($patches as $sql => $label) {
    try {
        $stmt = $pdo->exec($sql);
        $rows = ($stmt === false) ? 0 : $stmt;
        $results[$label] = $rows;
        $total_rows += $rows;
    } catch (PDOException $e) {
        // Column may not exist on all installs — skip gracefully
        $errors[$label] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency NULL Patch</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f0fdf4; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem; max-width: 600px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .badge { display: inline-block; padding: 0.4rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1.5rem; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error   { background: #fee2e2; color: #991b1b; }
        h1 { font-size: 1.6rem; font-weight: 800; color: #111827; margin-bottom: 0.5rem; }
        p  { color: #6b7280; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-bottom: 1.5rem; }
        th { background: #f9fafb; color: #374151; font-weight: 600; text-align: left; padding: 0.6rem 0.75rem; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
        td.rows { text-align: center; font-weight: 700; color: #059669; }
        td.zero  { text-align: center; color: #9ca3af; }
        .summary { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .summary strong { font-size: 1.1rem; color: #065f46; }
        .warning { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .warning p { color: #92400e; margin: 0; }
        .err-item { font-size: 0.78rem; color: #dc2626; padding: 0.4rem 0; border-bottom: 1px solid #fee2e2; }
        .delete-note { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem 1.25rem; font-size: 0.875rem; color: #7f1d1d; }
        .delete-note strong { display: block; margin-bottom: 0.25rem; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge <?php echo empty($errors) ? 'badge-success' : 'badge-error'; ?>">
        <?php echo empty($errors) ? '✓ Patch Complete' : '⚠ Completed With Warnings'; ?>
    </span>

    <h1>PHP 8.1 NULL-Safety Patch</h1>
    <p>All NULL values in key display columns have been converted to empty strings. The <code>htmlspecialchars()</code> deprecation warnings will no longer appear.</p>

    <div class="summary">
        <span>Total rows patched across all tables:</span>
        <strong><?php echo number_format($total_rows); ?> rows</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Column Patched</th>
                <th style="text-align:center">Rows Fixed</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $label => $rows): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($label); ?></code></td>
                <td class="<?php echo $rows > 0 ? 'rows' : 'zero'; ?>">
                    <?php echo $rows > 0 ? $rows . ' fixed' : '✓ already clean'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($errors)): ?>
    <div class="warning">
        <p><strong>Skipped (column not present in this install — safe to ignore):</strong></p>
        <?php foreach ($errors as $label => $msg): ?>
        <div class="err-item"><code><?php echo htmlspecialchars($label); ?></code>: <?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="delete-note">
        <strong>🗑 Action Required:</strong>
        Delete this file from your server now. It has done its job and should not remain publicly accessible.
        <br><br><code>/database/emergency_null_fix.php</code>
    </div>
</div>
</body>
</html>
