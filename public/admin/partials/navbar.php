<?php
require_once __DIR__ . '/../../../src/auth.php';

$loggedIn = is_admin_logged_in();
$canMitarbeiter = has_permission('mitarbeiter');
$canAufguesse = has_permission('aufguesse');
$canStatistik = has_permission('statistik');
$canUmfragen = has_permission('umfragen');
?>
<nav class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">Aufgussplan Admin</h1>
        <div>
            <a href="../index.php" class="mr-4 hover:underline">Anzeige</a>
            <a href="../umfrage.php" class="mr-4 hover:underline">Umfrage anzeigen</a>
            <?php if ($loggedIn): ?>
                <a href="index.php" class="mr-4 hover:underline">Dashboard</a>
                <?php if ($canMitarbeiter): ?>
                    <a href="mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                <?php endif; ?>
                <div class="relative inline-block mr-4 group pt-2 -mt-2">
                    <a href="bildschirme.php" class="hover:underline">Bildschirme</a>
                    <div class="absolute left-0 top-full w-48 rounded-md bg-white text-gray-800 shadow-lg ring-1 ring-black/10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-150">
                        <a href="../bildschirm_1.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 1</a>
                        <a href="../bildschirm_2.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 2</a>
                        <a href="../bildschirm_3.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 3</a>
                        <a href="../bildschirm_4.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 4</a>
                        <a href="../bildschirm_5.php" class="block px-4 py-2 hover:bg-gray-100">Bildschirm 5</a>
                    </div>
                </div>
                <?php if ($canAufguesse): ?>
                    <a href="aufguesse.php" class="mr-4 hover:underline">Aufguesse</a>
                <?php endif; ?>
                <?php if ($canStatistik): ?>
                    <a href="statistik.php" class="mr-4 hover:underline">Statistiken</a>
                <?php endif; ?>
                <?php if ($canUmfragen): ?>
                    <a href="umfragen.php" class="mr-4 hover:underline">Umfrage erstellen</a>
                <?php endif; ?>
                <a href="logout.php" class="hover:underline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="hover:underline">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
