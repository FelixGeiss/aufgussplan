/**
 * GEMEINSAME ADMIN-FUNKTIONEN
 *
 * Dieses Script enthält alle wiederverwendbaren Funktionen für den Admin-Bereich:
 * - AJAX-Requests mit Sicherheit
 * - Modal-Fenster Management
 * - Drag & Drop für Datei-Uploads
 * - Toast-Benachrichtigungen
 * - Formular-Validierung
 * - Datum/Zeit-Helfer
 *
 * Als Anfänger solltest du wissen:
 * - Dies ist eine "Utility"-Bibliothek für Admin-Funktionen
 * - window.AdminUtils macht Funktionen global verfügbar
 * - AJAX = Asynchronous JavaScript And XML (ohne Seitenreload)
 * - CSRF = Cross-Site Request Forgery (Sicherheit gegen Angriffe)
 *
 * Architektur: Mehrere Admin-Seiten verwenden diese gemeinsamen Funktionen
 */

// Warten bis DOM bereit ist
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Interface geladen');

    /**
     * CSRF-SCHUTZ FÜR AJAX-REQUESTS
     *
     * Cross-Site Request Forgery Schutz:
     * - Token im HTML-Head suchen
     * - Bei jedem AJAX-Request mitsenden
     * - Server prüft Token für Sicherheit
     */
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        // jQuery AJAX-Setup (falls jQuery verwendet wird)
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken.getAttribute('content')
            }
        });
    }

    /**
     * LOGOUT-FUNKTIONALITÄT
     *
     * Sicherer Logout mit Bestätigung:
     * - Klick auf Logout-Link abfangen
     * - Bestätigungsdialog anzeigen
     * - Bei "Ja": Zu login/logout.php weiterleiten
     */
    const logoutBtn = document.querySelector('a[href*="logout"]');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            // Standard-Link-Verhalten verhindern
            e.preventDefault();

            // Bestätigung vom Benutzer einholen
            if (confirm('Wirklich abmelden?')) {
                // Zu Logout-Seite weiterleiten (beendet Session)
                const path = window.location.pathname || '';
                const parts = path.split('/admin/');
                const base = parts.length > 1 ? parts[0] : '';
                window.location.href = `${base}/admin/login/logout.php`;
            }
        });
    }

    const existingToasts = document.querySelectorAll('#toast-stack [data-toast]');
    if (existingToasts.length) {
        existingToasts.forEach(toast => {
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            const closeButton = toast.querySelector('[data-toast-close]');
            const removeToast = () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 220);
            };

            if (closeButton) {
                closeButton.addEventListener('click', removeToast);
            }

            setTimeout(removeToast, 4500);
        });
    }
});

/**
 * ============================================================================
 * TOAST-BENACHRICHTIGUNGEN
 * ============================================================================
 *
 * Kleine Pop-up-Nachrichten für Erfolg/Fehler.
 * Erscheinen oben rechts und verschwinden automatisch.
 */

/**
 * Toast-Nachricht anzeigen
 *
 * @param {string} message - Nachrichtentext
 * @param {string} type - Typ: 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    const stack = document.getElementById('toast-stack');
    if (stack) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('data-toast', '');
        toast.innerHTML = `
            <div>${message}</div>
            <button type="button" class="font-bold leading-none" aria-label="Meldung schliessen" data-toast-close>
                &times;
            </button>
        `;
        stack.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const removeToast = () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 220);
        };
        const closeButton = toast.querySelector('[data-toast-close]');
        if (closeButton) {
            closeButton.addEventListener('click', removeToast);
        }
        setTimeout(removeToast, 4500);
        return;
    }

    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
        type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

/**
 * ============================================================================
 * EXPORT FÜR ANDERE MODULE
 * ============================================================================
 *
 * Macht alle Hilfsfunktionen global verfügbar.
 * Andere JavaScript-Dateien können diese Funktionen verwenden:
 *
 * Beispiel in mitarbeiter.js:
 * AdminUtils.showToast('Erfolgreich gespeichert!', 'success');
 */

