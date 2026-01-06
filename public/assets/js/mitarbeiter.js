const apiUrl = '../api/mitarbeiter.php';

// HTML in Text sicher escapen.
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Zeigt Toast oder Fallback-Alert.
function notify(message, type = 'info') {
    if (window.AdminUtils && typeof window.AdminUtils.showToast === 'function') {
        window.AdminUtils.showToast(message, type);
        return;
    }
    alert(message);
}

// Oeffnet das Modal zum Hinzufuegen.
function openAddModal() {
    const modal = document.getElementById('addModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// Schliesst das Hinzufuegen-Modal.
function closeAddModal() {
    const modal = document.getElementById('addModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Baut eine Tabellenzeile fuer einen Mitarbeiter.
function buildRow(mitarbeiter) {
    const id = Number(mitarbeiter.id || 0);
    const name = escapeHtml(mitarbeiter.name || '');
    const position = escapeHtml(mitarbeiter.position || '');
    const username = escapeHtml(mitarbeiter.username || '');
    const canAufguesse = Number(mitarbeiter.can_aufguesse) === 1;
    const canStatistik = Number(mitarbeiter.can_statistik) === 1;
    const canUmfragen = Number(mitarbeiter.can_umfragen) === 1;
    const canMitarbeiter = Number(mitarbeiter.can_mitarbeiter) === 1;
    const isAdmin = Number(mitarbeiter.is_admin) === 1;
    const aktiv = Number(mitarbeiter.aktiv) === 1;

    const checkbox = (name, label, checked) => `
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="${name}" class="peer sr-only" ${checked ? 'checked' : ''}>
            <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <span>${label}</span>
        </label>
    `;

    return `
        <tr data-id="${id}" class="border-b">
            <td class="px-4 py-2">
                <input type="text" name="name" class="w-full border rounded px-2 py-1" value="${name}">
            </td>
            <td class="px-4 py-2">
                <input type="text" name="position" class="w-full border rounded px-2 py-1" value="${position}">
            </td>
            <td class="px-4 py-2">
                <input type="text" name="username" class="w-full border rounded px-2 py-1" value="${username}" autocomplete="off">
            </td>
            <td class="px-4 py-2">
                <div class="flex flex-col gap-1 text-sm">
                    ${checkbox('can_aufguesse', 'Aufguesse', canAufguesse)}
                    ${checkbox('can_statistik', 'Statistik', canStatistik)}
                    ${checkbox('can_umfragen', 'Umfrage', canUmfragen)}
                    ${checkbox('can_mitarbeiter', 'Mitarbeiter', canMitarbeiter)}
                    ${checkbox('is_admin', 'Admin', isAdmin)}
                </div>
            </td>
            <td class="px-4 py-2">
                <div class="text-sm">
                    ${checkbox('aktiv', 'Aktiv', aktiv)}
                </div>
            </td>
            <td class="px-4 py-2">
                <input type="password" name="password" class="w-full border rounded px-2 py-1" placeholder="neu setzen" autocomplete="new-password">
            </td>
            <td class="px-4 py-2 text-center">
                <div class="flex justify-center gap-2">
                    <button type="button" data-action="save" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">Speichern</button>
                    <button type="button" data-action="delete" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Löschen</button>
                </div>
            </td>
        </tr>
    `;
}

// Laedt die Mitarbeiterliste und rendert die Tabelle.
async function loadMitarbeiter() {
    const table = document.getElementById('mitarbeiterTable');
    if (!table) return;
    table.innerHTML = '<tr><td class="px-4 py-6 text-sm text-gray-500" colspan="7">Lade...</td></tr>';

    try {
        const response = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
        const payload = await response.json();
        const rows = payload && payload.data && Array.isArray(payload.data.mitarbeiter)
            ? payload.data.mitarbeiter
            : [];

        if (!payload || payload.success === false) {
            table.innerHTML = '<tr><td class="px-4 py-6 text-sm text-red-500" colspan="7">Fehler beim Laden.</td></tr>';
            return;
        }

        if (rows.length === 0) {
            table.innerHTML = '<tr><td class="px-4 py-6 text-sm text-gray-500" colspan="7">Keine Mitarbeiter gefunden.</td></tr>';
            return;
        }

        table.innerHTML = rows.map(buildRow).join('');
    } catch (error) {
        table.innerHTML = '<tr><td class="px-4 py-6 text-sm text-red-500" colspan="7">Netzwerkfehler.</td></tr>';
    }
}

// Liest die Werte aus einer Tabellenzeile.
function getRowData(row) {
    const getValue = (name) => row.querySelector(`[name="${name}"]`);
    const nameInput = getValue('name');
    const positionInput = getValue('position');
    const usernameInput = getValue('username');
    const passwordInput = getValue('password');

    return {
        id: Number(row.getAttribute('data-id')),
        name: nameInput ? nameInput.value.trim() : '',
        position: positionInput ? positionInput.value.trim() : '',
        username: usernameInput ? usernameInput.value.trim() : '',
        password: passwordInput ? passwordInput.value : '',
        can_aufguesse: getValue('can_aufguesse')?.checked || false,
        can_statistik: getValue('can_statistik')?.checked || false,
        can_umfragen: getValue('can_umfragen')?.checked || false,
        can_mitarbeiter: getValue('can_mitarbeiter')?.checked || false,
        is_admin: getValue('is_admin')?.checked || false,
        aktiv: getValue('aktiv')?.checked || false
    };
}

// Speichert eine Zeile per API.
async function saveRow(row) {
    const data = getRowData(row);
    if (!data.id) return;

    const payload = {
        id: data.id,
        name: data.name,
        position: data.position,
        username: data.username,
        can_aufguesse: data.can_aufguesse,
        can_statistik: data.can_statistik,
        can_umfragen: data.can_umfragen,
        can_mitarbeiter: data.can_mitarbeiter,
        is_admin: data.is_admin,
        aktiv: data.aktiv
    };

    if (data.password) {
        payload.password = data.password;
    }

    try {
        const response = await fetch(apiUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result && result.success) {
            notify('Mitarbeiter gespeichert.', 'success');
            await loadMitarbeiter();
        } else {
            notify(result && result.message ? result.message : 'Speichern fehlgeschlagen.', 'error');
        }
    } catch (error) {
        notify('Netzwerkfehler beim Speichern.', 'error');
    }
}

// Loescht einen Mitarbeiter per API.
async function deleteRow(row) {
    const id = row.getAttribute('data-id');
    if (!id) return;

    if (!confirm('Mitarbeiter wirklich Löschen?')) return;

    try {
        const response = await fetch(`${apiUrl}?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        const result = await response.json();
        if (result && result.success) {
            notify('Mitarbeiter geloescht.', 'success');
            await loadMitarbeiter();
        } else {
            notify(result && result.message ? result.message : 'Löschen fehlgeschlagen.', 'error');
        }
    } catch (error) {
        notify('Netzwerkfehler beim Löschen.', 'error');
    }
}

// Bindet Tabellen-Events fuer Speichern/Löschen.
function setupTableHandlers() {
    const table = document.getElementById('mitarbeiterTable');
    if (!table) return;

    table.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const row = button.closest('tr');
        if (!row) return;

        const action = button.getAttribute('data-action');
        if (action === 'save') {
            saveRow(row);
        }
        if (action === 'delete') {
            deleteRow(row);
        }
    });
}

// Bindet das Formular fuer neue Mitarbeiter.
function setupAddForm() {
    const form = document.getElementById('addMitarbeiterForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = {
            name: String(formData.get('name') || '').trim(),
            position: String(formData.get('position') || '').trim(),
            username: String(formData.get('username') || '').trim(),
            password: String(formData.get('password') || ''),
            can_aufguesse: formData.get('can_aufguesse') === 'on',
            can_statistik: formData.get('can_statistik') === 'on',
            can_umfragen: formData.get('can_umfragen') === 'on',
            can_mitarbeiter: formData.get('can_mitarbeiter') === 'on',
            is_admin: formData.get('is_admin') === 'on',
            aktiv: formData.get('aktiv') === 'on'
        };

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result && result.success) {
                notify('Mitarbeiter hinzugefuegt.', 'success');
                form.reset();
                form.querySelector('input[name="aktiv"]').checked = true;
                closeAddModal();
                await loadMitarbeiter();
            } else {
                notify(result && result.message ? result.message : 'Hinzufuegen fehlgeschlagen.', 'error');
            }
        } catch (error) {
            notify('Netzwerkfehler beim Hinzufuegen.', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadMitarbeiter();
    setupTableHandlers();
    setupAddForm();
});
