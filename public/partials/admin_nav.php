<?php
require_once __DIR__ . '/../../src/auth.php';

$loggedIn = is_admin_logged_in();
$canMitarbeiter = has_permission('mitarbeiter');
$canAufguesse = has_permission('aufguesse');
$canStatistik = has_permission('statistik');
$canUmfragen = has_permission('umfragen');
$canBildschirme = has_permission('bildschirme');
$canBackup = has_permission('backup');
$isAdminUser = is_admin_user();

$navId = $navId ?? '';
$navClass = $navClass ?? 'bg-blue-600 text-white p-4';
$publicBase = $publicBase ?? BASE_URL;
$adminBase = $adminBase ?? (BASE_URL . 'admin/pages/');
$adminAuthBase = $adminAuthBase ?? (BASE_URL . 'admin/login/');
$showPublicLinksWhenLoggedOut = $showPublicLinksWhenLoggedOut ?? true;

$navIdAttr = $navId !== ''
    ? ' id="' . htmlspecialchars($navId, ENT_QUOTES, 'UTF-8') . '"'
    : '';
$navClassAttr = htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminToasts = [];
$addToast = function ($text, $type = 'success') use (&$adminToasts) {
    $text = trim((string)$text);
    if ($text === '') {
        return;
    }
    $adminToasts[] = [
        'text' => $text,
        'type' => $type
    ];
};

if (isset($_SESSION['toast_message'])) {
    $addToast($_SESSION['toast_message'], $_SESSION['toast_type'] ?? 'success');
    unset($_SESSION['toast_message'], $_SESSION['toast_type']);
}

if (!empty($toastMessage)) {
    $addToast($toastMessage, $toastType ?? 'success');
} elseif (!empty($message)) {
    $addToast($message, 'success');
}

if (!empty($messages) && is_array($messages)) {
    foreach ($messages as $msg) {
        $addToast($msg, 'success');
    }
}

if (!empty($saveMessage)) {
    $addToast($saveMessage, 'success');
}

if (!empty($errors)) {
    if (is_array($errors)) {
        foreach ($errors as $err) {
            $addToast($err, 'error');
        }
    } else {
        $addToast($errors, 'error');
    }
}