// Alle wichtigen Funktionen in window.AdminUtils exportieren
window.AdminUtils = {
    showToast,           // Toast-Nachrichten
};

window.showToast = showToast;

/**
 * Admin-Funktionen für Aufgussplan
 *
 * Diese Datei enthält alle JavaScript-Funktionen für das Admin-Interface
 */

// Toggle-Funktion für ausklappbare Formulare
function toggleForm(formId) {
    const form = document.getElementById('form-' + formId);
    const button = form.previousElementSibling;

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        button.innerHTML = `
            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            Formular schließen
        `;
    } else {
        form.classList.add('hidden');
        button.innerHTML = `
            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Neuen Aufguss zu "${button.querySelector('span') ? button.querySelector('span').textContent : 'Plan'}" hinzufügen
        `;
    }
}

// Toggle-Funktion für ausklappbare Formulare (vereinfachte Version für index.php)
function toggleFormMain(formId) {
    const form = document.getElementById('form-' + formId);
    const button = form.previousElementSibling;

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        button.innerHTML = `
            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            Formular schließen
        `;
    } else {
        form.classList.add('hidden');
        button.innerHTML = `
            <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Neuen Aufguss erstellen
        `;
    }
}

// Inline-Editing Funktionen für Aufgüsse
function closeAllEditModes(exceptEditMode) {
    document.querySelectorAll('.edit-mode:not(.hidden)').forEach(panel => {
        if (exceptEditMode && panel === exceptEditMode) {
            return;
        }
        panel.classList.add('hidden');
        const container = panel.closest('td') || panel.closest('div');
        if (!container) {
            return;
        }
        const displayMode = container.querySelector('.display-mode');
        if (displayMode) {
            displayMode.classList.remove('hidden');
        }
    });

    if (typeof window.closeAllMultiSelectPanels === 'function') {
        window.closeAllMultiSelectPanels();
    }
}

function toggleEdit(aufgussId, field) {
    // Debug-Logging entfernt (lokaler Debug-Server nicht aktiv)

    const row = document.querySelector(`tr[data-aufguss-id="${aufgussId}"]`) ||
               event.target.closest('tr');
    const cell = row.querySelector(`.${field}-cell`);
    const displayMode = cell.querySelector('.display-mode');
    const editMode = cell.querySelector('.edit-mode');

    if (displayMode && editMode) {
        closeAllEditModes(editMode);
        displayMode.classList.add('hidden');
        editMode.classList.remove('hidden');

        // Initialize fields correctly - only one input type should have value
        initializeFieldExclusive(field, editMode);

        // Focus auf erstes Input-Feld setzen
        const firstInput = editMode.querySelector('input, select');
        if (firstInput) {
            firstInput.focus();
        }
    }
}

// Inline-Editing abbrechen und Anzeige zuruecksetzen.
function cancelEdit(aufgussId, field) {
    const row = document.querySelector(`tr[data-aufguss-id="${aufgussId}"]`) ||
               event.target.closest('tr');
    const cell = row.querySelector(`.${field}-cell`);
    const displayMode = cell.querySelector('.display-mode');
    const editMode = cell.querySelector('.edit-mode');

    if (displayMode && editMode) {
        displayMode.classList.remove('hidden');
        editMode.classList.add('hidden');

        // Formular zurücksetzen (Werte wiederherstellen)
        const inputs = editMode.querySelectorAll('input, select');
        inputs.forEach(input => {
            // Reset to original values (this would need to be implemented properly)
            // For now, just hide the edit mode
        });
    }
}

