<?php
// api/selection.php
// GET    — επιστρέφει επιλογές του logged-in student
// POST   — αποθηκεύει επιλογές (replace all)
// PUT    — submit αίτησης
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

header('Content-Type: application/json; charset=utf-8');

const BUDGET = 1000.00;

$method = $_SERVER['REQUEST_METHOD'];
$user   = requireLogin();
$studentId = (int)$user['id'];

if ($user['role'] !== 'student') json(['error' => 'Μόνο για σπουδαστές'], 403);



function logAction(int $studentId, string $action, string $details = ''): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = db()->prepare(
        'INSERT INTO activity_log (student_id, action, details, ip) VALUES (?,?,?,?)'
    );
    $stmt->execute([$studentId, $action, $details, $ip]);
}

// ── GET — τρέχουσες επιλογές ─────────────────────────────────
if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT b.id, b.title, b.author, b.cost, b.direction
         FROM selections s JOIN books b ON b.id = s.book_id
         WHERE s.student_id = ?'
    );
    $stmt->execute([$studentId]);
    $books = $stmt->fetchAll();

    // submitted flag
    $sub = db()->prepare('SELECT submitted FROM users WHERE id=?');
    $sub->execute([$studentId]);
    $submitted = (bool)$sub->fetchColumn();

    json(['books' => $books, 'submitted' => $submitted]);
}

// ── POST — αποθήκευση επιλογών ───────────────────────────────
if ($method === 'POST') {
    // αν έχει ήδη υποβληθεί, απαγορεύεται η αλλαγή
    $sub = db()->prepare('SELECT submitted FROM users WHERE id=?');
    $sub->execute([$studentId]);
    if ((bool)$sub->fetchColumn()) json(['error' => 'Έχεις ήδη υποβάλει αίτηση.'], 403);

    $d       = input();
    $bookIds = array_map('intval', $d['book_ids'] ?? []);

    if (empty($bookIds)) {
        // διαγραφή όλων
        db()->prepare('DELETE FROM selections WHERE student_id=?')->execute([$studentId]);
        logAction($studentId, 'edit_selection', 'Άδεια επιλογή');
        json(['ok' => true, 'total' => 0.0]);
    }

    // έλεγχος ότι τα βιβλία ανήκουν στη σωστή κατεύθυνση
    $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
    $params = array_merge([$user['direction']], $bookIds);
    $stmt = db()->prepare(
        "SELECT id, cost FROM books WHERE direction=? AND id IN ($placeholders)"
    );
    $stmt->execute($params);
    $validBooks = $stmt->fetchAll();
    $validIds   = array_column($validBooks, 'id');

    // budget check
    $total = array_sum(array_column($validBooks, 'cost'));
    if ($total > BUDGET) json(['error' => 'Υπερβαίνει το όριο 1000€.'], 422);

    // replace selections
    $pdo = db();
    $pdo->prepare('DELETE FROM selections WHERE student_id=?')->execute([$studentId]);
    $ins = $pdo->prepare('INSERT INTO selections (student_id, book_id) VALUES (?,?)');
    foreach ($validIds as $bid) $ins->execute([$studentId, $bid]);

      $titles = implode(', ', array_column($validBooks, 'title'));
    logAction($studentId, 'edit_selection', "Επιλογή: {$titles} | Σύνολο: " . round($total,2) . '€');

    json(['ok' => true, 'total' => round($total, 2)]);
}

// ── PUT — submit αίτησης ─────────────────────────────────────
if ($method === 'PUT') {
    $d      = input();
    $action = $d['action'] ?? 'submit';

    if ($action === 'unsubmit') {
        // επεξεργασία: άρση submit
        db()->prepare("UPDATE users SET submitted=0 WHERE id=?")->execute([$studentId]);
           logAction($studentId, 'unsubmit', 'Τροποποίηση αίτησης');
        json(['ok' => true]);
    }

    // validate budget πριν submit
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(b.cost),0) as total
         FROM selections s JOIN books b ON b.id=s.book_id
         WHERE s.student_id=?'
    );
    $stmt->execute([$studentId]);
    $total = (float)$stmt->fetchColumn();

    // check τουλάχιστον ένα βιβλίο
    $cnt = db()->prepare('SELECT COUNT(*) FROM selections WHERE student_id=?');
    $cnt->execute([$studentId]);
    if ((int)$cnt->fetchColumn() === 0) json(['error' => 'Επέλεξε τουλάχιστον ένα βιβλίο.'], 422);

    if ($total > BUDGET) json(['error' => 'Υπερβαίνει το όριο 1000€.'], 422);

    db()->prepare("UPDATE users SET submitted=1 WHERE id=?")->execute([$studentId]);

     logAction($studentId, 'submit', 'Υποβολή αίτησης | Σύνολο: ' . round($total,2) . '€');

    json(['ok' => true, 'total' => round($total, 2)]);
}

json(['error' => 'Method not allowed'], 405);
