<?php
require_once '../../includes/security.php';
requireLogin('../../login.php');
require_once '../../config/database.php';

$page_title = 'Tithers & Tithe Books - Bridge Ministries International';
$page_heading = 'Tithers & Tithe Books';
$page_header = false;

$success = '';
$error = '';

if (!empty($_SESSION['finance_tithers_flash_success'])) {
    $success = (string)$_SESSION['finance_tithers_flash_success'];
    unset($_SESSION['finance_tithers_flash_success']);
}

function generateNextTitheBookNumber(PDO $pdo): string {
    $current_year_suffix = date('y');
    $max_sequence = 0;

    $stmt = $pdo->query('SELECT book_number FROM tithe_books');
    $book_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($book_numbers as $book_number) {
        $book_number = (string)$book_number;
        if (preg_match('/^BMI(\d+)-(\d{2})$/', $book_number, $matches) !== 1) {
            continue;
        }

        if ($matches[2] !== $current_year_suffix) {
            continue;
        }

        if (isset($matches[1])) {
            $current_sequence = (int)$matches[1];
            if ($current_sequence > $max_sequence) {
                $max_sequence = $current_sequence;
            }
        }
    }

    return 'BMI' . str_pad((string)($max_sequence + 1), 4, '0', STR_PAD_LEFT) . '-' . $current_year_suffix;
}

function validateAndGetBookForAssignment(PDO $pdo, int $tithe_book_id, int $exclude_tither_id = 0): array {
    $book_stmt = $pdo->prepare('SELECT id, status FROM tithe_books WHERE id = ? LIMIT 1');
    $book_stmt->execute([$tithe_book_id]);
    $book = $book_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        throw new Exception('Selected tithe book does not exist.');
    }

    if (($book['status'] ?? '') === 'retired') {
        throw new Exception('Selected tithe book is retired and cannot be assigned.');
    }

    if ($exclude_tither_id > 0) {
        $assigned_stmt = $pdo->prepare('SELECT full_name FROM tithers WHERE tithe_book_id = ? AND id <> ? LIMIT 1');
        $assigned_stmt->execute([$tithe_book_id, $exclude_tither_id]);
    } else {
        $assigned_stmt = $pdo->prepare('SELECT full_name FROM tithers WHERE tithe_book_id = ? LIMIT 1');
        $assigned_stmt->execute([$tithe_book_id]);
    }

    $assigned_tither = $assigned_stmt->fetch(PDO::FETCH_ASSOC);
    if ($assigned_tither) {
        throw new Exception('Selected tithe book is already assigned to ' . (string)$assigned_tither['full_name'] . '.');
    }

    return $book;
}

function syncBookStatus(PDO $pdo, int $book_id): void {
    $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM tithers WHERE tithe_book_id = ?');
    $count_stmt->execute([$book_id]);
    $assigned_count = (int)$count_stmt->fetchColumn();

    if ($assigned_count > 0) {
        $pdo->prepare("UPDATE tithe_books SET status = 'assigned' WHERE id = ? AND status <> 'retired'")->execute([$book_id]);
    } else {
        $pdo->prepare("UPDATE tithe_books SET status = 'available' WHERE id = ? AND status <> 'retired'")->execute([$book_id]);
    }
}