// Hilfsfunktion zur Initialisierung von Feldern mit exklusiver Eingabe
function initializeFieldExclusive(field, editMode) {
    let inputField, selectField;

    switch(field) {
        case 'aufguss':
            inputField = editMode.querySelector('input[name="aufguss_name"]');
            selectField = editMode.querySelector('select[name="select_aufguss_id"]');
            break;
        case 'mitarbeiter':
            inputField = editMode.querySelector('input[name="aufgieser_name"]');
            selectField = editMode.querySelector('select[name="mitarbeiter_id"]');
            break;
        case 'sauna':
            inputField = editMode.querySelector('input[name="sauna_name"]');
            selectField = editMode.querySelector('select[name="sauna_id"]');
            break;
        case 'duftmittel':
            inputField = editMode.querySelector('input[name="duftmittel_name"]');
            selectField = editMode.querySelector('select[name="duftmittel_id"]');
            break;
    }

    if (inputField && selectField) {
        // If input has value, clear select
        if (inputField.value.trim()) {
            selectField.value = '';
        }
        // If select has value, clear input
        else if (selectField.value) {
            inputField.value = '';
        }
    }
}

// Hilfsfunktionen für alle Felder mit exklusiver Eingabe
function handleFieldInput(aufgussId, field) {
    const editMode = document.querySelector(`.edit-mode[data-aufguss-id="${aufgussId}"]`);
    if (editMode) {
        let selectField;
        switch(field) {
            case 'aufguss':
                selectField = editMode.querySelector('select[name="select_aufguss_id"]');
                break;
            case 'mitarbeiter':
                selectField = editMode.querySelector('select[name="mitarbeiter_id"]');
                break;
            case 'sauna':
                selectField = editMode.querySelector('select[name="sauna_id"]');
                break;
            case 'duftmittel':
                selectField = editMode.querySelector('select[name="duftmittel_id"]');
                break;
        }
        if (selectField) {
            selectField.value = ''; // Select-Feld leeren wenn Input verwendet wird
        }
    }
}

// Leert den Freitext-Input, wenn ein Select gewaehlt wird.
function handleFieldSelect(aufgussId, field) {
    const editMode = document.querySelector(`.edit-mode[data-aufguss-id="${aufgussId}"]`);
    if (editMode) {
        let inputField;
        switch(field) {
            case 'aufguss':
                inputField = editMode.querySelector('input[name="aufguss_name"]');
                break;
            case 'mitarbeiter':
                inputField = editMode.querySelector('input[name="aufgieser_name"]');
                break;
            case 'sauna':
                inputField = editMode.querySelector('input[name="sauna_name"]');
                break;
            case 'duftmittel':
                inputField = editMode.querySelector('input[name="duftmittel_name"]');
                break;
        }
        if (inputField) {
            inputField.value = ''; // Input-Feld leeren wenn Select verwendet wird
        }
    }
}

