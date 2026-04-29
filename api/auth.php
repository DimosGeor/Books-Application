<?php
// ============================================================
//  auth.php — session helpers
//  Λειτουργεί και σε localhost HTTP και σε production HTTPS
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['REQUEST_URI']) && 
    str_contains($_SERVER['REQUEST_URI'], 'login')) {
    
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_attempt']   = time();
    
    // Reset μετά από 10 λεπτά
    if (isset($_SESSION['first_attempt_time']) && 
        time() - $_SESSION['first_attempt_time'] > 600) {
        $_SESSION['login_attempts'] = 1;
        $_SESSION['first_attempt_time'] = time();
    }
    
    if (!isset($_SESSION['first_attempt_time'])) {
        $_SESSION['first_attempt_time'] = time();
    }
    
    if ($_SESSION['login_attempts'] > 5) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Πολλές αποτυχημένες προσπάθειες. Δοκίμασε σε 10 λεπτά.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    return $user;
}

function json(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}
