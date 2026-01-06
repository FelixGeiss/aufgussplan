<?php
require_once __DIR__ . '/../../src/auth.php';

$loggedIn = is_admin_logged_in();
$canMitarbeiter = has_permission('mitarbeiter');
$canAufguesse = has_permission('aufguesse');
$canStatistik = has_permission('statistik');
$canUmfragen = has_permission('umfragen');

$navId = $navId ?? '';
$navClass = $navClass ?? 'bg-blue-600 text-white p-4';
$publicBase = $publicBase ?? '';
$adminBase = $adminBase ?? 'admin/';
$showPublicLinksWhenLoggedOut = $showPublicLinksWhenLoggedOut ?? true;

$navIdAttr = $navId !== ''
    ? ' id="' . htmlspecialchars($navId, ENT_QUOTES, 'UTF-8') . '"'
    : '';
$navClassAttr = htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8');
?>
<nav<?php echo $navIdAttr; ?> class="<?php echo $navClassAttr; ?>">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">Aufgussplan Admin</h1>
        <div>
            <?php if ($loggedIn || $showPublicLinksWhenLoggedOut): ?>
                <a href="<?php echo $publicBase; ?>index.php" class="mr-4 hover:underline">Anzeige</a>
                <a href="<?php echo $publicBase; ?>umfrage.php" class="mr-4 hover:underline">Umfrage anzeigen</a>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <a href="<?php echo $adminBase; ?>index.php" class="mr-4 hover:underline">Dashboard</a>
                <?php if ($canMitarbeiter): ?>
                    <a href="<?php echo $adminBase; ?>mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                <?php endif; ?>
                <div class="relative inline-block mr-4 group pt-2 -mt-2">
                    <a href="<?php echo $adminBase; ?>bildschirme.php" class="hover:underline">Bildschirme</a>
                    <div class="absolute left-0 top-full w-48 rounded-md bg-white text-gray-800 shadow-lg ring-1 ring-black/10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                        <a href="<?php echo $publicBase; ?>bildschirm_1.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 1</a>
                        <a href="<?php echo $publicBase; ?>bildschirm_2.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 2</a>
                        <a href="<?php echo $publicBase; ?>bildschirm_3.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 3</a>
                        <a href="<?php echo $publicBase; ?>bildschirm_4.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 4</a>
                        <a href="<?php echo $publicBase; ?>bildschirm_5.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 5</a>
                    </div>
                </div>
                <?php if ($canAufguesse): ?>
                    <a href="<?php echo $adminBase; ?>aufguesse.php" class="mr-4 hover:underline">Aufguesse</a>
                <?php endif; ?>
                <?php if ($canStatistik): ?>
                    <a href="<?php echo $adminBase; ?>statistik.php" class="mr-4 hover:underline">Statistiken</a>
                <?php endif; ?>
                <?php if ($canUmfragen): ?>
                    <a href="<?php echo $adminBase; ?>umfragen.php" class="mr-4 hover:underline">Umfrage erstellen</a>
                <?php endif; ?>
                <a href="<?php echo $adminBase; ?>logout.php" class="hover:underline">Logout</a>
            <?php else: ?>
                <a href="<?php echo $adminBase; ?>login.php" class="hover:underline">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
