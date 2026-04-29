<?php
// api/report.php — admin only
// GET ?type=summary|students|demand|csv

require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

requireAdmin();
$type = $_GET['type'] ?? 'summary';

$pdo = db();

if ($type === 'summary') {
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $done    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND submitted=1")->fetchColumn();
    $avgRow  = $pdo->query(
        "SELECT AVG(sub.total) FROM (
           SELECT SUM(b.cost) as total
           FROM users u
           JOIN selections s ON s.student_id=u.id
           JOIN books b ON b.id=s.book_id
           WHERE u.submitted=1
           GROUP BY u.id
         ) sub"
    )->fetchColumn();
    json([
        'total'   => $total,
        'done'    => $done,
        'pending' => $total - $done,
        'avg'     => $avgRow ? round((float)$avgRow, 2) : null,
    ]);
}

if ($type === 'students') {
    $students = $pdo->query(
        "SELECT u.id, u.name, u.username, u.direction, u.submitted,
                COALESCE(SUM(b.cost),0) as total_cost,
                GROUP_CONCAT(b.title ORDER BY b.title SEPARATOR '||') as book_titles,
                GROUP_CONCAT(b.id    ORDER BY b.title SEPARATOR ',')  as book_ids
         FROM users u
         LEFT JOIN selections s ON s.student_id=u.id
         LEFT JOIN books b ON b.id=s.book_id
         WHERE u.role='student'
         GROUP BY u.id
         ORDER BY u.direction, u.name"
    )->fetchAll();

    // parse concatenated fields
    foreach ($students as &$s) {
        $s['total_cost']  = round((float)$s['total_cost'], 2);
        $s['submitted']   = (bool)$s['submitted'];
        $s['book_titles'] = $s['book_titles'] ? explode('||', $s['book_titles']) : [];
        $s['book_ids']    = $s['book_ids']    ? array_map('intval', explode(',', $s['book_ids'])) : [];
    }
    json($students);
}

if ($type === 'demand') {
    $rows = $pdo->query(
       "SELECT b.id, b.title, b.author, b.publisher, b.cost, b.direction,
                COUNT(s.student_id) as demand,
                COUNT(s.student_id) * b.cost as subtotal
         FROM books b
         LEFT JOIN selections s ON s.book_id=b.id
         GROUP BY b.id
         ORDER BY demand DESC, b.title"
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['demand']   = (int)$r['demand'];
        $r['cost']     = (float)$r['cost'];
        $r['subtotal'] = round((float)$r['subtotal'], 2);
    }
    json($rows);
}

if ($type === 'csv') {
    // override header για CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_vivlia.csv"');

    $out = fopen('php://output', 'w');
    // BOM για Excel
    fputs($out, "\xEF\xBB\xBF");

    // Section 1: αιτήσεις
    fputcsv($out, ['=== ΑΙΤΗΣΕΙΣ ΣΠΟΥΔΑΣΤΩΝ ===']);
    fputcsv($out, ['Ονοματεπώνυμο','Username','Κατεύθυνση','Βιβλία','Σύνολο €','Κατάσταση']);
    $students = $pdo->query(
        "SELECT u.name, u.username, u.direction, u.submitted,
                COALESCE(SUM(b.cost),0) as total,
                GROUP_CONCAT(b.title ORDER BY b.title SEPARATOR ' | ') as titles
         FROM users u
         LEFT JOIN selections s ON s.student_id=u.id
         LEFT JOIN books b ON b.id=s.book_id
         WHERE u.role='student'
         GROUP BY u.id ORDER BY u.direction, u.name"
    )->fetchAll();
    foreach ($students as $s) {
        fputcsv($out, [
            $s['name'], $s['username'], $s['direction'],
            $s['titles'] ?? '',
            number_format((float)$s['total'], 2, '.', ''),
            $s['submitted'] ? 'Υποβλήθηκε' : 'Εκκρεμεί',
        ]);
    }

    // Section 2: ζήτηση βιβλίων
    fputcsv($out, []);
    fputcsv($out, ['=== ΖΗΤΗΣΗ ΑΝΑ ΒΙΒΛΙΟ ===']);
    fputcsv($out, ['Τίτλος','Συγγραφέας','Εκδοτικός Οίκος','Κατεύθυνση','Κόστος/τεμ','Τεμάχια','Σύνολο €']);
    $demand = $pdo->query(
        "SELECT b.title, b.author, b.publisher, b.direction, b.cost,
                COUNT(s.student_id) as cnt,
                COUNT(s.student_id)*b.cost as sub
         FROM books b
         LEFT JOIN selections s ON s.book_id=b.id
         GROUP BY b.id HAVING cnt > 0
         ORDER BY cnt DESC"
    )->fetchAll();
    foreach ($demand as $r) {
        fputcsv($out, [
            $r['title'], $r['author'], $r['publisher'], $r['direction'],
            number_format((float)$r['cost'], 2, '.', ''),
            $r['cnt'],
            number_format((float)$r['sub'], 2, '.', ''),
        ]);
    }

    // Section 3: σύνολο ανά εκδοτικό οίκο
    fputcsv($out, []);
    fputcsv($out, ['=== ΣΥΝΟΛΟ ΑΝΑ ΕΚΔΟΤΙΚΟ ΟΙΚΟ ===']);
    fputcsv($out, ['Εκδοτικός Οίκος','Τίτλοι','Επιλογές']);
    $pub = $pdo->query(
        "SELECT b.publisher, COUNT(DISTINCT b.id) as titles, COUNT(s.id) as selections
         FROM books b LEFT JOIN selections s ON s.book_id=b.id
         GROUP BY b.publisher ORDER BY b.publisher"
    )->fetchAll();
    foreach ($pub as $p) {
        fputcsv($out, [$p['publisher'] ?: '(Χωρίς)', $p['titles'], $p['selections']]);
    }

    fclose($out);
    exit;
}