if (!empty($saveError)) {
    $addToast($saveError, 'error');
}
?>
<nav<?php echo $navIdAttr; ?> class="<?php echo $navClassAttr; ?>">
    <div class="container mx-auto">
        <div class="flex items-center justify-end">
            <button type="button" class="md:hidden inline-flex items-center gap-2 px-3 py-2 border border-white/30 rounded" data-admin-nav-toggle aria-expanded="false" aria-controls="admin-nav-menu">
                <span class="text-sm font-semibold">Men&uuml;</span>
                <span aria-hidden="true">☰</span>
            </button>
        </div>

        <div class="mx-auto mt-4 w-full max-w-6xl flex-wrap items-center justify-evenly gap-4 hidden md:flex">
            <button type="button" class="mr-4 hover:underline" aria-label="Zur&uuml;ck" onclick="history.back()">
                &larr; Zur&uuml;ck
            </button>
            <?php if ($loggedIn || $showPublicLinksWhenLoggedOut): ?>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <a href="<?php echo $adminBase; ?>index.php" class="mr-4 hover:underline">Dashboard</a>
                <?php if ($canMitarbeiter): ?>
                    <a href="<?php echo $adminBase; ?>mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                <?php endif; ?>
                <?php if ($canBildschirme): ?>
                    <div class="relative inline-block group pt-2 -mt-2 after:absolute after:left-0 after:top-full after:h-2 after:w-full after:content-['']">
                        <a href="<?php echo $adminBase; ?>bildschirme.php" class="hover:underline">Bildschirme</a>
                        <div class="absolute left-0 top-full w-48 rounded-md bg-white text-gray-800 shadow-lg ring-1 ring-black/10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                            <a href="<?php echo $publicBase; ?>bildschirm_1.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bildschirm 1</a>
                            <a href="<?php echo $publicBase; ?>bildschirm_2.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bildschirm 2</a>
                            <a href="<?php echo $publicBase; ?>bildschirm_3.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bildschirm 3</a>
                            <a href="<?php echo $publicBase; ?>bildschirm_4.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bildschirm 4</a>
                            <a href="<?php echo $publicBase; ?>bildschirm_5.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bildschirm 5</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($canAufguesse): ?>
                    <div class="relative inline-block group pt-2 -mt-2 after:absolute after:left-0 after:top-full after:h-2 after:w-full after:content-['']">
                        <a href="<?php echo $adminBase; ?>aufguesse.php" class="hover:underline">Plan</a>
                        <div class="absolute left-0 top-full w-40 rounded-md bg-white text-gray-800 shadow-lg ring-1 ring-black/10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                            <a href="<?php echo $publicBase; ?>index.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Anzeigen</a>
                            <a href="<?php echo $adminBase; ?>aufguesse.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bearbeiten</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($canStatistik): ?>
                    <a href="<?php echo $adminBase; ?>statistik/statistik.php" class="mr-4 hover:underline">Statistiken</a>
                <?php endif; ?>
                <?php if ($canUmfragen): ?>
                    <div class="relative inline-block group pt-2 -mt-2 after:absolute after:left-0 after:top-full after:h-2 after:w-full after:content-['']">
                        <a href="<?php echo $adminBase; ?>umfragen.php" class="hover:underline">Umfrage</a>
                        <div class="absolute left-0 top-full w-40 rounded-md bg-white text-gray-800 shadow-lg ring-1 ring-black/10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                            <a href="<?php echo $publicBase; ?>umfrage.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Anzeigen</a>
                            <a href="<?php echo $adminBase; ?>umfragen.php" class="block px-4 py-2 hover:bg-gray-100" target="_blank" rel="noopener">Bearbeiten</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($canBackup): ?>
                    <a href="<?php echo $adminBase; ?>backup.php" class="mr-4 hover:underline">Backup</a>
                <?php endif; ?>
                <a href="<?php echo $adminAuthBase; ?>logout.php" class="hover:underline">Logout</a>
            <?php else: ?>
                <a href="<?php echo $adminAuthBase; ?>login.php" class="hover:underline">Login</a>
            <?php endif; ?>
        </div>

        <div id="admin-nav-menu" class="mt-4 hidden md:hidden">
            <?php if ($loggedIn): ?>
                <div class="grid gap-6 sm:grid-cols-2 text-center max-[500px]:grid-cols-1">
                    <div class="flex flex-col gap-3">
                        <?php if ($canBildschirme): ?>
                            <details class="group">
                                <summary class="cursor-pointer hover:underline list-none flex items-center justify-center gap-2">
                                    <span>Bildschirme</span>
                                    <span class="text-xs opacity-75 transition-transform duration-150 group-open:rotate-180">▾</span>
                                </summary>
                                <div class="mt-2 text-sm text-white/90">
                                    <a href="<?php echo $publicBase; ?>bildschirm_1.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bildschirm 1</a>
                                    <a href="<?php echo $publicBase; ?>bildschirm_2.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bildschirm 2</a>
                                    <a href="<?php echo $publicBase; ?>bildschirm_3.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bildschirm 3</a>
                                    <a href="<?php echo $publicBase; ?>bildschirm_4.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bildschirm 4</a>
                                    <a href="<?php echo $publicBase; ?>bildschirm_5.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bildschirm 5</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($canAufguesse): ?>
                            <details class="group">
                                <summary class="cursor-pointer hover:underline list-none flex items-center justify-center gap-2">
                                    <span>Plan</span>
                                    <span class="text-xs opacity-75 transition-transform duration-150 group-open:rotate-180">▾</span>
                                </summary>
                                <div class="mt-2 text-sm text-white/90">
                                    <a href="<?php echo $publicBase; ?>index.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Anzeigen</a>
                                    <a href="<?php echo $adminBase; ?>aufguesse.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bearbeiten</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($canUmfragen): ?>
                            <details class="group">
                                <summary class="cursor-pointer hover:underline list-none flex items-center justify-center gap-2">
                                    <span>Umfrage</span>
                                    <span class="text-xs opacity-75 transition-transform duration-150 group-open:rotate-180">▾</span>
                                </summary>
                                <div class="mt-2 text-sm text-white/90">
                                    <a href="<?php echo $publicBase; ?>umfrage.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Anzeigen</a>
                                    <a href="<?php echo $adminBase; ?>umfragen.php" class="block py-1 hover:underline" target="_blank" rel="noopener">Bearbeiten</a>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="<?php echo $adminBase; ?>index.php" class="hover:underline">Dashboard</a>
                        <?php if ($canMitarbeiter): ?>
                            <a href="<?php echo $adminBase; ?>mitarbeiter.php" class="hover:underline">Mitarbeiter</a>
                        <?php endif; ?>
                        <?php if ($canStatistik): ?>
                            <a href="<?php echo $adminBase; ?>statistik/statistik.php" class="hover:underline">Statistiken</a>
                        <?php endif; ?>
                        <?php if ($canBackup): ?>
                            <a href="<?php echo $adminBase; ?>backup.php" class="hover:underline">Backup</a>
                        <?php endif; ?>
                        <a href="<?php echo $adminAuthBase; ?>logout.php" class="hover:underline">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo $adminAuthBase; ?>login.php" class="hover:underline">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
    (function() {
        const toggle = document.querySelector('[data-admin-nav-toggle]');
        const menu = document.getElementById('admin-nav-menu');
        if (!toggle || !menu) return;
        toggle.addEventListener('click', () => {
            const isOpen = !menu.classList.contains('hidden');
            menu.classList.toggle('hidden', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });
    })();
</script>
<div id="toast-stack" class="toast-stack">
    <?php foreach ($adminToasts as $toast): ?>
        <div class="toast toast-<?php echo htmlspecialchars($toast['type'], ENT_QUOTES, 'UTF-8'); ?>" data-toast>
            <div><?php echo htmlspecialchars($toast['text'], ENT_QUOTES, 'UTF-8'); ?></div>
            <button type="button" class="font-bold leading-none" aria-label="Meldung schliessen" data-toast-close>
                &times;
            </button>
        </div>
    <?php endforeach; ?>
</div>
