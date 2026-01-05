<?php
require_once __DIR__ . '/config/config.php';

function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function is_admin_user() {
    return !empty($_SESSION['admin_is_admin']);
}

function admin_permissions() {
    return isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
        ? $_SESSION['admin_permissions']
        : [];
}

function has_permission($permission) {
    if (is_admin_user()) {
        return true;
    }
    $permissions = admin_permissions();
    return !empty($permissions[$permission]);
}

function require_login() {
    if (!is_admin_logged_in()) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
}

function require_permission($permission) {
    if (!has_permission($permission)) {
        header('Location: ' . BASE_URL . 'admin/index.php?denied=1');
        exit;
    }
}

function require_admin() {
    if (!is_admin_user()) {
        header('Location: ' . BASE_URL . 'admin/index.php?denied=1');
        exit;
    }
}