if ($type === 'xlsx') {

    $spreadsheet = new Spreadsheet();

    // ───────── SHEET 1: STUDENTS ─────────
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Students');

    $sheet1->fromArray([
        ['Ονοματεπώνυμο','Username','Κατεύθυνση','Βιβλία','Σύνολο €','Κατάσταση']
    ]);

    $students = $pdo->query(
        "SELECT u.name, u.username, u.direction, u.submitted,
                COALESCE(SUM(b.cost),0) as total,
                GROUP_CONCAT(b.title ORDER BY b.title SEPARATOR ' | ') as titles
         FROM users u
         LEFT JOIN selections s ON s.student_id=u.id
         LEFT JOIN books b ON b.id=s.book_id
         WHERE u.role='student'
         GROUP BY u.id ORDER BY u.direction, u.name"
    )->fetchAll();

    $row = 2;
    foreach ($students as $s) {
        $sheet1->fromArray([
            $s['name'],
            $s['username'],
            $s['direction'],
            $s['titles'] ?? '',
            (float)$s['total'],
            $s['submitted'] ? 'Υποβλήθηκε' : 'Εκκρεμεί'
        ], null, "A$row");
        $row++;
    }

    // ───────── SHEET 2: DEMAND ─────────
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Demand');

    $sheet2->fromArray([
        ['Τίτλος','Κατεύθυνση','Κόστος','Τεμάχια','Σύνολο €']
    ]);

    $demand = $pdo->query(
        "SELECT b.title, b.direction, b.cost,
                COUNT(s.student_id) as cnt,
                COUNT(s.student_id)*b.cost as sub
         FROM books b
         LEFT JOIN selections s ON s.book_id=b.id
         GROUP BY b.id HAVING cnt > 0
         ORDER BY cnt DESC"
    )->fetchAll();

    $row = 2;
    foreach ($demand as $d) {
        $sheet2->fromArray([
            $d['title'],
            $d['direction'],
            (float)$d['cost'],
            (int)$d['cnt'],
            (float)$d['sub']
        ], null, "A$row");
        $row++;
    }

    // ───────── DOWNLOAD ─────────
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// -- CSV ΑΝΑ ΣΠΟΥΔΑΣΤΗ -- 
if ($type === 'student_csv') {
    $id = (int)($_GET['student_id'] ?? 0);
    if (!$id) json(['error' => 'Missing student_id'], 400);

    $student = $pdo->prepare("SELECT name, direction FROM users WHERE id=? AND role='student'");
    $student->execute([$id]);
    $s = $student->fetch();
    if (!$s) json(['error' => 'Not found'], 404);

    $books = $pdo->prepare(
        "SELECT b.title, b.author, b.cost
         FROM selections s JOIN books b ON b.id=s.book_id
         WHERE s.student_id=? ORDER BY b.title"
    );
    $books->execute([$id]);
    $rows = $books->fetchAll();
    $total = array_sum(array_column($rows, 'cost'));

    $filename = 'paralabi_' . preg_replace('/[^\p{L}\p{N}]+/u', '_', $s['name']) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Σπουδαστής', $s['name']]);
    fputcsv($out, ['Κατεύθυνση', ['A'=>'Κατεύθυνση ΕΙΣΑΓΓΕΛΕΩΝ','B'=>'Κατεύθυνση ΔΙΟΙΚΗΤΙΚΗΣ ΔΙΚΑΙΟΣΥΝΗΣ','C'=>'Κατεύθυνση ΠΟΛΙΤΙΚΗΣ-ΠΟΙΝΙΚΗΣ'][$s['direction']]]);
    fputcsv($out, []);
    fputcsv($out, ['Τίτλος', 'Συγγραφέας', 'Κόστος €']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['title'], $r['author'], number_format((float)$r['cost'], 2, '.', '')]);
    }
    fputcsv($out, []);
    fputcsv($out, ['', 'ΣΥΝΟΛΟ', number_format($total, 2, '.', '')]);
    fclose($out);
    exit;
}

// --LOGS-- 

if ($type === 'log') {
    header('Content-Type: application/json; charset=utf-8');
    $rows = $pdo->query(
        "SELECT l.id, u.name, u.username, u.direction, l.action, l.details, l.ip, l.created_at
         FROM activity_log l
         JOIN users u ON u.id = l.student_id
         ORDER BY l.created_at DESC
         LIMIT 500"
    )->fetchAll();
    $actionLabel = ['submit'=>' Υποβολή','unsubmit'=>' Τροποποίηση','edit_selection'=>' Αλλαγή επιλογών'];
    foreach ($rows as &$r) {
        $r['action_label'] = $actionLabel[$r['action']] ?? $r['action'];
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

json(['error' => 'Unknown type'], 400);
