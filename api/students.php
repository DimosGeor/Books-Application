<?php
// api/students.php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_GET['action'] ?? '');

// GET — λίστα σπουδαστών
if ($method === 'GET') {
    requireAdmin();
    $rows = db()->query(
        "SELECT id, name, username, role, direction, submitted, created_at
         FROM users WHERE role='student' ORDER BY direction, name"
    )->fetchAll();
    json($rows);
}

// POST — νέος σπουδαστής
if ($method === 'POST' && $path !== 'import') {
    requireAdmin();
    $d         = input();
    $name      = trim($d['name']      ?? '');
    $username  = trim($d['username']  ?? '');
    $password  = $d['password']  ?? '';
    $direction = strtoupper(trim($d['direction'] ?? ''));

    if (!$name || !$username || !$password || !in_array($direction, ['A','B','C'])) {
        json(['error' => 'Συμπλήρωσε όλα τα πεδία.'], 400);
    }
    $chk = db()->prepare('SELECT id FROM users WHERE username=?');
    $chk->execute([$username]);
    if ($chk->fetch()) json(['error' => 'Το username υπάρχει ήδη.'], 409);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, username, password, role, direction) VALUES (?,?,?,\'student\',?)'
    );
    $stmt->execute([$name, $username, $hash, $direction]);
    json(['ok' => true, 'id' => db()->lastInsertId()]);
}

// POST import
if ($method === 'POST' && $path === 'import') {
    requireAdmin();
    $d     = input();
    $lines = $d['lines'] ?? [];
    $ok = 0; $skip = 0;
    $stmt = db()->prepare(
        'INSERT IGNORE INTO users (name, username, password, role, direction) VALUES (?,?,?,\'student\',?)'
    );
    foreach ($lines as $row) {
        $name      = trim($row['name']      ?? '');
        $username  = trim($row['username']  ?? '');
        $password  = $row['password']  ?? '';
        $direction = strtoupper(trim($row['direction'] ?? ''));
        if (!$name || !$username || !$password || !in_array($direction, ['A','B','C'])) { $skip++; continue; }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt->execute([$name, $username, $hash, $direction]);
        $stmt->rowCount() ? $ok++ : $skip++;
    }
    json(['ok' => true, 'imported' => $ok, 'skipped' => $skip]);
}

// PUT — επεξεργασία σπουδαστή
if ($method === 'PUT') {
    requireAdmin();
    $d         = input();
    $id        = (int)($d['id']       ?? 0);
    $name      = trim($d['name']      ?? '');
    $username  = trim($d['username']  ?? '');
    $direction = strtoupper(trim($d['direction'] ?? ''));
    $password  = $d['password'] ?? '';

    if (!$id || !$name || !$username || !in_array($direction, ['A','B','C'])) {
        json(['error' => 'Συμπλήρωσε όλα τα υποχρεωτικά πεδία.'], 400);
    }

    // έλεγχος duplicate username (εκτός από τον εαυτό του)
    $chk = db()->prepare('SELECT id FROM users WHERE username=? AND id != ?');
    $chk->execute([$username, $id]);
    if ($chk->fetch()) json(['error' => 'Το username υπάρχει ήδη.'], 409);

    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = db()->prepare(
            'UPDATE users SET name=?, username=?, direction=?, password=? WHERE id=? AND role=\'student\''
        );
        $stmt->execute([$name, $username, $direction, $hash, $id]);
    } else {
        $stmt = db()->prepare(
            'UPDATE users SET name=?, username=?, direction=? WHERE id=? AND role=\'student\''
        );
        $stmt->execute([$name, $username, $direction, $id]);
    }
    json(['ok' => true]);
}

// DELETE
if ($method === 'DELETE') {
    requireAdmin();
    $d  = input();
    $id = (int)($d['id'] ?? 0);
    if (!$id) json(['error' => 'Missing id'], 400);
    db()->prepare('DELETE FROM users WHERE id=? AND role=\'student\'')->execute([$id]);
    json(['ok' => true]);
}

json(['error' => 'Method not allowed'], 405);
