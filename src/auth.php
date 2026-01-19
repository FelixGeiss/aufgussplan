<?php
require_once __DIR__ . '/config/config.php';

// Prueft, ob eine Admin-Session aktiv ist.
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Prueft, ob der eingeloggte Benutzer Admin-Rechte hat.
function is_admin_user() {
    return !empty($_SESSION['admin_is_admin']);
}

// Liefert die gespeicherten Berechtigungen aus der Session.
function admin_permissions() {
    return isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
        ? $_SESSION['admin_permissions']
        : [];
}

// Prueft eine spezifische Berechtigung (Admin hat immer Zugriff).
function has_permission($permission) {
    if (is_admin_user()) {
        return true;
    }
    $permissions = admin_permissions();
    return !empty($permissions[$permission]);
}

// Erzwingt Login und leitet sonst zur Login-Seite um.
function require_login() {
    if (!is_admin_logged_in()) {
        header('Location: ' . BASE_URL . 'admin/login/login.php');
        exit;
    }
}

// Erzwingt eine Berechtigung und leitet sonst zur Admin-Startseite um.
function require_permission($permission) {
    if (!has_permission($permission)) {
        header('Location: ' . BASE_URL . 'admin/pages/index.php?denied=1');
        exit;
    }
}

// Erzwingt Admin-Rechte und leitet sonst zur Admin-Startseite um.
function require_admin() {
    if (!is_admin_user()) {
        header('Location: ' . BASE_URL . 'admin/pages/index.php?denied=1');
        exit;
    }
}
