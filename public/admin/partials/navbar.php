<?php
require_once __DIR__ . '/../../../src/auth.php';

$loggedIn = is_admin_logged_in();
$isAdmin = is_admin_user();
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
                <?php if ($isAdmin): ?>
                    <a href="mitarbeiter.php" class="mr-4 hover:underline">Mitarbeiter</a>
                <?php endif; ?>
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
