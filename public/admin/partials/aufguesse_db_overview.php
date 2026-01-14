<div class="bg-white rounded-lg shadow-md mt-8">
            <div class="p-6">
                <h2 class="text-3xl font-bold text-gray-900 mb-6">Datenbank-Übersicht</h2>

                <!-- Tabs für verschiedene Datenarten -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="showTab('aufguesse')" id="tab-aufguesse" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">
                                Aufgüsse (<?php echo count($aufguss_optionen); ?>)
                            </button>
                            <button onclick="showTab('saunen')" id="tab-saunen" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Saunen (<?php echo count($saunen); ?>)
                            </button>
                            <button onclick="showTab('duftmittel')" id="tab-duftmittel" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Duftmittel (<?php echo count($duftmittel); ?>)
                            </button>
                            <button onclick="showTab('mitarbeiter')" id="tab-mitarbeiter" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Mitarbeiter (<?php echo count($mitarbeiter); ?>)
                            </button>
                            <button onclick="showTab('werbung')" id="tab-werbung" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Werbung (<?php echo count($werbungTabFiles); ?>)
                            </button>
                            <button onclick="showTab('hintergrund')" id="tab-hintergrund" class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Hintergrund (<?php echo count($hintergrundTabFiles); ?>)
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Aufgüsse Tab -->
                <div id="content-aufguesse" class="tab-content">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neuen Aufgussnamen anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_aufguss_name">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Aufgussname</label>
                                <input type="text" name="aufguss_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Citrus-Explosion" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="aufguss_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-1">
                                    Aufguss speichern <span aria-hidden="true">+</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($aufguss_optionen)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Aufgüsse in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($aufguss_optionen as $aufguss): ?>
                                        <tr class="bg-white/5" data-aufguss-name-id="<?php echo $aufguss['id']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($aufguss['id']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap aufguss-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($aufguss['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="aufguss_name" value="<?php echo htmlspecialchars($aufguss['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap aufguss-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($aufguss['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="aufguss_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($aufguss['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelAufgussNameEdit(<?php echo $aufguss['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('aufguss', <?php echo $aufguss['id']; ?>, '<?php echo htmlspecialchars($aufguss['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Aufguss löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Saunen Tab -->
                <div id="content-saunen" class="tab-content hidden">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neue Sauna anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_sauna">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input type="text" name="sauna_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Finnische Sauna" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="sauna_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Temperatur (°C)</label>
                                <input type="number" name="sauna_temperatur" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. 90" min="0" step="1">
                            </div>
                            <div class="md:col-span-3 flex justify-end">
                                <button type="submit" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-1">
                                    Sauna speichern <span aria-hidden="true">+</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bild
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Temperatur
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($saunen)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Saunen in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($saunen as $sauna): ?>
                                        <tr class="bg-white/5" data-sauna-id="<?php echo $sauna['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($sauna['id']); ?>
                                            </td>

                                            <!-- Bild -->
                                                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="relative inline-flex flex-col items-center group cursor-pointer image-edit-hover"
                                                    onclick="openImageModal('sauna', <?php echo $sauna['id']; ?>, '<?php echo htmlspecialchars($sauna['name']); ?>')">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <?php if (!empty($sauna['bild'])): ?>
                                                            <img src="../../uploads/<?php echo htmlspecialchars($sauna['bild']); ?>"
                                                                alt="Sauna-Bild"
                                                                class="h-8 w-8 rounded-full object-cover border border-gray-200 hover:border-indigo-400 transition-colors">
                                                        <?php else: ?>
                                                            <div class="h-8 w-8 bg-green-100 rounded-full flex items-center justify-center hover:bg-green-200 transition-colors">
    <span class="text-green-600 text-sm">&#127968;</span>
</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <svg class="mt-1 w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($sauna['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="sauna_name" value="<?php echo htmlspecialchars($sauna['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'name')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Beschreibung (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($sauna['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="sauna_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($sauna['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Temperatur -->
                                            <td class="px-6 py-4 whitespace-nowrap sauna-temp-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')">
                                                    <span>
                                                        <?php if ($sauna['temperatur'] !== null && $sauna['temperatur'] !== ''): ?>
                                                            <?php echo (int)$sauna['temperatur']; ?>&deg;C
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="number" name="sauna_temperatur" value="<?php echo htmlspecialchars($sauna['temperatur'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300" min="0" step="1" placeholder="z.B. 90">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button type="button" onclick="clearSaunaTemperatur(<?php echo $sauna['id']; ?>)" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm hover:bg-gray-300">Leeren</button>
                                                        <button onclick="cancelSaunaEdit(<?php echo $sauna['id']; ?>, 'temperatur')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('sauna', <?php echo $sauna['id']; ?>, '<?php echo htmlspecialchars($sauna['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Sauna löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Duftmittel Tab -->
                <div id="content-duftmittel" class="tab-content hidden">
                    <div class="bg-white/70 border border-gray-200 rounded-lg p-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Neues Duftmittel anlegen</h3>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <input type="hidden" name="form_type" value="create_duftmittel">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input type="text" name="duftmittel_name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="z.B. Eukalyptus" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                                <input type="text" name="duftmittel_beschreibung" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900" placeholder="optional">
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="admin-btn-save text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-1">
                                    Duftmittel speichern <span aria-hidden="true">+</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Beschreibung
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($duftmittel)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Duftmittel in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($duftmittel as $dm): ?>
                                        <tr class="bg-white/5" data-duftmittel-id="<?php echo $dm['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($dm['id']); ?>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($dm['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="duftmittel_name" value="<?php echo htmlspecialchars($dm['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelDuftmittelEdit(<?php echo $dm['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Beschreibung (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap duftmittel-desc-cell">
                                                <div class="display-mode text-lg text-gray-600 cursor-pointer hover:bg-purple-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')">
                                                    <span><?php echo htmlspecialchars($dm['beschreibung'] ?? 'Keine Beschreibung'); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <textarea name="duftmittel_beschreibung" rows="2"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300"><?php echo htmlspecialchars($dm['beschreibung'] ?? ''); ?></textarea>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelDuftmittelEdit(<?php echo $dm['id']; ?>, 'beschreibung')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('duftmittel', <?php echo $dm['id']; ?>, '<?php echo htmlspecialchars($dm['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Duftmittel löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mitarbeiter Tab -->
                <div id="content-mitarbeiter" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bild
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($mitarbeiter)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Keine Mitarbeiter in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mitarbeiter as $mitarbeiter_item): ?>
                                        <tr class="bg-white/5" data-mitarbeiter-id="<?php echo $mitarbeiter_item['id']; ?>">
                                            <!-- ID -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($mitarbeiter_item['id']); ?>
                                            </td>

                                            <!-- Bild -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="relative inline-flex flex-col items-center group cursor-pointer image-edit-hover"
                                                    onclick="openImageModal('mitarbeiter', <?php echo $mitarbeiter_item['id']; ?>, '<?php echo htmlspecialchars($mitarbeiter_item['name']); ?>')">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <?php if (!empty($mitarbeiter_item['bild'])): ?>
                                                            <img src="../../uploads/<?php echo htmlspecialchars($mitarbeiter_item['bild']); ?>"
                                                                alt="Mitarbeiter-Bild"
                                                                class="h-8 w-8 rounded-full object-cover border border-gray-200 hover:border-indigo-400 transition-colors">
                                                        <?php else: ?>
                                                            <div class="h-8 w-8 bg-gray-300 rounded-full flex items-center justify-center hover:bg-gray-400 transition-colors">
                                                                <span class="text-gray-700 font-semibold text-sm">
                                                                    <?php echo strtoupper(substr($mitarbeiter_item['name'], 0, 1)); ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <svg class="mt-1 w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                            </td>

                                            <!-- Name (editierbar) -->
                                            <td class="px-6 py-4 whitespace-nowrap mitarbeiter-name-cell">
                                                <div class="display-mode text-sm font-medium text-gray-900 cursor-pointer hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 rounded px-2 py-1 group" onclick="toggleMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')">
                                                    <span><?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?></span>
                                                    <svg class="inline-block w-3 h-3 ml-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </div>
                                                <div class="edit-mode hidden flex flex-col gap-2">
                                                    <input type="text" name="mitarbeiter_name" value="<?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?>"
                                                        class="rounded px-2 py-1 text-sm border border-gray-300">
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <button onclick="saveMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')" class="admin-btn-save text-white px-3 py-1 rounded text-sm">✓ Speichern</button>
                                                        <button onclick="cancelMitarbeiterEdit(<?php echo $mitarbeiter_item['id']; ?>, 'name')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">✕ Abbrechen</button>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="deleteDatenbankEintrag('mitarbeiter', <?php echo $mitarbeiter_item['id']; ?>, '<?php echo htmlspecialchars($mitarbeiter_item['name'] ?? ''); ?>')"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Mitarbeiter löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Werbung Tab -->
                <div id="content-werbung" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Vorschau
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Datei
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Typ
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bereich
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($werbungTabFiles)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Werbung in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($werbungTabFiles as $file): ?>
                                        <tr class="bg-white/5">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $isVideo = stripos($file['typ'], 'video') !== false;
                                                $fileRelPath = $file['datei'] ?? '';
                                                $filePath = '../../uploads/' . $fileRelPath;
                                                ?>
                                                <?php if ($isVideo): ?>
                                                    <video src="<?php echo htmlspecialchars($filePath); ?>" class="h-12 w-20 object-cover rounded border border-gray-200" muted></video>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($filePath); ?>" alt="Datei" class="h-12 w-12 object-cover rounded border border-gray-200">
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(basename($file['datei'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['typ']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['bereich']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button type="button"
                                                    onclick="deleteUploadFile('werbung', <?php echo htmlspecialchars(json_encode($fileRelPath), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Datei Löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Hintergrund Tab -->
                <div id="content-hintergrund" class="tab-content hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-transparent border border-gray-200 rounded-lg db-overview-table">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Vorschau
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Datei
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Typ
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Bereich
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-black-500 uppercase tracking-wider border-b">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent divide-y divide-gray-200">
                                <?php if (empty($hintergrundTabFiles)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            Keine Hintergrundbilder in der Datenbank gefunden.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($hintergrundTabFiles as $file): ?>
                                        <tr class="bg-white/5">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $isVideo = stripos($file['typ'], 'video') !== false;
                                                $fileRelPath = $file['datei'] ?? '';
                                                $filePath = '../../uploads/' . $fileRelPath;
                                                ?>
                                                <?php if ($isVideo): ?>
                                                    <video src="<?php echo htmlspecialchars($filePath); ?>" class="h-12 w-20 object-cover rounded border border-gray-200" muted></video>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($filePath); ?>" alt="Datei" class="h-12 w-12 object-cover rounded border border-gray-200">
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(basename($file['datei'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['typ']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['bereich']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($file['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button type="button"
                                                    onclick="deleteUploadFile('plan', <?php echo htmlspecialchars(json_encode($fileRelPath), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition-colors duration-150"
                                                    title="Datei Löschen">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
