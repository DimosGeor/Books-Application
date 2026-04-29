<?php
// api/login.php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';



header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

// POST /api/login.php  { username, password }
if ($method === 'POST') {
    $d = input();
    $username = trim($d['username'] ?? '');
    $password = $d['password'] ?? '';
     


    if (!$username || !$password) {
        json(['error' => 'Συμπλήρωσε username και password.'], 400);
    }

    $stmt = db()->prepare('SELECT id, name, username, password, role, direction FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json(['error' => 'Λάθος username ή password.'], 401);
    }


    unset($user['password']);
$_SESSION['login_attempts'] = 0;
$_SESSION['first_attempt_time'] = null;

    $_SESSION['user'] = $user;
    json(['ok' => true, 'user' => $user]);
}

// DELETE /api/login.php  (logout)
if ($method === 'DELETE') {
    session_destroy();
    json(['ok' => true]);
}

// GET /api/login.php  (check session)
if ($method === 'GET') {
    $user = currentUser();
    if ($user) json(['ok' => true, 'user' => $user]);
    else json(['ok' => false], 401);
}

json(['error' => 'Method not allowed'], 405);