function fetchMemberProfile(PDO $pdo, int $member_id): ?array {
    $stmt = $pdo->prepare(
        "SELECT mr.id AS member_id, p.full_name, p.phone, p.email
         FROM member_roles mr
         JOIN people p ON p.id = mr.person_id
         WHERE mr.id = ?
         LIMIT 1"
    );
    $stmt->execute([$member_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function createAssignedBook(PDO $pdo, ?string $preferred_book_number = null): array {
    $saved = false;
    $attempts = 0;
    $book_id = 0;
    $book_number = '';

    while (!$saved && $attempts < 5) {
        $attempts++;
        if ($attempts === 1 && $preferred_book_number !== null && $preferred_book_number !== '') {
            $book_number = $preferred_book_number;
        } else {
            $book_number = generateNextTitheBookNumber($pdo);
        }
        try {
            $insert_stmt = $pdo->prepare('INSERT INTO tithe_books (book_number, issued_date, status, notes) VALUES (?, ?, ?, ?)');
            $insert_stmt->execute([
                $book_number,
                date('Y-m-d'),
                'assigned',
                'Auto-generated on tither creation',
            ]);
            $book_id = (int)$pdo->lastInsertId();
            $saved = true;
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    if (!$saved || $book_id <= 0) {
        throw new Exception('Unable to auto-generate a tithe book right now. Please try again.');
    }

    return ['id' => $book_id, 'book_number' => $book_number];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add_book') {
                $issued_date = trim($_POST['issued_date'] ?? '');
                $status = trim($_POST['status'] ?? 'available');
                $notes = trim($_POST['notes'] ?? '');

                if (!in_array($status, ['available', 'assigned', 'retired'], true)) {
                    throw new Exception('Invalid book status selected.');
                }

                $saved = false;
                $attempts = 0;
                while (!$saved && $attempts < 5) {
                    $attempts++;
                    $book_number = generateNextTitheBookNumber($pdo);
                    try {
                        $insert_stmt = $pdo->prepare('INSERT INTO tithe_books (book_number, issued_date, status, notes) VALUES (?, ?, ?, ?)');
                        $insert_stmt->execute([
                            $book_number,
                            $issued_date !== '' ? $issued_date : null,
                            $status,
                            $notes !== '' ? $notes : null,
                        ]);
                        $saved = true;
                    } catch (PDOException $e) {
                        if ((string)$e->getCode() !== '23000') {
                            throw $e;
                        }
                    }
                }

                if (!$saved) {
                    throw new Exception('Unable to generate a unique tithe book number right now. Please try again.');
                }

                $_SESSION['finance_tithers_flash_success'] = 'Tithe book added successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'update_book') {
                $book_id = (int)($_POST['book_id'] ?? 0);
                $issued_date = trim($_POST['issued_date'] ?? '');
                $status = trim($_POST['status'] ?? 'available');
                $notes = trim($_POST['notes'] ?? '');

                if ($book_id <= 0) {
                    throw new Exception('Invalid tithe book selected.');
                }

                if (!in_array($status, ['available', 'assigned', 'retired'], true)) {
                    throw new Exception('Invalid book status selected.');
                }

                if ($status === 'retired') {
                    $assigned_stmt = $pdo->prepare('SELECT COUNT(*) FROM tithers WHERE tithe_book_id = ?');
                    $assigned_stmt->execute([$book_id]);
                    if ((int)$assigned_stmt->fetchColumn() > 0) {
                        throw new Exception('Cannot retire a book that is currently assigned to a tither.');
                    }
                }

                $update_stmt = $pdo->prepare('UPDATE tithe_books SET issued_date = ?, status = ?, notes = ? WHERE id = ?');
                $update_stmt->execute([
                    $issued_date !== '' ? $issued_date : null,
                    $status,
                    $notes !== '' ? $notes : null,
                    $book_id,
                ]);

                if ($status !== 'retired') {
                    syncBookStatus($pdo, $book_id);
                }

                $_SESSION['finance_tithers_flash_success'] = 'Tithe book updated successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'retire_book') {
                $book_id = (int)($_POST['book_id'] ?? 0);
                if ($book_id <= 0) {
                    throw new Exception('Invalid tithe book selected.');
                }

                $assigned_stmt = $pdo->prepare('SELECT COUNT(*) FROM tithers WHERE tithe_book_id = ?');
                $assigned_stmt->execute([$book_id]);
                if ((int)$assigned_stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot retire a book that is currently assigned to a tither.');
                }

                $pdo->prepare("UPDATE tithe_books SET status = 'retired' WHERE id = ?")->execute([$book_id]);
                $_SESSION['finance_tithers_flash_success'] = 'Tithe book retired successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'reactivate_book') {
                $book_id = (int)($_POST['book_id'] ?? 0);
                if ($book_id <= 0) {
                    throw new Exception('Invalid tithe book selected.');
                }

                $pdo->prepare("UPDATE tithe_books SET status = 'available' WHERE id = ?")->execute([$book_id]);
                syncBookStatus($pdo, $book_id);

                $_SESSION['finance_tithers_flash_success'] = 'Tithe book reactivated successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'delete_book') {
                $book_id = (int)($_POST['book_id'] ?? 0);
                if ($book_id <= 0) {
                    throw new Exception('Invalid tithe book selected.');
                }

                $assigned_stmt = $pdo->prepare('SELECT COUNT(*) FROM tithers WHERE tithe_book_id = ?');
                $assigned_stmt->execute([$book_id]);
                if ((int)$assigned_stmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete a book that is currently assigned to a tither.');
                }

                $pdo->prepare('DELETE FROM tithe_books WHERE id = ?')->execute([$book_id]);
                $_SESSION['finance_tithers_flash_success'] = 'Tithe book deleted successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'add_tither') {
                $member_id = (int)($_POST['member_id'] ?? 0);
                $full_name = trim($_POST['full_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $tithe_book_id = (int)($_POST['tithe_book_id'] ?? 0);
                $suggested_book_number = trim($_POST['suggested_book_number'] ?? '');
                $status = trim($_POST['status'] ?? 'active');
                $start_date = trim($_POST['start_date'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                $created_book_number = '';

                if ($member_id > 0) {
                    $member_profile = fetchMemberProfile($pdo, $member_id);
                    if (!$member_profile) {
                        throw new Exception('Selected member was not found.');
                    }
                    $full_name = trim((string)($member_profile['full_name'] ?? $full_name));
                    if ($phone === '') {
                        $phone = trim((string)($member_profile['phone'] ?? ''));
                    }
                    if ($email === '') {
                        $email = trim((string)($member_profile['email'] ?? ''));
                    }
                }

                if ($full_name === '') {
                    throw new Exception('Tither full name is required.');
                }

                if (!in_array($status, ['active', 'inactive'], true)) {
                    throw new Exception('Invalid tither status selected.');
                }

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format.');
                }

                $pdo->beginTransaction();
                try {
                    if ($tithe_book_id > 0) {
                        validateAndGetBookForAssignment($pdo, $tithe_book_id);
                    } else {
                        $new_book = createAssignedBook($pdo, $suggested_book_number !== '' ? $suggested_book_number : null);
                        $tithe_book_id = (int)$new_book['id'];
                        $created_book_number = (string)$new_book['book_number'];
                    }

                    $insert_stmt = $pdo->prepare('INSERT INTO tithers (member_id, full_name, phone, email, tithe_book_id, status, start_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $insert_stmt->execute([
                        $member_id > 0 ? $member_id : null,
                        $full_name,
                        $phone !== '' ? $phone : null,
                        $email !== '' ? $email : null,
                        $tithe_book_id > 0 ? $tithe_book_id : null,
                        $status,
                        $start_date !== '' ? $start_date : null,
                        $notes !== '' ? $notes : null,
                    ]);

                    if ($tithe_book_id > 0) {
                        syncBookStatus($pdo, $tithe_book_id);
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if ($created_book_number !== '') {
                    $_SESSION['finance_tithers_flash_success'] = 'Tither added successfully with book ' . $created_book_number . '.';
                } else {
                    $_SESSION['finance_tithers_flash_success'] = 'Tither added successfully.';
                }
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'update_tither') {
                $tither_id = (int)($_POST['tither_id'] ?? 0);
                $member_id = (int)($_POST['member_id'] ?? 0);
                $full_name = trim($_POST['full_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $tithe_book_id = array_key_exists('tithe_book_id', $_POST) ? (int)($_POST['tithe_book_id'] ?? 0) : 0;
                $status = trim($_POST['status'] ?? 'active');
                $start_date = trim($_POST['start_date'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if ($member_id > 0) {
                    $member_profile = fetchMemberProfile($pdo, $member_id);
                    if (!$member_profile) {
                        throw new Exception('Selected member was not found.');
                    }
                    $full_name = trim((string)($member_profile['full_name'] ?? $full_name));
                    if ($phone === '') {
                        $phone = trim((string)($member_profile['phone'] ?? ''));
                    }
                    if ($email === '') {
                        $email = trim((string)($member_profile['email'] ?? ''));
                    }
                }

                if ($tither_id <= 0) {
                    throw new Exception('Invalid tither selected.');
                }

                if ($full_name === '') {
                    throw new Exception('Tither full name is required.');
                }

                if (!in_array($status, ['active', 'inactive'], true)) {
                    throw new Exception('Invalid tither status selected.');
                }

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format.');
                }

                $existing_stmt = $pdo->prepare('SELECT tithe_book_id FROM tithers WHERE id = ? LIMIT 1');
                $existing_stmt->execute([$tither_id]);
                $existing = $existing_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new Exception('Selected tither was not found.');
                }

                $old_book_id = (int)($existing['tithe_book_id'] ?? 0);

                if (!array_key_exists('tithe_book_id', $_POST)) {
                    $tithe_book_id = $old_book_id;
                }

                if ($tithe_book_id > 0) {
                    validateAndGetBookForAssignment($pdo, $tithe_book_id, $tither_id);
                }

                $update_stmt = $pdo->prepare('UPDATE tithers SET member_id = ?, full_name = ?, phone = ?, email = ?, tithe_book_id = ?, status = ?, start_date = ?, notes = ? WHERE id = ?');
                $update_stmt->execute([
                    $member_id > 0 ? $member_id : null,
                    $full_name,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $tithe_book_id > 0 ? $tithe_book_id : null,
                    $status,
                    $start_date !== '' ? $start_date : null,
                    $notes !== '' ? $notes : null,
                    $tither_id,
                ]);

                if ($old_book_id > 0) {
                    syncBookStatus($pdo, $old_book_id);
                }
                if ($tithe_book_id > 0) {
                    syncBookStatus($pdo, $tithe_book_id);
                }

                $_SESSION['finance_tithers_flash_success'] = 'Tither updated successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'retire_tither') {
                $tither_id = (int)($_POST['tither_id'] ?? 0);
                if ($tither_id <= 0) {
                    throw new Exception('Invalid tither selected.');
                }

                $pdo->beginTransaction();
                try {
                    $tither_stmt = $pdo->prepare('SELECT tithe_book_id FROM tithers WHERE id = ? LIMIT 1');
                    $tither_stmt->execute([$tither_id]);
                    $tither = $tither_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$tither) {
                        throw new Exception('Selected tither was not found.');
                    }

                    $book_id = (int)($tither['tithe_book_id'] ?? 0);

                    $pdo->prepare("UPDATE tithers SET status = 'inactive' WHERE id = ?")->execute([$tither_id]);

                    if ($book_id > 0) {
                        $pdo->prepare("UPDATE tithe_books SET status = 'retired' WHERE id = ?")->execute([$book_id]);
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $_SESSION['finance_tithers_flash_success'] = 'Tither retired successfully. Assigned tithe book retired automatically.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'reactivate_tither') {
                $tither_id = (int)($_POST['tither_id'] ?? 0);
                if ($tither_id <= 0) {
                    throw new Exception('Invalid tither selected.');
                }

                $pdo->prepare("UPDATE tithers SET status = 'active' WHERE id = ?")->execute([$tither_id]);
                $_SESSION['finance_tithers_flash_success'] = 'Tither reactivated successfully.';
                header('Location: tithers.php');
                exit;
            }

            if ($action === 'delete_tither') {
                $tither_id = (int)($_POST['tither_id'] ?? 0);
                if ($tither_id <= 0) {
                    throw new Exception('Invalid tither selected.');
                }

                $old_stmt = $pdo->prepare('SELECT tithe_book_id FROM tithers WHERE id = ? LIMIT 1');
                $old_stmt->execute([$tither_id]);
                $old_tither = $old_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$old_tither) {
                    throw new Exception('Selected tither was not found.');
                }

                $old_book_id = (int)($old_tither['tithe_book_id'] ?? 0);

                $pdo->beginTransaction();
                try {
                    $pdo->prepare('DELETE FROM tithers WHERE id = ?')->execute([$tither_id]);

                    if ($old_book_id > 0) {
                        $pdo->prepare('DELETE FROM tithe_books WHERE id = ?')->execute([$old_book_id]);
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $_SESSION['finance_tithers_flash_success'] = 'Tither and assigned tithe book deleted successfully.';
                header('Location: tithers.php');
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$books = [];
$tithers = [];
$members = [];

try {
    $books_stmt = $pdo->query('SELECT id, book_number, issued_date, status, notes FROM tithe_books ORDER BY book_number ASC');
    $books = $books_stmt->fetchAll(PDO::FETCH_ASSOC);

    $tithers_stmt = $pdo->query(
        "SELECT t.id, t.member_id, t.full_name, t.phone, t.email, t.status, t.start_date, t.notes, t.tithe_book_id, b.book_number
         FROM tithers t
         LEFT JOIN tithe_books b ON b.id = t.tithe_book_id
         ORDER BY t.full_name ASC"
    );
    $tithers = $tithers_stmt->fetchAll(PDO::FETCH_ASSOC);

    $members_stmt = $pdo->query(
        "SELECT mr.id, p.full_name, p.phone, p.email
         FROM member_roles mr
         JOIN people p ON p.id = mr.person_id
         WHERE mr.status = 'active'
         ORDER BY p.full_name ASC"
    );
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $error !== '' ? $error : 'Unable to load tithers and books data.';
}

$book_map = [];
foreach ($books as $book) {
    $book_map[(int)$book['id']] = $book;
}

$tither_map = [];
foreach ($tithers as $tither) {
    $tither_map[(int)$tither['id']] = $tither;
}

$edit_tither = null;
if (isset($_GET['edit_tither'])) {
    $edit_tither_id = (int)$_GET['edit_tither'];
    if ($edit_tither_id > 0 && isset($tither_map[$edit_tither_id])) {
        $edit_tither = $tither_map[$edit_tither_id];
    }
}

$next_book_number = 'BMI0001-' . date('y');
try {
    $next_book_number = generateNextTitheBookNumber($pdo);
} catch (Exception $e) {
    $next_book_number = 'BMI0001-' . date('y');
}

$assigned_book_ids = [];
foreach ($tithers as $tither) {
    if (!empty($tither['tithe_book_id'])) {
        $assigned_book_ids[(int)$tither['tithe_book_id']] = true;
    }
}

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo @filemtime('../../assets/css/finance.css'); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="row g-3 finance-stats mb-1">
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-income"><i class="bi bi-person-badge-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Total Tithers</span>
                    <h3 class="finance-stat-value"><?php echo number_format(count($tithers)); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-journal-bookmark-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Tithe Books</span>
                    <h3 class="finance-stat-value"><?php echo number_format(count($books)); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-person-check-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Active Tithers</span>
                    <h3 class="finance-stat-value"><?php echo number_format(count(array_filter($tithers, static fn($t) => ($t['status'] ?? '') === 'active'))); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-person-plus-fill"></i> <?php echo $edit_tither ? 'Edit Tither' : 'Add Tither'; ?></h2>
                    <p>Register and manage tithers. A unique tithe book is auto-created during tither registration.</p>
                </div>

                <form method="POST" class="finance-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_tither ? 'update_tither' : 'add_tither'; ?>">
                    <?php if ($edit_tither): ?>
                        <input type="hidden" name="tither_id" value="<?php echo (int)$edit_tither['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="suggested_book_number" value="<?php echo htmlspecialchars((string)$next_book_number); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Link To Member (Optional)</label>
                        <select class="form-select" name="member_id" id="member_id">
                            <option value="">Manual entry</option>
                            <?php foreach ($members as $member): ?>
                                <option
                                    value="<?php echo (int)$member['id']; ?>"
                                    data-name="<?php echo htmlspecialchars((string)$member['full_name']); ?>"
                                    data-phone="<?php echo htmlspecialchars((string)($member['phone'] ?? '')); ?>"
                                    data-email="<?php echo htmlspecialchars((string)($member['email'] ?? '')); ?>"
                                    <?php echo ((int)($edit_tither['member_id'] ?? 0) === (int)$member['id']) ? ' selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars((string)$member['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars((string)($edit_tither['full_name'] ?? '')); ?>" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars((string)($edit_tither['phone'] ?? '')); ?>">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars((string)($edit_tither['email'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tithe Book</label>
                        <?php if ($edit_tither && !empty($edit_tither['book_number'])): ?>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string)$edit_tither['book_number']); ?>" readonly>
                        <?php else: ?>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string)$next_book_number); ?>" readonly>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active"<?php echo (($edit_tither['status'] ?? 'active') === 'active') ? ' selected' : ''; ?>>Active</option>
                                <option value="inactive"<?php echo (($edit_tither['status'] ?? '') === 'inactive') ? ' selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars((string)($edit_tither['start_date'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars((string)($edit_tither['notes'] ?? '')); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save2-fill me-1"></i> <?php echo $edit_tither ? 'Update Tither' : 'Save Tither'; ?></button>
                    <?php if ($edit_tither): ?>
                        <a href="tithers.php" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-journal-bookmark"></i> Tithe Books</h2>
                    <p>Current tithe book inventory.</p>
                </div>
                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead><tr><th>Book #</th><th>Issued</th><th>Status</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($books)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No tithe books registered.</td></tr>
                            <?php else: ?>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars((string)$book['book_number']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($book['issued_date'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)$book['status'])); ?></td>
                                        <td><?php echo htmlspecialchars((string)($book['notes'] ?: '-')); ?></td>
                                        <td class="text-end">
                                            <?php if (($book['status'] ?? '') !== 'retired'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Retire this tithe book?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="retire_book">
                                                    <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Retire</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Reactivate this tithe book?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="reactivate_book">
                                                    <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this tithe book?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_book">
                                                <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-people-fill"></i> Tithers</h2>
                    <p>Registered tithe contributors and assigned books.</p>
                </div>
                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Book</th><th>Phone</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($tithers)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No tithers registered.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tithers as $tither): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars((string)$tither['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($tither['book_number'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($tither['phone'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)$tither['status'])); ?></td>
                                        <td class="text-end">
                                            <a href="tithers.php?edit_tither=<?php echo (int)$tither['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <?php if (($tither['status'] ?? '') === 'active'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Retire this tither? The assigned tithe book will also be retired.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="retire_tither">
                                                    <input type="hidden" name="tither_id" value="<?php echo (int)$tither['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Retire</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Reactivate this tither?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="reactivate_tither">
                                                    <input type="hidden" name="tither_id" value="<?php echo (int)$tither['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this tither? The assigned tithe book will also be deleted.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_tither">
                                                <input type="hidden" name="tither_id" value="<?php echo (int)$tither['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const memberSelect = document.getElementById('member_id');
    const nameInput = document.querySelector('input[name="full_name"]');
    const phoneInput = document.querySelector('input[name="phone"]');
    const emailInput = document.querySelector('input[name="email"]');

    if (!memberSelect || !nameInput || !phoneInput || !emailInput) {
        return;
    }

    memberSelect.addEventListener('change', function () {
        const option = memberSelect.options[memberSelect.selectedIndex];
        if (!option || !option.value) {
            return;
        }

        nameInput.value = option.getAttribute('data-name') || nameInput.value;
        if (!phoneInput.value) {
            phoneInput.value = option.getAttribute('data-phone') || '';
        }
        if (!emailInput.value) {
            emailInput.value = option.getAttribute('data-email') || '';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
