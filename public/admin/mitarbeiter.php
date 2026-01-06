<?php
/**
 * MITARBEITER-VERWALTUNG
 *
 * Diese Seite ermoeglicht es Administratoren, Mitarbeiter zu verwalten:
 * - Neue Mitarbeiter hinzufuegen
 * - Bestehende Mitarbeiter anzeigen
 * - Mitarbeiter bearbeiten/Löschen (zukuenftig)
 *
 * URL: http://localhost/aufgussplan/admin/mitarbeiter.php
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db/connection.php';

require_login();
require_permission('mitarbeiter');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitarbeiter verwalten - Aufgussplan</title>
    <link rel="stylesheet" href="../dist/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Mitarbeiter verwalten</h2>
            <button onclick="openAddModal()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                + Neuen Mitarbeiter hinzufuegen
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Position</th>
                            <th class="px-4 py-2 text-left">Benutzername</th>
                            <th class="px-4 py-2 text-left">Rechte</th>
                            <th class="px-4 py-2 text-left">Aktiv</th>
                            <th class="px-4 py-2 text-left">Neues Passwort</th>
                            <th class="px-4 py-2 text-center">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="mitarbeiterTable">
                        <!-- Hier werden die Mitarbeiter dynamisch geladen -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Neuen Mitarbeiter hinzufuegen</h3>
            <form id="addMitarbeiterForm">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Position</label>
                    <input type="text" name="position" class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Benutzername</label>
                    <input type="text" name="username" class="w-full px-3 py-2 border rounded" autocomplete="off">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Passwort</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border rounded" autocomplete="new-password">
                </div>
                <div class="mb-4">
                    <div class="text-sm font-semibold text-gray-700 mb-2">Rechte</div>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="can_aufguesse" class="peer sr-only">
                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Aufguesse
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="can_statistik" class="peer sr-only">
                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Statistik
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="can_umfragen" class="peer sr-only">
                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Umfrage
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="can_mitarbeiter" class="peer sr-only">
                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Mitarbeiter
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_admin" class="peer sr-only">
                            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            Admin
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="aktiv" class="peer sr-only" checked>
                        <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                            <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                        Login aktiv
                    </label>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="closeAddModal()" class="mr-2 px-4 py-2 text-gray-600 hover:text-gray-800">Abbrechen</button>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Hinzufuegen</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/mitarbeiter.js"></script>
</body>
</html>
