// Admin-Aufguesse: ausgelagerte Logik fuer public/admin/pages/aufguesse.php
// Alle Kommentare bewusst auf Deutsch (ASCII) gehalten.


const placeholderMitarbeiter = '../../assets/placeholders/Platzhalter_Mitarbeiter.svg';
const placeholderSauna = '../../assets/placeholders/Platzhalter_Sauna.svg';

// Baut ein IMG-Tag mit Fallback fuer fehlende Bilder.
function buildImageTag(src, fallbackSrc, alt, className) {
    const safeAlt = String(alt || '');
    const safeSrc = src || fallbackSrc;
    return `<img src="${safeSrc}" alt="${safeAlt}" class="${className}" onerror="this.onerror=null;this.src='${fallbackSrc}'">`;
}

// Liefert Staerke-Icons wie in der Tabelle.
function getStaerkeIconBaseUrl() {
    return '../../uploads/';
}

function getStaerkeBadgeCategory(level) {
    const staerke = Number(level) || 0;
    if (staerke <= 0) return 0;
    if (staerke === 1) return 1;
    if (staerke === 2) return 2;
    return 3;
}

function formatStaerke(aufguss) {
    const level = Number(aufguss?.staerke) || 0;
    const category = getStaerkeBadgeCategory(level);
    const iconPath = (aufguss?.staerke_icon || '').trim();
    let iconHtml = '';
    if (iconPath && category > 0) {
        const safePath = String(iconPath).replace(/"/g, '&quot;');
        const baseUrl = getStaerkeIconBaseUrl();
        const icons = Array.from({ length: category })
            .map(() => `<img src="${baseUrl}${safePath}" alt="Staerke-Icon" decoding="async" class="plan-list-staerke-icon">`)
            .join('');
        iconHtml = `<div class="plan-list-staerke-icons">${icons}</div>`;
    }
    return { iconHtml };
}
// Dateiname-Feedback für Bild-Uploads
        // Funktion: updateFileName
        function updateFileName(type, planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            if (loadingBar) {
                showLoading(planId);
            }

            const input = document.getElementById(`${type}-bild-${planId}`);
            const filenameDiv = document.getElementById(`${type}-filename-${planId}`);
            const filenameText = document.getElementById(`${type}-filename-text-${planId}`);

            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                // Dateigröße validieren
                if (fileSize > 10) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu groß! Max. 10MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else {
                filenameDiv.classList.add('hidden');
            }

            if (loadingBar) {
                hideLoading(planId);
            }
        }

        // Funktion: setupMultiSelects
        function setupMultiSelects() {
            const openPanels = new Set();

            const closePanel = (panel) => {
                if (!panel) {
                    return;
                }
                panel.classList.add('hidden');
                if (panel._reposition) {
                    window.removeEventListener('resize', panel._reposition);
                    window.removeEventListener('scroll', panel._reposition, true);
                }
                if (panel._scrollParent && panel._reposition) {
                    panel._scrollParent.removeEventListener('scroll', panel._reposition);
                }
                if (panel._originalParent) {
                    panel._originalParent.appendChild(panel);
                }
                panel.style.position = '';
                panel.style.left = '';
                panel.style.top = '';
                panel.style.width = '';
                panel.style.zIndex = '';
                openPanels.delete(panel);
            };

            const closeAllPanels = () => {
                Array.from(openPanels).forEach(closePanel);
            };

            window.closeAllMultiSelectPanels = closeAllPanels;

            document.querySelectorAll('.multi-select').forEach(select => {
                const trigger = select.querySelector('.multi-select-trigger');
                const panel = select.querySelector('.multi-select-panel');
                const checkboxes = Array.from(select.querySelectorAll('input[type="checkbox"]'));
                const placeholder = select.dataset.placeholder || 'Auswahl';

                if (!trigger || !panel) {
                    return;
                }

                const updateLabel = () => {
                    const selected = checkboxes
                        .filter(box => box.checked)
                        .map(box => {
                            const label = box.closest('label');
                            const text = label ? label.textContent : '';
                            return text.trim();
                        })
                        .filter(Boolean);
                    trigger.textContent = selected.length ? selected.join(', ') : placeholder;
                    checkboxes.forEach(box => {
                        const label = box.closest('label');
                        if (!label) {
                            return;
                        }
                        label.classList.toggle('is-selected', box.checked);
                    });
                };

                const positionPanel = () => {
                    const rect = trigger.getBoundingClientRect();
                    panel.style.position = 'fixed';
                    panel.style.left = `${rect.left}px`;
                    panel.style.top = `${rect.bottom}px`;
                    panel.style.width = `${rect.width}px`;
                    panel.style.zIndex = '9999';
                };

                const openPanel = () => {
                    closeAllPanels();
                    if (!panel._originalParent) {
                        panel._originalParent = panel.parentElement;
                    }
                    panel._scrollParent = select.closest('.plan-table-scroll');
                    panel._reposition = positionPanel;
                    document.body.appendChild(panel);
                    panel.classList.remove('hidden');
                    positionPanel();
                    window.addEventListener('resize', positionPanel);
                    window.addEventListener('scroll', positionPanel, true);
                    if (panel._scrollParent) {
                        panel._scrollParent.addEventListener('scroll', positionPanel);
                    }
                    openPanels.add(panel);
                };

                trigger.addEventListener('click', (event) => {
                    event.stopPropagation();
                    if (panel.classList.contains('hidden')) {
                        openPanel();
                    } else {
                        closePanel(panel);
                    }
                });

                panel.addEventListener('click', (event) => {
                    event.stopPropagation();
                });

                checkboxes.forEach(box => {
                    box.addEventListener('change', updateLabel);
                });

                updateLabel();
            });

            document.addEventListener('click', () => {
                closeAllPanels();
            });
        }

        // Initialisierung nach DOM-Laden
        document.addEventListener('DOMContentLoaded', setupMultiSelects);

        // Datei entfernen
        // Funktion: removeFile
        function removeFile(type, planId) {
            const input = document.getElementById(`${type}-bild-${planId}`);
            const filenameDiv = document.getElementById(`${type}-filename-${planId}`);

            // Datei-Eingabe zurücksetzen
            input.value = '';

            // Feedback ausblenden
            filenameDiv.classList.add('hidden');

            // Optional: Benutzer benachrichtigen
            console.log(`${type}-Bild für Plan ${planId} entfernt`);
        }

        // Funktion: updateAdFileName
        function updateAdFileName(planId) {
            const input = document.getElementById(`plan-ad-file-${planId}`);
            const filenameDiv = document.getElementById(`plan-ad-filename-${planId}`);
            const filenameText = document.getElementById(`plan-ad-filename-text-${planId}`);

            if (input && input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                if (fileSize > 50) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu gross! Max. 50MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else if (filenameDiv) {
                filenameDiv.classList.add('hidden');
            }
        }

        // Funktion: removeAdFile
        function removeAdFile(planId) {
            const input = document.getElementById(`plan-ad-file-${planId}`);
            const filenameDiv = document.getElementById(`plan-ad-filename-${planId}`);
            if (input) input.value = '';
            if (filenameDiv) filenameDiv.classList.add('hidden');
        }


        // Ladebalken beim Formular-Submit
        // Funktion: showLoading
        function showLoading(planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            const submitBtn = document.getElementById(`submit-btn-${planId}`);
            const progressBar = loadingBar.querySelector('div');

            // Ladebalken anzeigen
            loadingBar.classList.remove('hidden');

            // Button deaktivieren
            submitBtn.disabled = true;
            submitBtn.textContent = 'Lädt...';

            // Animierter Ladebalken
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90; // Nicht auf 100% gehen, bis wirklich fertig

                progressBar.style.width = progress + '%';
            }, 200);

            // Interval-ID speichern für späteres Stoppen
            loadingBar.dataset.intervalId = interval;
        }

        // Ladebalken ausblenden
        // Funktion: hideLoading
        function hideLoading(planId) {
            const loadingBar = document.getElementById(`loading-bar-${planId}`);
            const submitBtn = document.getElementById(`submit-btn-${planId}`);
            const progressBar = loadingBar.querySelector('div');

            // Ladebalken auf 100% setzen
            progressBar.style.width = '100%';

            // Nach kurzer Verzögerung ausblenden
            setTimeout(() => {
                loadingBar.classList.add('hidden');
                progressBar.style.width = '0%';

                // Button wieder aktivieren
                submitBtn.disabled = false;
                submitBtn.textContent = 'Speichern';

                // Interval stoppen falls noch läuft
                if (loadingBar.dataset.intervalId) {
                    clearInterval(loadingBar.dataset.intervalId);
                    delete loadingBar.dataset.intervalId;
                }
            }, 500);
        }

        // Funktion: clearSaunaTemperatur
        function clearSaunaTemperatur(saunaId) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const editMode = row.querySelector('.sauna-temp-cell .edit-mode');
            const input = editMode.querySelector('input[name="sauna_temperatur"]');
            if (!input) return;
            input.value = '';
            saveSaunaEdit(saunaId, 'temperatur');
        }

        // Inline-Editing für Saunen
        // Funktion: toggleSaunaEdit
        function toggleSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode`);
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);

            if (window.closeAllEditModes) {
                window.closeAllEditModes(editMode);
            }
            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        // Funktion: cancelSaunaEdit
        function cancelSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode`);
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        // Funktion: saveSaunaEdit
        async function saveSaunaEdit(saunaId, field) {
            const row = document.querySelector(`[data-sauna-id="${saunaId}"]`);
            const fieldMap = {
                name: 'name',
                beschreibung: 'desc',
                temperatur: 'temp'
            };
            const key = fieldMap[field] || 'name';
            const editMode = row.querySelector(`.sauna-${key}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('../updates/update_sauna.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: saunaId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.sauna-${key}-cell .display-mode span`);
                    if (field === 'beschreibung') {
                        displayMode.textContent = (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;
                    } else if (field === 'temperatur') {
                        displayMode.textContent = (!newValue || newValue.trim() === '') ? '-' : `${parseInt(newValue, 10)}°C`;
                    } else {
                        displayMode.textContent = newValue;
                    }

                    cancelSaunaEdit(saunaId, field);
                    if (window.showToast) {
                        window.showToast('Gespeichert', 'success');
                    }
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing für Mitarbeiter
        // Funktion: toggleMitarbeiterEdit
        function toggleMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);

            if (window.closeAllEditModes) {
                window.closeAllEditModes(editMode);
            }
            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        // Funktion: cancelMitarbeiterEdit
        function cancelMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        // Funktion: saveMitarbeiterEdit
        async function saveMitarbeiterEdit(mitarbeiterId, field) {
            const row = document.querySelector(`[data-mitarbeiter-id="${mitarbeiterId}"]`);
            const editMode = row.querySelector(`.mitarbeiter-name-cell .edit-mode`);
            const input = editMode.querySelector('input');
            const newValue = input.value;

            try {
                const response = await fetch('../updates/update_mitarbeiter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: mitarbeiterId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.mitarbeiter-name-cell .display-mode span`);
                    displayMode.textContent = newValue;

                    cancelMitarbeiterEdit(mitarbeiterId, field);
                    if (window.showToast) {
                        window.showToast('Gespeichert', 'success');
                    }
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing fuer Aufgussnamen
        // Funktion: toggleAufgussNameEdit
        function toggleAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            if (window.closeAllEditModes) {
                window.closeAllEditModes(editMode);
            }
            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        // Funktion: cancelAufgussNameEdit
        function cancelAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        // Funktion: saveAufgussNameEdit
        async function saveAufgussNameEdit(aufgussId, field) {
            const row = document.querySelector(`[data-aufguss-name-id="${aufgussId}"]`);
            const editMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('../updates/update_aufguss_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: aufgussId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    const displayMode = row.querySelector(`.aufguss-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode span`);
                    displayMode.textContent = field === 'beschreibung' && (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;

                    cancelAufgussNameEdit(aufgussId, field);
                    if (window.showToast) {
                        window.showToast('Gespeichert', 'success');
                    }
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Inline-Editing für Duftmittel
        // Funktion: toggleDuftmittelEdit
        function toggleDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            if (window.closeAllEditModes) {
                window.closeAllEditModes(editMode);
            }
            displayMode.classList.add('hidden');
            editMode.classList.remove('hidden');
        }

        // Funktion: cancelDuftmittelEdit
        function cancelDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);

            editMode.classList.add('hidden');
            displayMode.classList.remove('hidden');
        }

        // Funktion: saveDuftmittelEdit
        async function saveDuftmittelEdit(duftmittelId, field) {
            const row = document.querySelector(`[data-duftmittel-id="${duftmittelId}"]`);
            const editMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .edit-mode`);
            const input = editMode.querySelector(field === 'beschreibung' ? 'textarea' : 'input');
            const newValue = input.value;

            try {
                const response = await fetch('../updates/update_duftmittel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: duftmittelId,
                        field: field,
                        value: newValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Erfolg - Display-Modus aktualisieren
                    const displayMode = row.querySelector(`.duftmittel-${field === 'beschreibung' ? 'desc' : 'name'}-cell .display-mode span`);
                    displayMode.textContent = field === 'beschreibung' && (!newValue || newValue.trim() === '') ? 'Keine Beschreibung' : newValue;

                    cancelDuftmittelEdit(duftmittelId, field);
                    if (window.showToast) {
                        window.showToast('Gespeichert', 'success');
                    }
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern');
                console.error('Save error:', error);
            }
        }

        // Bild-Modal Funktionen
        let currentEntityType = '';
        let currentEntityId = '';

        function populateModalExistingImages(entityType) {
            const select = document.getElementById('modalExistingImage');
            if (!select) {
                return;
            }
            const optionsByType = window.MODAL_IMAGE_OPTIONS || {};
            const files = optionsByType[entityType] || [];
            select.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- Bitte waehlen --';
            select.appendChild(placeholder);

            files.forEach((fileName) => {
                const option = document.createElement('option');
                option.value = fileName;
                option.textContent = fileName;
                select.appendChild(option);
            });

            select.disabled = files.length === 0;
        }

        // Funktion: openImageModal
        function openImageModal(entityType, entityId, entityName) {
            currentEntityType = entityType;
            currentEntityId = entityId;

            let title = '';
            if (entityType === 'plan') {
                title = `Hintergrundbild für Plan "${entityName}" ändern`;
            } else {
                title = `Bild für ${entityName} ändern`;
            }

            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalEntityType').value = entityType;
            document.getElementById('modalEntityId').value = entityId;
            document.getElementById('imageModal').classList.remove('hidden');

            const placeholders = document.querySelectorAll('[data-modal-placeholder]');
            placeholders.forEach((img) => {
                const match = img.getAttribute('data-modal-placeholder') === entityType;
                img.classList.toggle('hidden', !match);
            });

            // Reset form
            document.getElementById('imageUploadForm').reset();
            document.getElementById('modalFilename').classList.add('hidden');
            document.getElementById('modalLoadingBar').classList.add('hidden');
            document.getElementById('modalSubmitBtn').disabled = false;
            document.getElementById('modalSubmitBtn').textContent = 'Speichern';
            populateModalExistingImages(entityType);
        }

        // Funktion: deleteUploadFile
        async function deleteUploadFile(type, path) {
            if (!confirm('Datei wirklich Löschen?')) {
                return;
            }

            try {
                const response = await fetch('../deletes/delete_upload_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type,
                        path
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Löschen der Datei.');
                    return;
                }
                location.reload();
            } catch (error) {
                alert('Netzwerkfehler beim Löschen der Datei.');
            }
        }

        // Funktion: deletePlanBackgroundImage
        async function deletePlanBackgroundImage(planId, planName) {
            if (!confirm(`Möchten Sie das Hintergrundbild für "${planName}" wirklich löschen?`)) {
                return;
            }

            try {
                const response = await fetch('../deletes/delete_entity_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        entity_type: 'plan',
                        entity_id: planId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Netzwerkfehler beim Löschen');
                console.error('Delete error:', error);
            }
        }

        // Funktion: uploadPlanBackgroundImage
        async function uploadPlanBackgroundImage(planId) {
            const input = document.getElementById(`plan-bild-${planId}`);
            const submitBtn = document.getElementById(`plan-upload-btn-${planId}`);

            if (!input || !input.files || !input.files[0]) {
                alert('Bitte waehlen Sie eine Datei aus.');
                return;
            }

            const file = input.files[0];
            const fileSize = file.size / 1024 / 1024;
            if (fileSize > 10) {
                alert('Die Datei ist zu gross. Maximale Groesse: 10MB');
                return;
            }

            const formData = new FormData();
            formData.append('entity_type', 'plan');
            formData.append('entity_id', planId);
            formData.append('bild', file);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Laedt...';

            try {
                const response = await fetch('../uploads/upload_entity_image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Hochladen: ' + (result.error || 'Unbekannter Fehler'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Speichern';
                }
            } catch (error) {
                alert('Netzwerkfehler beim Hochladen');
                console.error('Upload error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Speichern';
            }
        }

        // Funktion: closeImageModal
        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            currentEntityType = '';
            currentEntityId = '';
        }

        // Funktion: updateModalFileName
        function updateModalFileName() {
            const input = document.getElementById('modalImageInput');
            const filenameDiv = document.getElementById('modalFilename');
            const filenameText = document.getElementById('modalFilenameText');
            const existingSelect = document.getElementById('modalExistingImage');

            if (input.files && input.files[0]) {
                if (existingSelect) {
                    existingSelect.value = '';
                }
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);

                filenameText.textContent = `${fileName} (${fileSize}MB)`;
                filenameDiv.classList.remove('hidden');

                // Dateigröße validieren
                if (fileSize > 10) {
                    filenameDiv.classList.remove('text-green-600');
                    filenameDiv.classList.add('text-red-600');
                    filenameText.textContent = `${fileName} (${fileSize}MB - Zu groß! Max. 10MB)`;
                } else {
                    filenameDiv.classList.remove('text-red-600');
                    filenameDiv.classList.add('text-green-600');
                }
            } else {
                filenameDiv.classList.add('hidden');
            }
        }

        // Funktion: removeModalFile
        function removeModalFile() {
            document.getElementById('modalImageInput').value = '';
            document.getElementById('modalFilename').classList.add('hidden');
        }

        const modalExistingImage = document.getElementById('modalExistingImage');
        if (modalExistingImage) {
            modalExistingImage.addEventListener('change', function() {
                if (this.value) {
                    removeModalFile();
                }
            });
        }

        // Modal Form Submit
        // Formular-Upload im Modal abfangen
        document.getElementById('imageUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = document.getElementById('modalSubmitBtn');
            const loadingBar = document.getElementById('modalLoadingBar');
            const progressBar = loadingBar.querySelector('div');
            const existingSelect = document.getElementById('modalExistingImage');
            const selectedExisting = existingSelect ? existingSelect.value : '';
            const selectedFile = formData.get('bild');

            // Validierung
            if ((!selectedFile || !selectedFile.name) && !selectedExisting) {
                alert('Bitte wählen Sie ein Bild aus.');
                return;
            }

            if (selectedExisting) {
                formData.set('existing_bild', selectedExisting);
                if (selectedFile && selectedFile.name) {
                    formData.delete('bild');
                }
            }

            // Dateigröße prüfen
            if (selectedFile && selectedFile.name) {
                const fileSize = selectedFile.size / 1024 / 1024;
                if (fileSize > 10) {
                    alert('Die Datei ist zu groß. Maximale Größe: 10MB');
                    return;
                }
            }

            // Ladezustand aktivieren
            submitBtn.disabled = true;
            submitBtn.textContent = 'Lädt...';
            loadingBar.classList.remove('hidden');

            // Animierter Ladebalken
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
            }, 200);

            try {
                const response = await fetch('../uploads/upload_entity_image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                clearInterval(interval);
                progressBar.style.width = '100%';

                if (result.success) {
                    // Erfolg - Seite neu laden um Änderungen zu zeigen
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Fehler beim Hochladen: ' + (result.error || 'Unbekannter Fehler'));
                    loadingBar.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Hochladen';
                }
            } catch (error) {
                clearInterval(interval);
                alert('Netzwerkfehler beim Hochladen');
                loadingBar.classList.add('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Hochladen';
            }
        });

        // Modal außerhalb klicken zum schließen
        // Klick ausserhalb schliesst das Bild-Modal
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
        // Klick ausserhalb schliesst das Banner-Modal
        document.getElementById('planBannerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePlanBannerModal();
            }
        });

        // Verbesserte Drag & Drop Funktionalität
        // Initialisierung nach DOM-Laden
        document.addEventListener('DOMContentLoaded', function() {
            const dropZones = document.querySelectorAll('.border-dashed');

            dropZones.forEach(zone => {
                zone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('border-indigo-400', 'bg-indigo-50');
                });

                zone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-indigo-400', 'bg-indigo-50');
                });

                zone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-indigo-400', 'bg-indigo-50');

                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const input = this.querySelector('input[type="file"]');
                        if (input) {
                            input.files = files;
                            // Trigger change event
                            const event = new Event('change');
                            input.dispatchEvent(event);
                        }
                    }
                });
            });
        });

        // Naechster Aufguss Popup (5 Sekunden vor Start)
        const nextAufgussQueue = [];
        const nextAufgussShown = new Set();
        const nextAufgussSettings = new Map();
        let nextAufgussActive = false;
        let nextAufgussActivePlanId = null;
        let nextAufgussHideTimer = null;
        let nextAufgussCountdownTimer = null;
        let nextAufgussCountdownTarget = null;
        let nextAufgussIsPreview = false;
        const planAdSettings = new Map();
        const planAdMedia = new Map();
        const planAdIntervals = new Map();
        const planAdHideTimers = new Map();
        let adminClockTimer = null;

        // Funktion: parseStartTime
        function parseStartTime(text) {
            if (!text) return null;
            const match = text.match(/(\d{1,2})(?::(\d{2}))?/);
            if (!match) return null;
            const hour = parseInt(match[1], 10);
            const minute = match[2] ? parseInt(match[2], 10) : 0;

            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
            if (start.getTime() < now.getTime() - 1000) {
                start.setDate(start.getDate() + 1);
            }
            return start;
        }

        // Funktion: updateNextAufgussRowHighlight
        function updateNextAufgussRowHighlight() {
            const rows = Array.from(document.querySelectorAll('tr[data-aufguss-id]'));
            if (rows.length === 0) return;

            const rowsByPlan = new Map();
            rows.forEach(row => {
                const planId = row.getAttribute('data-plan-id');
                if (!planId) return;
                if (!rowsByPlan.has(planId)) rowsByPlan.set(planId, []);
                rowsByPlan.get(planId).push(row);
            });

            rowsByPlan.forEach(planRows => {
                const planId = planRows[0]?.getAttribute('data-plan-id');
                const settings = planId ? (nextAufgussSettings.get(String(planId)) || getPlanSettings(planId)) : null;
                if (settings && !settings.highlightEnabled) {
                    planRows.forEach(row => row.classList.remove('next-aufguss-row'));
                    return;
                }

                let nextRow = null;
                let nextTime = null;

                planRows.forEach(row => {
                    const timeText = row.querySelector('.zeit-cell .display-mode span')?.textContent?.trim();
                    const startTime = parseStartTime(timeText);
                    if (!startTime) return;

                    if (!nextTime || startTime.getTime() < nextTime) {
                        nextTime = startTime.getTime();
                        nextRow = row;
                    }
                });

                planRows.forEach(row => row.classList.remove('next-aufguss-row'));
                if (nextRow) {
                    nextRow.classList.add('next-aufguss-row');
                }
            });
        }

        // Funktion: buildNextAufgussHtml
        function buildNextAufgussHtml(data) {
            const aufgussName = data.name || 'Aufguss';
            const staerkeInfo = formatStaerke(data);
            const staerkeText = data.staerke ? `Stärke: ${data.staerke}` : 'Stärke: -';
            const staerkeLine = staerkeInfo.iconHtml
                ? `<div class="next-aufguss-info-label">Stärke</div><div class="next-aufguss-staerke-icons">${staerkeInfo.iconHtml}</div>`
                : '';
            const aufgieserRaw = data.aufgieser_name || '-';
            const aufgieserList = aufgieserRaw
                .split(',')
                .map(item => item.trim())
                .filter(Boolean);
            const aufgieser = aufgieserList.length > 1 ? aufgieserList.join('<br>') : aufgieserRaw;
            const saunaName = data.sauna_name || 'Sauna: -';
            const saunaTempText = (data.sauna_temperatur !== null && data.sauna_temperatur !== undefined && data.sauna_temperatur !== '')
                ? String(data.sauna_temperatur)
                : '';
            const saunaTempLine = saunaTempText ? `Temperatur: ${saunaTempText}&deg;C` : 'Temperatur: -';
            const saunaTempLabel = 'Temperatur';
            const saunaTempValue = saunaTempText ? `${saunaTempText}&deg;C` : '-';
            const duftmittelNameRaw = data.duftmittel_name || '';
            const duftmittelName = duftmittelNameRaw ? String(duftmittelNameRaw).trim() : '';
            const duftmittelImage = data.duftmittel_bild ? String(data.duftmittel_bild).trim() : '';
            let duftmittelLine = '';
            if (duftmittelImage) {
                const imgSrc = `../../uploads/${duftmittelImage.replace(/"/g, '&quot;')}`;
                const label = duftmittelName ? duftmittelName : 'Duftmittel';
                duftmittelLine = `<div class="next-aufguss-info-label">${label}</div><img src="${imgSrc}" alt="Duftmittel" class="plan-list-staerke-icon" onerror="this.onerror=null;this.remove();">`;
            } else if (duftmittelName) {
                duftmittelLine = `<div class="next-aufguss-info-label">Duftmittel</div><div>${duftmittelName}</div>`;
            }
            const infoGridRows = [];
            if (staerkeLine) {
                infoGridRows.push(staerkeLine);
            }
            if (duftmittelLine) {
                infoGridRows.push(duftmittelLine);
            }
            infoGridRows.push(`<div class="next-aufguss-info-label">${saunaTempLabel}</div><div>${saunaTempValue}</div>`);
            const infoGrid = infoGridRows.length > 0
                ? `<div class="next-aufguss-info-grid">${infoGridRows.join('')}</div>`
                : '';

            const aufgieserItems = (data.aufgieser_items || '')
                .split(';;')
                .map(item => item.split('||'))
                .filter(parts => parts[0] && parts[0].trim() !== '');
            const aufgieserImages = aufgieserItems.map(parts => {
                const name = parts[0] ? parts[0].trim() : 'Aufgieser';
                const bild = parts[1] ? parts[1].trim() : '';
                const img = buildImageTag(
                    bild ? `../../uploads/${bild}` : '',
                    placeholderMitarbeiter,
                    name,
                    'w-full h-40 object-contain rounded-lg bg-white/80'
                );
                return `<div class="flex flex-col gap-2 text-center"><div>${img}</div><div class="text-sm font-semibold text-gray-900">${name}</div></div>`;
            });

            const mitarbeiterImg = aufgieserImages.length > 0
                ? `<div class="flex flex-col gap-3">${aufgieserImages.join('')}</div>`
                : buildImageTag(
                    data.mitarbeiter_bild ? `../../uploads/${data.mitarbeiter_bild}` : '',
                    placeholderMitarbeiter,
                    'Aufgieser',
                    'w-full h-72 object-contain rounded-lg bg-white/80'
                );

            const saunaImg = buildImageTag(
                data.sauna_bild ? `../../uploads/${data.sauna_bild}` : '',
                placeholderSauna,
                saunaName,
                'w-full h-72 object-contain rounded-lg bg-white/80'
            );

            return `
                <div class="relative flex flex-col gap-4">
                    <div class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none">
                        <div class="text-8xl font-bold text-gray-900 bg-white/80 border border-white/80 rounded-full px-10 py-4 shadow-lg" id="next-aufguss-countdown">--</div>
                    </div>
                    <div class="relative z-10 flex flex-col gap-6 min-h-[70vh]">
                        <div class="flex flex-col gap-2 text-center">
                            <div class="text-3xl font-bold text-gray-900 font-display">${aufgussName}</div>
                            ${infoGrid ? `<div class="text-lg font-semibold text-gray-900">${infoGrid}</div>` : ''}
                        </div>
                        <div class="mt-auto grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2">
                                ${saunaImg}
                                <div class="text-sm font-semibold text-gray-900 text-center">${saunaName}</div>
                            </div>
                            <div class="flex flex-col gap-3">
                                ${mitarbeiterImg}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Funktion: applyNextAufgussTheme
        function applyNextAufgussTheme(settings) {
            const card = document.getElementById('next-aufguss-card');
            if (!card || !settings) return;
            if (settings.themeColor) {
                card.style.setProperty('--next-aufguss-theme-color', settings.themeColor);
            }
            if (settings.textColor) {
                card.style.setProperty('--next-aufguss-text-color', settings.textColor);
            }
        }

        // Funktion: getPlanSettings
        function getPlanSettings(planId) {
            const keyEnabled = `nextAufgussEnabled_${planId}`;
            const keyLead = `nextAufgussLeadSeconds_${planId}`;
            const keyHighlight = `nextAufgussHighlightEnabled_${planId}`;
            const keyClock = `nextAufgussClockEnabled_${planId}`;
            const keyBannerEnabled = `nextAufgussBannerEnabled_${planId}`;
            const keyBannerMode = `nextAufgussBannerMode_${planId}`;
            const keyBannerText = `nextAufgussBannerText_${planId}`;
            const keyBannerImage = `nextAufgussBannerImage_${planId}`;
            const keyBannerHeight = `nextAufgussBannerHeight_${planId}`;
            const keyBannerWidth = `nextAufgussBannerWidth_${planId}`;
            const keyThemeColor = `nextAufgussThemeColor_${planId}`;
            const keyTextColor = `nextAufgussTextColor_${planId}`;
            const enabled = localStorage.getItem(keyEnabled);
            const leadSeconds = localStorage.getItem(keyLead);
            const highlightEnabled = localStorage.getItem(keyHighlight);
            const clockEnabled = localStorage.getItem(keyClock);
            const bannerEnabled = localStorage.getItem(keyBannerEnabled);
            const bannerMode = localStorage.getItem(keyBannerMode);
            const bannerText = localStorage.getItem(keyBannerText);
            const bannerImage = localStorage.getItem(keyBannerImage);
            const bannerHeight = localStorage.getItem(keyBannerHeight);
            const bannerWidth = localStorage.getItem(keyBannerWidth);
            const themeColor = localStorage.getItem(keyThemeColor);
            const textColor = localStorage.getItem(keyTextColor);
            const settings = {
                enabled: enabled === null ? true : enabled === 'true',
                leadSeconds: leadSeconds ? Math.max(1, parseInt(leadSeconds, 10)) : 5,
                highlightEnabled: highlightEnabled === null ? true : highlightEnabled === 'true',
                clockEnabled: clockEnabled === null ? false : clockEnabled === 'true',
                bannerEnabled: bannerEnabled === null ? false : bannerEnabled === 'true',
                bannerMode: bannerMode === 'image' ? 'image' : 'text',
                bannerText: bannerText ? String(bannerText) : '',
                bannerImage: bannerImage ? String(bannerImage) : '',
                bannerHeight: bannerHeight ? Math.max(40, parseInt(bannerHeight, 10)) : 160,
                bannerWidth: bannerWidth ? Math.max(160, parseInt(bannerWidth, 10)) : 220,
                themeColor: themeColor ? String(themeColor) : '#ffffff',
                textColor: textColor ? String(textColor) : '#111827'
            };
            nextAufgussSettings.set(String(planId), settings);
            return settings;
        }

        // Funktion: applyPlanSettings
        function applyPlanSettings(planId) {
            const settings = getPlanSettings(planId);
            const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
            const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
            const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
            const textColorInput = document.getElementById(`plan-text-color-${planId}`);
            if (enabledInput) enabledInput.checked = settings.enabled;
            if (leadInput) leadInput.value = settings.leadSeconds;
            if (highlightInput) highlightInput.checked = settings.highlightEnabled;
            if (clockInput) clockInput.checked = settings.clockEnabled;
            if (bannerInput) bannerInput.checked = settings.bannerEnabled;
            if (themeColorInput) themeColorInput.value = settings.themeColor || '#ffffff';
            if (textColorInput) textColorInput.value = settings.textColor || '#111827';
            const planTableScope = document.getElementById(`plan-table-wrap-${planId}`);
            if (planTableScope) {
                planTableScope.style.setProperty('--plan-accent-color', settings.themeColor || '#ffffff');
                planTableScope.style.setProperty('--plan-text-color', settings.textColor || '#111827');
            }
            const planClock = document.getElementById(`plan-clock-admin-${planId}`);
            if (planClock) {
                planClock.style.setProperty('--plan-accent-color', settings.themeColor || '#ffffff');
                planClock.style.setProperty('--plan-text-color', settings.textColor || '#111827');
            }
            toggleAdminClock(planId, settings.clockEnabled);
            updateNextAufgussControls(planId);
            updatePlanBannerStatus(planId);
        }

        // Funktion: isPlanBannerVideoPath
        function isPlanBannerVideoPath(path) {
            if (!path) return false;
            const cleanPath = String(path).split('?')[0].split('#')[0];
            return /\.(mp4|webm|ogg)$/i.test(cleanPath);
        }

        // Funktion: updatePlanBannerStatus
        function updatePlanBannerStatus(planId) {
            const statusEl = document.getElementById(`plan-banner-status-${planId}`);
            if (!statusEl) return;
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const hasBanner = settings
                && settings.bannerEnabled
                && ((settings.bannerMode === 'image' && settings.bannerImage)
                    || (settings.bannerMode !== 'image' && settings.bannerText));
            if (!hasBanner) {
                statusEl.classList.add('hidden');
                statusEl.textContent = '';
                return;
            }
            const label = settings.bannerMode === 'image'
                ? (isPlanBannerVideoPath(settings.bannerImage) ? 'Banner: Video' : 'Banner: Bild')
                : 'Banner: Text';
            statusEl.textContent = label;
            statusEl.classList.remove('hidden');
        }

// Funktion: savePlanSettings
// Speichert Plan-Einstellungen inkl. Uploads und UI-Status.
function savePlanSettings(planId, options = {}) {
            const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
            const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
            const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
            const textColorInput = document.getElementById(`plan-text-color-${planId}`);
            if (!enabledInput || !leadInput) return;
            const persist = !!options.persist;

            const enabled = enabledInput.checked;
            const leadSeconds = Math.max(1, parseInt(leadInput.value || '5', 10));
            const highlightEnabled = highlightInput ? highlightInput.checked : true;
            const clockEnabled = clockInput ? clockInput.checked : false;
            const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const bannerEnabled = bannerInput ? bannerInput.checked : (currentSettings ? !!currentSettings.bannerEnabled : false);
            const bannerMode = currentSettings ? String(currentSettings.bannerMode || 'text') : 'text';
            const bannerText = currentSettings ? String(currentSettings.bannerText || '') : '';
            const bannerImage = currentSettings ? String(currentSettings.bannerImage || '') : '';
            const bannerHeight = currentSettings ? Math.max(40, parseInt(currentSettings.bannerHeight || 160, 10)) : 160;
            const bannerWidth = currentSettings ? Math.max(160, parseInt(currentSettings.bannerWidth || 220, 10)) : 220;
            const themeColor = themeColorInput && themeColorInput.value
                ? themeColorInput.value
                : (currentSettings ? String(currentSettings.themeColor || '#ffffff') : '#ffffff');
            const textColor = textColorInput && textColorInput.value
                ? textColorInput.value
                : (currentSettings ? String(currentSettings.textColor || '#111827') : '#111827');
            leadInput.value = leadSeconds;

            localStorage.setItem(`nextAufgussEnabled_${planId}`, String(enabled));
            localStorage.setItem(`nextAufgussLeadSeconds_${planId}`, String(leadSeconds));
            localStorage.setItem(`nextAufgussHighlightEnabled_${planId}`, String(highlightEnabled));
            localStorage.setItem(`nextAufgussClockEnabled_${planId}`, String(clockEnabled));
            localStorage.setItem(`nextAufgussBannerEnabled_${planId}`, String(bannerEnabled));
            localStorage.setItem(`nextAufgussThemeColor_${planId}`, String(themeColor));
            localStorage.setItem(`nextAufgussTextColor_${planId}`, String(textColor));
            nextAufgussSettings.set(String(planId), {
                enabled,
                leadSeconds,
                highlightEnabled,
                clockEnabled,
                bannerEnabled,
                bannerMode,
                bannerText,
                bannerImage,
                bannerHeight,
                bannerWidth,
                themeColor,
                textColor
            });
            const planTableScope = document.getElementById(`plan-table-wrap-${planId}`);
            if (planTableScope) {
                planTableScope.style.setProperty('--plan-accent-color', themeColor);
                planTableScope.style.setProperty('--plan-text-color', textColor);
            }
            const planClock = document.getElementById(`plan-clock-admin-${planId}`);
            if (planClock) {
                planClock.style.setProperty('--plan-accent-color', themeColor);
                planClock.style.setProperty('--plan-text-color', textColor);
            }
            toggleAdminClock(planId, clockEnabled);
            updateNextAufgussControls(planId);
            updateNextAufgussRowHighlight();
            if (persist) {
                syncNextAufgussSettings(
                    planId,
                    enabled,
                    leadSeconds,
                    highlightEnabled,
                    clockEnabled,
                    bannerEnabled,
                    bannerMode,
                    bannerText,
                    bannerImage,
                    bannerHeight,
                    bannerWidth,
                    themeColor,
                    textColor
                );
                if (isSelectedPlan(planId)) {
                    notifyPublicPlanChange(planId);
                    notifyPublicReload();
                }
            }

            if (!enabled) {
                for (let i = nextAufgussQueue.length - 1; i >= 0; i -= 1) {
                    if (String(nextAufgussQueue[i].planId) === String(planId)) {
                        nextAufgussQueue.splice(i, 1);
                    }
                }
                if (String(nextAufgussActivePlanId) === String(planId)) {
                    closeNextAufgussPopup();
                }
            }
        }

        // Funktion: syncNextAufgussSettings
        function syncNextAufgussSettings(planId, enabled, leadSeconds, highlightEnabled, clockEnabled, bannerEnabled, bannerMode, bannerText, bannerImage, bannerHeight, bannerWidth, themeColor, textColor) {
            fetch('../../api/next_aufguss_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    plan_id: String(planId),
                    enabled: !!enabled,
                    lead_seconds: Number(leadSeconds),
                    highlight_enabled: !!highlightEnabled,
                    clock_enabled: !!clockEnabled,
                    banner_enabled: !!bannerEnabled,
                    banner_mode: String(bannerMode || 'text'),
                    banner_text: String(bannerText || ''),
                    banner_image: String(bannerImage || ''),
                    banner_height: Number(bannerHeight || 160),
                    banner_width: Number(bannerWidth || 220),
                    theme_color: String(themeColor || '#ffffff'),
                    text_color: String(textColor || '#111827')
                })
            }).catch(() => {});
        }

        // Funktion: persistBannerSettings
        async function persistBannerSettings(planId) {
            try {
                const response = await fetch(`../../api/next_aufguss_settings.php?plan_id=${encodeURIComponent(planId)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                const serverSettings = data && data.data ? data.data.settings : null;
                const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
                const payload = {
                    plan_id: String(planId),
                    enabled: serverSettings ? !!serverSettings.enabled : !!currentSettings.enabled,
                    lead_seconds: serverSettings ? Number(serverSettings.lead_seconds || 5) : Number(currentSettings.leadSeconds || 5),
                    highlight_enabled: serverSettings ? !!serverSettings.highlight_enabled : !!currentSettings.highlightEnabled,
                    clock_enabled: serverSettings ? !!serverSettings.clock_enabled : !!currentSettings.clockEnabled,
                    banner_enabled: !!currentSettings.bannerEnabled,
                    banner_mode: String(currentSettings.bannerMode || 'text'),
                    banner_text: String(currentSettings.bannerText || ''),
                    banner_image: String(currentSettings.bannerImage || ''),
                    banner_height: Number(currentSettings.bannerHeight || 160),
                    banner_width: Number(currentSettings.bannerWidth || 220),
                    theme_color: serverSettings && typeof serverSettings.theme_color === 'string'
                        ? serverSettings.theme_color
                        : String(currentSettings.themeColor || '#ffffff'),
                    text_color: serverSettings && typeof serverSettings.text_color === 'string'
                        ? serverSettings.text_color
                        : String(currentSettings.textColor || '#111827')
                };
                syncNextAufgussSettings(
                    planId,
                    payload.enabled,
                    payload.lead_seconds,
                    payload.highlight_enabled,
                    payload.clock_enabled,
                    payload.banner_enabled,
                    payload.banner_mode,
                    payload.banner_text,
                    payload.banner_image,
                    payload.banner_height,
                    payload.banner_width,
                    payload.theme_color,
                    payload.text_color
                );
                if (isSelectedPlan(planId)) {
                    notifyPublicPlanChange(planId);
                }
            } catch (error) {
                // keep local changes; server sync can be retried on save
            }
        }

        // Funktion: persistThemeColorSettings
        async function persistThemeColorSettings(planId) {
            try {
                const response = await fetch(`../../api/next_aufguss_settings.php?plan_id=${encodeURIComponent(planId)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                const serverSettings = data && data.data ? data.data.settings : null;
                const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
                const payload = {
                    plan_id: String(planId),
                    enabled: serverSettings ? !!serverSettings.enabled : !!currentSettings.enabled,
                    lead_seconds: serverSettings ? Number(serverSettings.lead_seconds || 5) : Number(currentSettings.leadSeconds || 5),
                    highlight_enabled: serverSettings ? !!serverSettings.highlight_enabled : !!currentSettings.highlightEnabled,
                    clock_enabled: serverSettings ? !!serverSettings.clock_enabled : !!currentSettings.clockEnabled,
                    banner_enabled: serverSettings ? !!serverSettings.banner_enabled : !!currentSettings.bannerEnabled,
                    banner_mode: serverSettings && typeof serverSettings.banner_mode === 'string'
                        ? serverSettings.banner_mode
                        : String(currentSettings.bannerMode || 'text'),
                    banner_text: serverSettings && typeof serverSettings.banner_text === 'string'
                        ? serverSettings.banner_text
                        : String(currentSettings.bannerText || ''),
                    banner_image: serverSettings && typeof serverSettings.banner_image === 'string'
                        ? serverSettings.banner_image
                        : String(currentSettings.bannerImage || ''),
                    banner_height: serverSettings ? Number(serverSettings.banner_height || 160) : Number(currentSettings.bannerHeight || 160),
                    banner_width: serverSettings ? Number(serverSettings.banner_width || 220) : Number(currentSettings.bannerWidth || 220),
                    theme_color: String(currentSettings.themeColor || '#ffffff'),
                    text_color: serverSettings && typeof serverSettings.text_color === 'string'
                        ? serverSettings.text_color
                        : String(currentSettings.textColor || '#111827')
                };
                syncNextAufgussSettings(
                    planId,
                    payload.enabled,
                    payload.lead_seconds,
                    payload.highlight_enabled,
                    payload.clock_enabled,
                    payload.banner_enabled,
                    payload.banner_mode,
                    payload.banner_text,
                    payload.banner_image,
                    payload.banner_height,
                    payload.banner_width,
                    payload.theme_color,
                    payload.text_color
                );
                if (isSelectedPlan(planId)) {
                    notifyPublicPlanChange(planId);
                }
            } catch (error) {
                // keep local changes; server sync can be retried on save
            }
        }

        // Funktion: saveAllPlanSettings
        async function saveAllPlanSettings(planId) {
            savePlanSettings(planId, { persist: true });
            await savePlanAdSettings(planId);
            if (window.showToast) {
                window.showToast('Gespeichert', 'success');
            }
        }

        // Funktion: updateNextAufgussControls
        function updateNextAufgussControls(planId) {
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
            const previewBtn = document.getElementById(`next-aufguss-preview-btn-${planId}`);
            const fields = document.getElementById(`next-aufguss-settings-fields-${planId}`);
            if (leadInput) leadInput.disabled = !settings.enabled;
            if (previewBtn) previewBtn.disabled = !settings.enabled;
            if (fields) fields.classList.toggle('opacity-50', !settings.enabled);
        }



        // Funktion: updateNextAufgussCountdown
        function updateNextAufgussCountdown() {
            const countdownEl = document.getElementById('next-aufguss-countdown');
            if (!countdownEl || !nextAufgussCountdownTarget) return;
            const diffMs = nextAufgussCountdownTarget - Date.now();
            const diffSec = Math.max(0, Math.ceil(diffMs / 1000));
            const nextText = diffSec === 0 ? 'Startet jetzt' : `${diffSec}`;
            if (countdownEl.textContent !== nextText) {
                countdownEl.textContent = nextText;
                countdownEl.classList.remove('is-pulsing');
                void countdownEl.offsetWidth;
                countdownEl.classList.add('is-pulsing');
            }
            if (diffMs <= 0 && !nextAufgussIsPreview) {
                closeNextAufgussPopup();
            }
        }

        // Funktion: closeNextAufgussPopup
        function closeNextAufgussPopup() {
            const overlay = document.getElementById('next-aufguss-overlay');
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            nextAufgussActive = false;
            nextAufgussActivePlanId = null;
            if (nextAufgussHideTimer) {
                clearTimeout(nextAufgussHideTimer);
                nextAufgussHideTimer = null;
            }
            if (nextAufgussCountdownTimer) {
                clearInterval(nextAufgussCountdownTimer);
                nextAufgussCountdownTimer = null;
            }
            nextAufgussCountdownTarget = null;
            showNextAufgussFromQueue();
        }

        // Funktion: showNextAufgussFromQueue
        function showNextAufgussFromQueue() {
            if (nextAufgussActive || nextAufgussQueue.length === 0) return;
            const next = nextAufgussQueue.shift();
            showNextAufgussPopup(next.id, next.startTs, next.planId);
        }

        // Funktion: showNextAufgussPopup
        async function showNextAufgussPopup(aufgussId, startTs, planId, previewData = null) {
            if (nextAufgussActive) {
                nextAufgussQueue.push({
                    id: aufgussId,
                    startTs,
                    planId
                });
                return;
            }
            nextAufgussActive = true;
            nextAufgussActivePlanId = planId;
            nextAufgussIsPreview = !!previewData;

            const overlay = document.getElementById('next-aufguss-overlay');
            const body = document.getElementById('next-aufguss-body');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            body.innerHTML = '<div class="text-sm text-gray-500">Laedt...</div>';
            applyNextAufgussTheme(nextAufgussSettings.get(String(planId)) || getPlanSettings(planId));

            if (previewData) {
                body.innerHTML = buildNextAufgussHtml(previewData);
            } else {
                try {
                    const response = await fetch(`next_aufguss.php?id=${aufgussId}`);
                    const result = await response.json();
                    if (!result.success) {
                        body.innerHTML = '<div class="text-sm text-red-600">Konnte Aufgussdaten nicht laden.</div>';
                    } else {
                        body.innerHTML = buildNextAufgussHtml(result.data);
                    }
                } catch (error) {
                    body.innerHTML = '<div class="text-sm text-red-600">Netzwerkfehler beim Laden.</div>';
                }
            }

            nextAufgussCountdownTarget = startTs;
            updateNextAufgussCountdown();
            nextAufgussCountdownTimer = setInterval(updateNextAufgussCountdown, 200);

            const hideDelay = previewData ? 20000 : Math.max(0, startTs - Date.now());
            nextAufgussHideTimer = setTimeout(() => {
                closeNextAufgussPopup();
            }, hideDelay);
        }

        // Funktion: previewNextAufgussPopup
        function previewNextAufgussPopup(planId) {
            savePlanSettings(planId);
            const firstRow = document.querySelector(`tr[data-aufguss-id][data-plan-id="${planId}"]`);
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const startTs = Date.now() + (settings.leadSeconds * 1000);
            if (firstRow) {
                const aufgussId = firstRow.getAttribute('data-aufguss-id');
                showNextAufgussPopup(aufgussId, startTs, planId);
                return;
            }

            const previewData = {
                name: 'Vorschau Aufguss',
                staerke: 3,
                aufgieser_name: 'Max Mustermann',
                sauna_name: 'Finnische Sauna',
                duftmittel_name: 'Eukalyptus',
                sauna_temperatur: 90,
                sauna_bild: '',
                mitarbeiter_bild: ''
            };
            showNextAufgussPopup('preview', startTs, planId, previewData);
        }

        // Funktion: getPlanAdSettings
        function getPlanAdSettings(planId) {
            const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const settings = {
                enabled: !!(enabledInput && enabledInput.checked),
                intervalMinutes: intervalInput ? Math.max(1, parseInt(intervalInput.value || '10', 10)) : 10,
                durationSeconds: durationInput ? Math.max(1, parseInt(durationInput.value || '10', 10)) : 10
            };
            planAdSettings.set(String(planId), settings);
            return settings;
        }

        // Funktion: applyPlanAdSettings
        function applyPlanAdSettings(planId) {
            getPlanAdSettings(planId);
            initPlanAdMedia(planId);
            updatePlanAdControls(planId);
            schedulePlanAd(planId);
        }

        // Funktion: savePlanAdSettings
        async function savePlanAdSettings(planId, includeFile = false, options = {}) {
            const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            if (!enabledInput || !intervalInput || !durationInput) return;

            const enabled = enabledInput.checked;
            const intervalMinutes = Math.max(1, parseInt(intervalInput.value || '10', 10));
            const durationSeconds = Math.max(1, parseInt(durationInput.value || '10', 10));
            intervalInput.value = intervalMinutes;
            durationInput.value = durationSeconds;

            const formData = new FormData();
            formData.append('plan_id', planId);
            formData.append('enabled', enabled ? '1' : '0');
            formData.append('interval_minutes', String(intervalMinutes));
            formData.append('duration_seconds', String(durationSeconds));
            if (includeFile && fileInput && fileInput.files && fileInput.files[0]) {
                formData.append('media', fileInput.files[0]);
            }

            try {
                const response = await fetch('../updates/update_plan_ad.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern der Werbung.');
                    return;
                }
                if (result.data && Object.prototype.hasOwnProperty.call(result.data, 'media_path')) {
                    if (result.data.media_path) {
                        setPlanAdMediaFromServer(planId, result.data.media_path, result.data.media_type, result.data.media_name);
                    } else {
                        clearPlanAdMedia(planId);
                    }
                }
                planAdSettings.set(String(planId), {
                    enabled,
                    intervalMinutes,
                    durationSeconds
                });
                updatePlanAdControls(planId);
                if (!enabled) {
                    hidePlanAd(planId);
                }
                if (includeFile) {
                    removeAdFile(planId);
                }
                schedulePlanAd(planId);
                if (options.notify !== false && isSelectedPlan(planId)) {
                    notifyPublicPlanChange(planId);
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern der Werbung.');
            }
        }

        // Funktion: selectPlanBackground
        async function selectPlanBackground(planId) {
            const select = document.getElementById(`plan-background-select-${planId}`);
            if (!select) return;
            const backgroundPath = select.value;

            try {
                const response = await fetch('../updates/update_plan_background.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        background_path: backgroundPath
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern des Hintergrundbilds.');
                    return;
                }
                location.reload();
            } catch (error) {
                alert('Netzwerkfehler beim Speichern des Hintergrundbilds.');
            }
        }

        // Funktion: selectPlanAdMedia
        async function selectPlanAdMedia(planId) {
            const select = document.getElementById(`plan-ad-select-${planId}`);
            if (!select) return;
            const option = select.options[select.selectedIndex];
            const mediaPath = select.value;
            const mediaType = option ? option.getAttribute('data-type') : '';

            if (!mediaPath) {
                return;
            }

            try {
                const response = await fetch('../updates/update_plan_ad_select.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId,
                        media_path: mediaPath,
                        media_type: mediaType
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Speichern der Werbung.');
                    return;
                }
                if (result.data && result.data.media_path) {
                    setPlanAdMediaFromServer(planId, result.data.media_path, result.data.media_type, result.data.media_name);
                }
            } catch (error) {
                alert('Netzwerkfehler beim Speichern der Werbung.');
            }
        }

        // Funktion: uploadPlanAdMedia
        function uploadPlanAdMedia(planId) {
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Bitte eine Datei auswaehlen.');
                return;
            }
            savePlanAdSettings(planId, true);
        }

        // Funktion: deletePlanAdMedia
        async function deletePlanAdMedia(planId, planName) {
            if (!confirm(`Werbung von "${planName}" Löschen?`)) {
                return;
            }

            try {
                const response = await fetch('../deletes/delete_plan_ad_media.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan_id: planId
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.error || 'Fehler beim Löschen der Werbung.');
                    return;
                }

                const enabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
                if (enabledInput) enabledInput.checked = false;

                clearPlanAdMedia(planId);
                hidePlanAd(planId);
                getPlanAdSettings(planId);
                updatePlanAdControls(planId);
                schedulePlanAd(planId);
                if (isSelectedPlan(planId)) {
                    notifyPublicPlanChange(planId);
                }
            } catch (error) {
                alert('Netzwerkfehler beim Löschen der Werbung.');
            }
        }


        // Funktion: updatePlanAdControls
        function updatePlanAdControls(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            const intervalInput = document.getElementById(`plan-ad-interval-${planId}`);
            const durationInput = document.getElementById(`plan-ad-duration-${planId}`);
            const previewBtn = document.getElementById(`plan-ad-preview-btn-${planId}`);
            const fileInput = document.getElementById(`plan-ad-file-${planId}`);
            const uploadBtn = document.getElementById(`plan-ad-upload-btn-${planId}`);
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            const fields = document.getElementById(`plan-ad-settings-fields-${planId}`);
            if (intervalInput) intervalInput.disabled = !settings.enabled;
            if (durationInput) durationInput.disabled = !settings.enabled;
            if (previewBtn) previewBtn.disabled = !settings.enabled;
            if (fileInput) fileInput.disabled = !settings.enabled;
            if (uploadBtn) uploadBtn.disabled = !settings.enabled;
            if (deleteBtn) deleteBtn.disabled = !settings.enabled || !planAdMedia.has(String(planId));
            if (fields) fields.classList.toggle('opacity-50', !settings.enabled);
        }

        // Funktion: getPlanAdElements
        function getPlanAdElements(planId) {
            const adWrap = document.getElementById(`plan-ad-wrap-${planId}`);
            const mediaWrap = document.getElementById(`plan-ad-media-${planId}`);
            const tableBlock = document.getElementById(`plan-table-wrap-${planId}`);
            return {
                adWrap,
                mediaWrap,
                tableBlock
            };
        }

        // Funktion: initPlanAdMedia
        function initPlanAdMedia(planId) {
            if (planAdMedia.has(String(planId))) return;
            const fields = document.getElementById(`plan-ad-settings-fields-${planId}`);
            if (!fields) return;
            const path = fields.dataset.adPath || '';
            const type = fields.dataset.adType || '';
            if (!path) return;
            const name = path.split('/').pop();
            setPlanAdMediaFromServer(planId, path, type, name);
        }

        // Funktion: renderPlanAdPreview
        function renderPlanAdPreview(planId, media) {
            const preview = document.getElementById(`plan-ad-preview-${planId}`);
            if (!preview) return;
            if (!media || !media.url) {
                preview.innerHTML = '<div class="flex items-center justify-center h-48 text-sm text-gray-500 bg-gray-100">Keine Werbung vorhanden</div>';
                return;
            }
            const isVideo = media.type && (media.type.startsWith('video/') || media.type === 'video');
            if (isVideo) {
                preview.innerHTML = `<video class="w-full h-48 object-contain" autoplay muted playsinline loop><source src="${media.url}"></video>`;
            } else {
                preview.innerHTML = `<img src="${media.url}" alt="Werbung" class="w-full h-48 object-contain">`;
            }
        }

        // Funktion: setPlanAdMediaFromServer
        function setPlanAdMediaFromServer(planId, path, type, name) {
            const media = planAdMedia.get(String(planId));
            if (media && media.url && media.url.startsWith('blob:')) {
                URL.revokeObjectURL(media.url);
            }
            const url = `../../uploads/${path}`;
            planAdMedia.set(String(planId), {
                url,
                type,
                name: name || path.split('/').pop()
            });
            const fileInfo = document.getElementById(`plan-ad-file-info-${planId}`);
            if (fileInfo) {
                fileInfo.textContent = `Aktuelle Datei: ${name || path.split('/').pop()}`;
                fileInfo.classList.remove('text-gray-500');
                fileInfo.classList.add('text-gray-600');
            }
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            if (deleteBtn) deleteBtn.disabled = false;
            renderPlanAdPreview(planId, planAdMedia.get(String(planId)));
            schedulePlanAd(planId);
        }

        // Funktion: clearPlanAdMedia
        function clearPlanAdMedia(planId) {
            const media = planAdMedia.get(String(planId));
            if (media && media.url && media.url.startsWith('blob:')) {
                URL.revokeObjectURL(media.url);
            }
            planAdMedia.delete(String(planId));
            renderPlanAdPreview(planId, null);
            const fileInfo = document.getElementById(`plan-ad-file-info-${planId}`);
            if (fileInfo) {
                fileInfo.textContent = 'Keine Datei gespeichert.';
                fileInfo.classList.remove('text-gray-600');
                fileInfo.classList.add('text-gray-500');
            }
            const deleteBtn = document.getElementById(`plan-ad-delete-btn-${planId}`);
            if (deleteBtn) deleteBtn.disabled = true;
        }

        // Funktion: showPlanAd
        function showPlanAd(planId, durationSeconds, isPreview = false) {
            const media = planAdMedia.get(String(planId));
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            if (!media) {
                if (isPreview) {
                    alert('Bitte zuerst ein Bild oder Video auswaehlen.');
                }
                return;
            }
            if (!isPreview && !settings.enabled) return;

            const {
                adWrap,
                mediaWrap,
                tableBlock
            } = getPlanAdElements(planId);
            if (!adWrap || !mediaWrap || !tableBlock) return;

            const isVideo = media.type && (media.type.startsWith('video/') || media.type === 'video');
            if (isVideo) {
                mediaWrap.innerHTML = `<video src="${media.url}" class="plan-ad-asset rounded-lg" autoplay muted playsinline loop></video>`;
            } else {
                mediaWrap.innerHTML = `<img src="${media.url}" alt="Werbung" class="plan-ad-asset rounded-lg">`;
            }

            tableBlock.classList.add('is-hidden');
            adWrap.classList.remove('hidden');
            requestAnimationFrame(() => adWrap.classList.add('is-visible'));

            if (planAdHideTimers.has(String(planId))) {
                clearTimeout(planAdHideTimers.get(String(planId)));
            }
            const hideTimer = setTimeout(() => {
                hidePlanAd(planId);
            }, Math.max(1, durationSeconds) * 1000);
            planAdHideTimers.set(String(planId), hideTimer);
        }

        // Funktion: hidePlanAd
        function hidePlanAd(planId) {
            const {
                adWrap,
                tableBlock
            } = getPlanAdElements(planId);
            if (!adWrap || !tableBlock) return;
            adWrap.classList.remove('is-visible');
            setTimeout(() => adWrap.classList.add('hidden'), 300);
            requestAnimationFrame(() => tableBlock.classList.remove('is-hidden'));
        }

        // Funktion: schedulePlanAd
        function schedulePlanAd(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            if (planAdIntervals.has(String(planId))) {
                clearInterval(planAdIntervals.get(String(planId)));
                planAdIntervals.delete(String(planId));
            }
            if (!settings.enabled) return;
            if (!planAdMedia.has(String(planId))) return;

            const intervalMs = Math.max(1, settings.intervalMinutes) * 60 * 1000;
            const timer = setInterval(() => {
                showPlanAd(planId, settings.durationSeconds);
            }, intervalMs);
            planAdIntervals.set(String(planId), timer);
        }

        // Funktion: previewPlanAd
        function previewPlanAd(planId) {
            const settings = planAdSettings.get(String(planId)) || getPlanAdSettings(planId);
            const planCard = document.getElementById(`plan-${planId}`);
            if (planCard) {
                const headerOffset = 80;
                const rect = planCard.getBoundingClientRect();
                const targetY = rect.top + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: Math.max(0, targetY),
                    behavior: 'smooth'
                });
            }
            showPlanAd(planId, settings.durationSeconds, true);
        }

        // Funktion: initSaunaTemperatureSync
        function initSaunaTemperatureSync() {
            const selects = document.querySelectorAll('select[id^="sauna-select-"]');
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    const planId = select.id.replace('sauna-select-', '');
                    const tempInput = document.getElementById(`sauna-temperatur-${planId}`);
                    if (!tempInput) return;
                    const option = select.options[select.selectedIndex];
                    const temp = option ? option.getAttribute('data-temperatur') : '';
                    tempInput.value = temp ? temp : '';
                });
            });
        }

        // Initialisierung nach DOM-Laden
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-plan-id]').forEach(container => {
                const planId = container.getAttribute('data-plan-id');
                if (!planId) return;
                const hasSettings = container.querySelector(`[id^="next-aufguss-enabled-"]`);
                if (!hasSettings) return;

                applyPlanSettings(planId);
                applyPlanAdSettings(planId);
                const enabledInput = document.getElementById(`next-aufguss-enabled-${planId}`);
                const leadInput = document.getElementById(`next-aufguss-lead-${planId}`);
                const highlightInput = document.getElementById(`next-aufguss-highlight-enabled-${planId}`);
                const clockInput = document.getElementById(`next-aufguss-clock-enabled-${planId}`);
                const bannerInput = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
                const themeColorInput = document.getElementById(`next-aufguss-theme-color-${planId}`);
                const textColorInput = document.getElementById(`plan-text-color-${planId}`);
                const planForm = document.querySelector(`#form-${planId} form`);
                const adEnabledInput = document.getElementById(`plan-ad-enabled-${planId}`);
                const adIntervalInput = document.getElementById(`plan-ad-interval-${planId}`);
                const adDurationInput = document.getElementById(`plan-ad-duration-${planId}`);
                const adFileInput = document.getElementById(`plan-ad-file-${planId}`);
                if (enabledInput) {
                    enabledInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (leadInput) {
                    leadInput.addEventListener('change', () => savePlanSettings(planId, { persist: true }));
                }
                if (highlightInput) {
                    highlightInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (clockInput) {
                    clockInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (bannerInput) {
                    bannerInput.addEventListener('change', () => {
                        savePlanSettings(planId);
                        updatePlanBannerStatus(planId);
                        persistBannerSettings(planId);
                    });
                }
                if (themeColorInput) {
                    themeColorInput.addEventListener('change', () => {
                        savePlanSettings(planId);
                        persistThemeColorSettings(planId);
                    });
                }
                if (textColorInput) {
                    textColorInput.addEventListener('change', () => savePlanSettings(planId));
                }
                if (planForm) {
                    planForm.addEventListener('submit', () => savePlanSettings(planId));
                }
                if (adEnabledInput) {
                    adEnabledInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adIntervalInput) {
                    adIntervalInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adDurationInput) {
                    adDurationInput.addEventListener('change', () => savePlanAdSettings(planId));
                }
                if (adFileInput) {
                    adFileInput.addEventListener('change', () => updateAdFileName(planId));
                }
            });

            initPlanSelectButtons();
            initSaunaTemperatureSync();
            startAdminClockTicker();
            updateNextAufgussRowHighlight();
            setInterval(() => {
                const rows = document.querySelectorAll('tr[data-aufguss-id]');
                const now = Date.now();

                rows.forEach(row => {
                    const aufgussId = row.getAttribute('data-aufguss-id');
                    if (!aufgussId) return;
                    const planId = row.getAttribute('data-plan-id');
                    if (!planId) return;
                    const key = `${planId}:${aufgussId}`;
                    if (nextAufgussShown.has(key)) return;

                    const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
                    if (!settings.enabled) return;

                    const timeText = row.querySelector('.zeit-cell .display-mode span')?.textContent?.trim();
                    const startTime = parseStartTime(timeText);
                    if (!startTime) return;

                    const diff = startTime.getTime() - now;
                    if (diff <= (settings.leadSeconds * 1000) && diff > 0) {
                        nextAufgussShown.add(key);
                        showNextAufgussPopup(aufgussId, startTime.getTime(), planId);
                    }
                });

                updateNextAufgussRowHighlight();
            }, 1000);
        });

        // Funktion: initPlanSelectButtons
        function initPlanSelectButtons() {
            const buttons = document.querySelectorAll('[data-plan-select]');
            if (!buttons.length) return;

            const storageKey = 'aufgussplanSelectedPlan';
            const stored = localStorage.getItem(storageKey);

            const setActive = (planId) => {
                buttons.forEach(button => {
                    const isActive = button.getAttribute('data-plan-select') === String(planId);
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            if (stored) {
                setActive(stored);
            }

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    const planId = button.getAttribute('data-plan-select');
                    if (!planId) return;
                    setActive(planId);
                    localStorage.setItem(storageKey, String(planId));
                    notifyPublicPlanChange(planId);
                    if (window.showToast) {
                        const planName = button.getAttribute('data-plan-name') || `Plan ${planId}`;
                        window.showToast(`Ausgewählt: ${planName}`, 'success');
                    }
                });
            });
        }

        // Funktion: updateAdminClockElement
        function updateAdminClockElement(clockEl) {
            if (!clockEl) return;
            const now = new Date();
            const timeEl = clockEl.querySelector('.plan-clock-admin-time');
            const dateEl = clockEl.querySelector('.plan-clock-admin-date');
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            }
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
        }

        // Funktion: toggleAdminClock
        function toggleAdminClock(planId, enabled) {
            const clockEl = document.getElementById(`plan-clock-admin-${planId}`);
            if (!clockEl) return;
            clockEl.classList.toggle('hidden', !enabled);
            if (enabled) {
                updateAdminClockElement(clockEl);
            }
        }

        // Funktion: startAdminClockTicker
        function startAdminClockTicker() {
            if (adminClockTimer) return;
            adminClockTimer = setInterval(() => {
                document.querySelectorAll('.plan-clock-admin:not(.hidden)').forEach(clockEl => {
                    updateAdminClockElement(clockEl);
                });
            }, 1000);
        }

        // Funktion: openPlanBannerModal
        function openPlanBannerModal(planId) {
            const modal = document.getElementById('planBannerModal');
            if (!modal) return;
            const settings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const planIdInput = document.getElementById('planBannerPlanId');
            const enabledInput = document.getElementById('planBannerEnabled');
            const modeTextInput = document.getElementById('planBannerModeText');
            const modeImageInput = document.getElementById('planBannerModeImage');
            const textInput = document.getElementById('planBannerText');
            const imageInput = document.getElementById('planBannerImage');
            const imageSelect = document.getElementById('planBannerImageSelect');

            if (planIdInput) planIdInput.value = String(planId);
            if (enabledInput) enabledInput.checked = !!settings.bannerEnabled;
            if (modeTextInput) modeTextInput.checked = settings.bannerMode !== 'image';
            if (modeImageInput) modeImageInput.checked = settings.bannerMode === 'image';
            if (textInput) textInput.value = settings.bannerText || '';
            if (imageInput) imageInput.value = settings.bannerImage || '';
            if (imageSelect) imageSelect.value = settings.bannerImage || '';

            modal.classList.remove('hidden');
        }

        // Funktion: closePlanBannerModal
        function closePlanBannerModal() {
            const modal = document.getElementById('planBannerModal');
            if (!modal) return;
            modal.classList.add('hidden');
        }

        // Funktion: savePlanBannerSettings
        function savePlanBannerSettings() {
            const planId = document.getElementById('planBannerPlanId')?.value;
            if (!planId) return;
            const enabledInput = document.getElementById('planBannerEnabled');
            const modeTextInput = document.getElementById('planBannerModeText');
            const textInput = document.getElementById('planBannerText');
            const imageInput = document.getElementById('planBannerImage');

            const bannerEnabled = !!(enabledInput && enabledInput.checked);
            const bannerMode = modeTextInput && modeTextInput.checked ? 'text' : 'image';
            const bannerText = textInput ? textInput.value.trimEnd() : '';
            const bannerImage = imageInput ? imageInput.value.trim() : '';
            const bannerToggle = document.getElementById(`next-aufguss-banner-enabled-${planId}`);
            if (bannerToggle) bannerToggle.checked = bannerEnabled;
            const currentSettings = nextAufgussSettings.get(String(planId)) || getPlanSettings(planId);
            const bannerWidth = currentSettings ? Math.max(160, parseInt(currentSettings.bannerWidth || 220, 10)) : 220;
            const bannerHeight = currentSettings ? Math.max(40, parseInt(currentSettings.bannerHeight || 160, 10)) : 160;

            localStorage.setItem(`nextAufgussBannerEnabled_${planId}`, String(bannerEnabled));
            localStorage.setItem(`nextAufgussBannerMode_${planId}`, String(bannerMode));
            localStorage.setItem(`nextAufgussBannerText_${planId}`, String(bannerText));
            localStorage.setItem(`nextAufgussBannerImage_${planId}`, String(bannerImage));
            localStorage.setItem(`nextAufgussBannerWidth_${planId}`, String(bannerWidth));
            localStorage.setItem(`nextAufgussBannerHeight_${planId}`, String(bannerHeight));

            nextAufgussSettings.set(String(planId), {
                ...currentSettings,
                bannerEnabled,
                bannerMode,
                bannerText,
                bannerImage,
                bannerWidth,
                bannerHeight
            });
            updatePlanBannerStatus(planId);

            const updatedSettings = nextAufgussSettings.get(String(planId)) || currentSettings;
            syncNextAufgussSettings(
                planId,
                updatedSettings.enabled,
                updatedSettings.leadSeconds,
                updatedSettings.highlightEnabled,
                updatedSettings.clockEnabled,
                bannerEnabled,
                bannerMode,
                bannerText,
                bannerImage,
                bannerHeight,
                bannerWidth,
                updatedSettings.themeColor
            );
            if (isSelectedPlan(planId)) {
                notifyPublicPlanChange(planId);
            }
            closePlanBannerModal();
        }

        // Funktion: selectPlanBannerImage
        function selectPlanBannerImage() {
            const select = document.getElementById('planBannerImageSelect');
            const input = document.getElementById('planBannerImage');
            if (!select || !input) return;
            input.value = select.value;
        }

        // Funktion: updatePlanBannerFileName
        function updatePlanBannerFileName() {
            const input = document.getElementById('planBannerFile');
            const filenameDiv = document.getElementById('plan-banner-filename');
            const filenameText = document.getElementById('plan-banner-filename-text');
            if (!input || !filenameDiv || !filenameText) return;
            if (!input.files || !input.files[0]) {
                filenameDiv.classList.add('hidden');
                filenameText.textContent = '';
                return;
            }
            filenameText.textContent = input.files[0].name;
            filenameDiv.classList.remove('hidden');
        }

        // Funktion: removePlanBannerFile
        function removePlanBannerFile() {
            const input = document.getElementById('planBannerFile');
            const filenameDiv = document.getElementById('plan-banner-filename');
            const filenameText = document.getElementById('plan-banner-filename-text');
            if (input) input.value = '';
            if (filenameDiv) filenameDiv.classList.add('hidden');
            if (filenameText) filenameText.textContent = '';
        }

        // Funktion: uploadPlanBannerImage
        async function uploadPlanBannerImage() {
            const input = document.getElementById('planBannerFile');
            if (!input || !input.files || !input.files[0]) {
                alert('Bitte eine Datei auswaehlen.');
                return;
            }
            const formData = new FormData();
            formData.append('banner', input.files[0]);
            try {
                const response = await fetch('../updates/upload_banner_image.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data || !data.success) {
                    throw new Error(data && data.error ? data.error : 'Upload fehlgeschlagen');
                }
                const path = data.data && data.data.path ? data.data.path : '';
                if (path) {
                    const imageInput = document.getElementById('planBannerImage');
                    const imageSelect = document.getElementById('planBannerImageSelect');
                    if (imageInput) imageInput.value = path;
                    if (imageSelect) imageSelect.value = path;
                }
                removePlanBannerFile();
            } catch (error) {
                alert(error && error.message ? error.message : 'Upload fehlgeschlagen');
            }
        }

        // Funktion: isSelectedPlan
        function isSelectedPlan(planId) {
            const selected = localStorage.getItem('aufgussplanSelectedPlan');
            if (!selected) return false;
            return String(selected) === String(planId);
        }

        // Funktion: notifyPublicPlanChange
        function notifyPublicPlanChange(planId) {
            localStorage.setItem('aufgussplanPlanChanged', String(Date.now()));
            if (!planId) return;
            fetch('../../api/selected_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: String(planId) })
            }).catch(error => {
                console.warn('Failed to sync selected plan:', error);
            });
        }

        // Funktion: notifyPublicReload
        function notifyPublicReload() {
            localStorage.setItem('aufgussplanForceReload', String(Date.now()));
        }
