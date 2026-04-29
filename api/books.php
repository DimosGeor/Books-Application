<?php
// api/books.php
// GET    — public (χρησιμοποιείται και από students)
// POST   — admin only
// DELETE — admin only
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';


header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_GET['action'] ?? '');

// ── GET — λίστα βιβλίων (φιλτράρει ανά direction αν δοθεί) ──
if ($method === 'GET') {
    requireLogin();
    $dir = strtoupper($_GET['direction'] ?? '');
    if ($dir && in_array($dir, ['A','B','C'])) {
        $stmt = db()->prepare('SELECT id, title, author, publisher, cost, direction FROM books WHERE direction=? ORDER BY title');
        $stmt->execute([$dir]);
    } else {
       $stmt = db()->query('SELECT id, title, author, publisher, cost, direction FROM books ORDER BY direction, title');
    }
    json($stmt->fetchAll());
}

// ── POST — νέο βιβλίο ────────────────────────────────────────
if ($method === 'POST' && $path !== 'import') {
    requireAdmin();
    $d         = input();
    $title     = trim($d['title']     ?? '');
    $author    = trim($d['author']    ?? '');
    $cost      = (float)($d['cost']   ?? -1);
    $direction = strtoupper(trim($d['direction'] ?? ''));

    if (!$title || !$author || $cost < 0 || !in_array($direction, ['A','B','C'])) {
        json(['error' => 'Συμπλήρωσε όλα τα πεδία σωστά.'], 400);
    }
    $publisher = trim($d['publisher'] ?? '');
   $stmt = db()->prepare('INSERT INTO books (title, author, publisher, cost, direction) VALUES (?,?,?,?,?)');
   $stmt->execute([$title, $author, $publisher, $cost, $direction]);

    json(['ok' => true, 'id' => db()->lastInsertId()]);
}

// ── POST import — bulk ────────────────────────────────────────
if ($method === 'POST' && $path === 'import') {
    requireAdmin();
    $d     = input();
    $lines = $d['lines'] ?? [];
    $ok = 0; $skip = 0;
    $stmt = db()->prepare('INSERT INTO books (title, author, publisher, cost, direction) VALUES (?,?,?,?,?)');
    foreach ($lines as $row) {
        $title     = trim($row['title']     ?? '');
        $author    = trim($row['author']    ?? '');
        $cost      = (float)($row['cost']   ?? -1);
        $direction = strtoupper(trim($row['direction'] ?? ''));
        if (!$title || !$author || $cost < 0 || !in_array($direction, ['A','B','C'])) { $skip++; continue; }
        $stmt->execute([$title, $author, $publisher, $cost, $direction]);
        $ok++;
    }
    json(['ok' => true, 'imported' => $ok, 'skipped' => $skip]);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $d  = input();
    $id = (int)($d['id'] ?? 0);
    if (!$id) json(['error' => 'Missing id'], 400);
    db()->prepare('DELETE FROM books WHERE id=?')->execute([$id]);
    json(['ok' => true]);
}

json(['error' => 'Method not allowed'], 405);