// Speichert Inline-Aenderungen per Fetch.
function saveEdit(aufgussId, field) {
    const row = document.querySelector(`tr[data-aufguss-id="${aufgussId}"]`) ||
        event.target.closest('tr');
    const cell = row.querySelector(`.${field}-cell`);
    const editMode = cell.querySelector('.edit-mode');

    if (editMode) {
        // Zeit-Validierung (ohne Speichern zu blockieren)
        if (field === 'zeit') {
            const zeitAnfang = editMode.querySelector('input[name="zeit_anfang"]');
            const zeitEnde = editMode.querySelector('input[name="zeit_ende"]');

            if (zeitAnfang && zeitEnde) {
                const isValid = validateTimeOrder(zeitAnfang, zeitEnde, false);
                if (!isValid && typeof window.showToast === 'function') {
                    window.showToast('Endzeit darf nicht fr&uuml;her als Anfang sein.', 'error');
                }
            }
        }

        // Deaktiviere Buttons waehrend des Speicherns
        const buttons = editMode.querySelectorAll('button');
        buttons.forEach(button => button.disabled = true);
        const originalText = buttons[0].innerHTML;
        buttons[0].innerHTML = 'Speichere...';

        const formData = new FormData();
        formData.append('aufguss_id', aufgussId);
        formData.append('field', field);

        // Sammle alle Input-Werte (auch leere Werte fuer NULL-Updates)
        const inputs = editMode.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.tagName === 'SELECT' && input.multiple) {
                const selected = Array.from(input.selectedOptions).map(option => option.value);
                if (selected.length === 0) {
                    formData.append(input.name, '');
                } else {
                    selected.forEach(value => formData.append(input.name, value));
                }
                return;
            }

            if (input.type === 'checkbox') {
                if (input.checked) {
                    formData.append(input.name, input.value);
                }
                return;
            }

            // Immer den Wert senden, auch wenn er leer ist
            formData.append(input.name, input.value ? input.value.trim() : '');
        });

        // Debug: Zeige gesendete Daten
        console.log('Sending data for', field, ':', Array.from(formData.entries()));

        // Zusaetzliche Debug-Ausgabe
        console.log('All inputs found:', inputs);
        inputs.forEach(input => {
            console.log(`Input ${input.name}: "${input.value}"`);
        });

        // AJAX Request zum Speichern
        fetch('../updates/update_aufguss.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Gespeichert', 'success');
                    }
                    setTimeout(() => location.reload(), 700);
                } else {
                    // Fehler: Buttons wieder aktivieren
                    buttons.forEach(button => button.disabled = false);
                    buttons[0].innerHTML = originalText;
                    alert('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'));
                    console.error('Server Error:', data);
                }
            })
            .catch(error => {
                // Fehler: Buttons wieder aktivieren
                buttons.forEach(button => button.disabled = false);
                buttons[0].innerHTML = originalText;
                console.error('Network Error:', error);
                alert('Netzwerkfehler beim Speichern der Daten.');
            });
    }
}
function deletePlan(planId, planName) {
    // Sicherheitsabfrage vor dem Löschen
    if (!confirm(`Bist du sicher, dass du den Plan "${planName}" löschen möchtest?\n\nAlle Aufgüsse in diesem Plan bleiben erhalten, werden aber keinem Plan mehr zugeordnet.`)) {
        return;
    }

    // AJAX Request zum Löschen
    fetch('../deletes/delete_plan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plan_id=' + encodeURIComponent(planId)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Erfolg: Seite neu laden um Aenderungen anzuzeigen
            if (typeof window.showToast === 'function') {
                window.showToast('Gelöscht', 'success');
            }
            setTimeout(() => location.reload(), 700);
        } else {
            // Fehler anzeigen
            alert('Fehler beim Loeschen: ' + (data.error || 'Unbekannter Fehler'));
            console.error('Server Error:', data);
        }
    })
    .catch(error => {
        console.error('Network Error:', error);
        alert('Netzwerkfehler beim Löschen des Plans.');
    });
}

/**
 * Löscht einen Aufguss
 */
function deleteAufguss(aufgussId) {
    // Sicherheitsabfrage vor dem Löschen
    if (!confirm('Bist du sicher, dass du diesen Aufguss löschen möchtest?\n\nDieser Vorgang kann nicht rückgängig gemacht werden.')) {
        return;
    }

    // Stattdessen: Seite neu laden mit Lösch-Parameter
    window.location.href = 'deletes/delete_aufguss.php?id=' + encodeURIComponent(aufgussId);
}

// Zeit-Validierung für Formulare
function validateTimeFields(container) {
    const zeitAnfangFields = container.querySelectorAll('input[name="zeit_anfang"], input[id^="zeit_anfang-"]');
    const zeitEndeFields = container.querySelectorAll('input[name="zeit_ende"], input[id^="zeit_ende-"]');

    zeitAnfangFields.forEach((anfangField, index) => {
        const endeField = zeitEndeFields[index];
        if (anfangField && endeField) {
            // Entferne vorherige Event-Listener
            anfangField.removeEventListener('change', validateTimeOrder);
            endeField.removeEventListener('change', validateTimeOrder);

            // Füge neue Event-Listener hinzu
            anfangField.addEventListener('change', () => validateTimeOrder(anfangField, endeField));
            endeField.addEventListener('change', () => validateTimeOrder(anfangField, endeField));
        }
    });
}

// Prueft, ob Endzeit vor Startzeit liegt.
function validateTimeOrder(anfangField, endeField, showToast = true) {
    if (!anfangField.value || !endeField.value) {
        anfangField.classList.remove('border-red-500', 'border-green-500');
        endeField.classList.remove('border-red-500', 'border-green-500');
        return true;
    }

    const anfangTime = new Date('1970-01-01T' + anfangField.value + ':00');
    const endeTime = new Date('1970-01-01T' + endeField.value + ':00');

    anfangField.classList.remove('border-red-500', 'border-green-500');
    endeField.classList.remove('border-red-500', 'border-green-500');

    if (endeTime < anfangTime) {
        anfangField.classList.add('border-red-500');
        endeField.classList.add('border-red-500');
        if (showToast && typeof window.showToast === 'function') {
            window.showToast('Endzeit darf nicht fr&uuml;her als Anfang sein.', 'error');
        }
        return false;
    }

    anfangField.classList.add('border-green-500');
    endeField.classList.add('border-green-500');
    return true;
}

// DOM-Event-Listener für verschiedene Seiten
document.addEventListener('DOMContentLoaded', function() {
    // Abwärtskompatibilität: zeit-Feld mit zeit_anfang synchronisieren (für index.php)
    const zeitAnfang = document.getElementById('zeit_anfang');
    const zeit = document.getElementById('zeit');
    if (zeitAnfang && zeit) {
        zeitAnfang.addEventListener('change', function() {
            zeit.value = this.value;
        });

        // Beim Laden der Seite: wenn zeit_anfang gesetzt ist, zeit damit füllen
        if (zeitAnfang.value && !zeit.value) {
            zeit.value = zeitAnfang.value;
        }
    }

    // Abwärtskompatibilität: zeit-Felder synchronisieren (für aufguesse.php)
    document.querySelectorAll('[id^="zeit_anfang-"]').forEach(function(element) {
        element.addEventListener('change', function() {
            const planId = this.id.split('-')[1];
            const zeitField = document.getElementById('zeit-' + planId);
            if (zeitField) {
                zeitField.value = this.value;
            }
        });
    });

    // Zeit-Validierung für alle Formulare aktivieren
    validateTimeFields(document);

    // Multi-Selects: Klick toggelt Auswahl ohne Strg/Cmd
    document.querySelectorAll('select[multiple]').forEach(select => {
        select.addEventListener('mousedown', event => {
            if (event.target.tagName === 'OPTION') {
                event.preventDefault();
                event.target.selected = !event.target.selected;
                select.focus();
            }
        });

        select.addEventListener('change', () => {
            const container = select.closest('.edit-mode, form');
            if (!container) return;
            const singleInput = container.querySelector('input[name="aufgieser"], input[name="aufgieser_name"]');
            const singleSelect = container.querySelector('select[name="mitarbeiter_id"]');
            if (singleInput) singleInput.value = '';
            if (singleSelect) singleSelect.value = '';
        });
    });

    document.querySelectorAll('input[type="checkbox"][name="mitarbeiter_ids[]"]').forEach(box => {
        box.addEventListener('change', () => {
            const container = box.closest('.edit-mode, form');
            if (!container) return;
            const singleInput = container.querySelector('input[name="aufgieser"], input[name="aufgieser_name"]');
            const singleSelect = container.querySelector('select[name="mitarbeiter_id"]');
            if (singleInput) singleInput.value = '';
            if (singleSelect) singleSelect.value = '';
        });
    });
});

window.toggleForm = toggleForm;
window.toggleFormMain = toggleFormMain;
window.toggleEdit = toggleEdit;
window.closeAllEditModes = closeAllEditModes;
window.cancelEdit = cancelEdit;
window.handleFieldInput = handleFieldInput;
window.handleFieldSelect = handleFieldSelect;
window.saveEdit = saveEdit;
window.deletePlan = deletePlan;
window.deleteAufguss = deleteAufguss;
window.validateTimeFields = validateTimeFields;








