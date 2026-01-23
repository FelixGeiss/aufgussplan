const screensApiUrl = '../../api/bildschirme.php';
const plansApiUrl = '../../api/plaene.php';
const uploadUrl = '../uploads/upload_screen_media.php';
const globalAdUploadUrl = '../uploads/upload_global_ad.php';
const screenCount = 5;
const mediaOptions = window.ScreenMediaOptions || { screens: [], backgrounds: [], ads: [] };
let globalAd = {
    path: '',
    type: '',
    enabled: false,
    order: [],
    displaySeconds: 10,
    pauseSeconds: 10
};

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

// JSON-Request mit einfachem Error-Handling.
function fetchJson(url, options) {
    return fetch(url, options).then(response => response.ok ? response.json() : null);
}

// Extrahiert Pläene aus API-Payload.
function extractPlans(payload) {
    if (payload && payload.data && Array.isArray(payload.data.Pläene)) {
        return payload.data.Pläene;
    }
    if (payload && Array.isArray(payload.Pläene)) {
        return payload.Pläene;
    }
    return [];
}

// Extrahiert Bildschirmdaten aus API-Payload.
function extractScreens(payload) {
    const screens = {};
    const list = payload && payload.data && Array.isArray(payload.data.screens)
        ? payload.data.screens
        : [];
    list.forEach(screen => {
        if (screen && screen.id) {
            screens[String(screen.id)] = screen;
        }
    });
    return screens;
}

// Baut Select-Optionen fuer Pläene.
function buildPlanOptions(plans, selectedId) {
    if (!plans.length) {
        return '<option value="">Keine Pläne</option>';
    }
    return [
        '<option value="">Plan wählen</option>',
        ...plans.map(plan => {
            const id = String(plan.id);
            const label = escapeHtml(plan.name || `Plan ${id}`);
            const selected = String(selectedId) === id ? ' selected' : '';
            return `<option value="${id}"${selected}>${label}</option>`;
        })
    ].join('');
}

// Prueft, ob der Pfad ein Video ist.
function isVideoPath(path) {
    if (!path) return false;
    const clean = String(path).split('?')[0].split('#')[0];
    return /\.(mp4|webm|ogg)$/i.test(clean);
}

// Baut Select-Optionen fuer Dateien.
function buildFileOptions(paths, selectedPath, placeholder) {
    const list = Array.isArray(paths) ? [...paths] : [];
    if (selectedPath && !list.includes(selectedPath)) {
        list.unshift(selectedPath);
    }
    const options = [
        `<option value="">${escapeHtml(placeholder)}</option>`,
        ...list.map(path => {
            const selected = selectedPath === path ? ' selected' : '';
            const label = escapeHtml(path.split('/').pop() || path);
            return `<option value="${escapeHtml(path)}"${selected}>${label}</option>`;
        })
    ];
    return options.join('');
}

// Erstellt Bild-Preview-HTML.
function buildPreview(path) {
    if (!path) {
        return '<div class="text-xs text-gray-500">Kein Bild hinterlegt.</div>';
    }
    const safe = escapeHtml(`../../uploads/${path}`);
    return `<img src="${safe}" alt="Preview" class="w-full max-h-40 object-contain rounded border">`;
}

// Erstellt Bild-/Video-Preview-HTML.
function buildMediaPreview(path) {
    if (!path) {
        return '<div class="text-xs text-gray-500">Keine Datei hinterlegt.</div>';
    }
    const safe = escapeHtml(`../../uploads/${path}`);
    if (isVideoPath(path)) {
        return `<video src="${safe}" class="w-full max-h-40 object-contain rounded border" muted autoplay loop playsinline></video>`;
    }
    return `<img src="${safe}" alt="Preview" class="w-full max-h-40 object-contain rounded border">`;
}

// Rendert den Block fuer globale Werbung.
function buildGlobalAdCard() {
    const options = buildFileOptions(mediaOptions.ads, globalAd.path, '-- Werbung wählen --');
    const orderOptions = buildScreenOrderOptions(globalAd.order);
    const orderButtons = buildScreenOrderButtons(globalAd.order);
    return `
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" data-global-ad-card>
            <div class="text-lg font-semibold mb-3">Globale Werbung</div>
            <div class="mb-4">
                <div class="flex flex-col h-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Aktiv</label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="global_ad_enabled" class="sr-only peer" ${globalAd.enabled ? 'checked' : ''}>
                        <span class="h-4 w-4 rounded border border-gray-300 bg-white flex items-center justify-center text-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600">
                            <svg class="h-3 w-3 hidden peer-checked:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.408 0Z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                        <span>Werbung einblenden</span>
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="flex flex-col justify-between h-full">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Reihenfolge</label>
                    <div class="flex flex-wrap items-center gap-3" style="min-height:46px;" data-order-buttons>
                        ${orderButtons}
                    </div>
                    <select name="global_ad_order" multiple size="${Math.max(3, screenCount)}" class="sr-only" aria-hidden="true" tabindex="-1">
                        ${orderOptions}
                    </select>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-600" data-selected-order>
                        ${renderSelectedOrderBadges(globalAd.order)}
                    </div>
                    <div class="text-xs text-gray-500 mt-2">Mehrfachauswahl: Klick auf Eintr&auml;ge.</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Werbung hochladen</label>
                            <input type="file" name="global_ad_upload" data-kind="global-ad" class="w-full text-sm" accept="image/*,video/*">
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <button type="button" data-action="save-global-ad" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Speichern</button>
                    </div>
                </div>
                <div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Anzeigedauer (Sek.)</label>
                            <input type="number" name="global_ad_display_seconds" min="1" value="${Number(globalAd.displaySeconds) || 10}" class="w-full border rounded px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Pause (Sek.)</label>
                            <input type="number" name="global_ad_pause_seconds" min="0" value="${Number(globalAd.pauseSeconds) || 10}" class="w-full border rounded px-3 py-2">
                        </div>
                    </div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Werbung ausw&auml;hlen</label>
                    <select name="global_ad_select" class="w-full border rounded px-3 py-2">
                        ${options}
                    </select>
                    <div class="mt-2" data-preview="global-ad">
                        ${buildMediaPreview(globalAd.path)}
                    </div>
                </div>
            </div>
        </div>`;
}

// Baut Optionen fuer die Bildschirm-Reihenfolge.
function buildScreenOrderOptions(order) {
    const selected = Array.isArray(order) ? order.map(value => Number(value)) : [];
    const uniqueSelected = [];
    selected.forEach(value => {
        if (!Number.isFinite(value)) return;
        if (value < 1 || value > screenCount) return;
        if (!uniqueSelected.includes(value)) {
            uniqueSelected.push(value);
        }
    });
    const all = Array.from({ length: screenCount }, (_, i) => i + 1);
    const remaining = all.filter(value => !uniqueSelected.includes(value));
    const list = uniqueSelected.concat(remaining);
    return list.map(value => {
        const isSelected = uniqueSelected.includes(value);
        return `<option value="${value}"${isSelected ? ' selected' : ''}>Bildschirm ${value}</option>`;
    }).join('');
}


// Buttons fuer die Bildschirm-Reihenfolge.
function buildScreenOrderButtons(order) {
    const selected = Array.isArray(order) ? order.map(value => Number(value)) : [];
    const uniqueSelected = [];
    selected.forEach(value => {
        if (!Number.isFinite(value)) return;
        if (value < 1 || value > screenCount) return;
        if (!uniqueSelected.includes(value)) {
            uniqueSelected.push(value);
        }
    });
    const all = Array.from({ length: screenCount }, (_, i) => i + 1);
    const remaining = all.filter(value => !uniqueSelected.includes(value));
    const list = uniqueSelected.concat(remaining);
    return list.map(value => {
        const isSelected = uniqueSelected.includes(value);
        const activeClass = isSelected ? ' is-active' : '';
        const pressed = isSelected ? 'true' : 'false';
        return `<button type="button" class="plan-select-btn${activeClass}" data-order-value="${value}" aria-pressed="${pressed}">Bildschirm ${value}</button>`;
    }).join('');
}

// Erstellt Badges fuer die ausgewaehlte Reihenfolge.
function renderSelectedOrderBadges(order) {
    const list = Array.isArray(order) ? order : [];
    if (!list.length) {
        return '<span class="text-xs text-gray-400">Keine Auswahl</span>';
    }
    return list
        .map(value => Number(value))
        .filter(value => Number.isFinite(value) && value > 0)
        .map(value => `<span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">Bildschirm ${value}</span>`)
        .join('');
}

function sanitizeOrderList(order) {
    const list = Array.isArray(order) ? order.map(Number) : [];
    const clean = [];
    list.forEach(value => {
        if (!Number.isFinite(value)) return;
        if (value < 1 || value > screenCount) return;
        if (clean.includes(value)) return;
        clean.push(value);
    });
    return clean;
}

function rebuildGlobalAdOrderSelect(select, order) {
    if (!select) return;
    select.innerHTML = buildScreenOrderOptions(order);
}

const adDirectionValues = ['left', 'right', 'up', 'down'];
const adSideOptions = [
    { side: 'left', entryLabel: 'von links', entryArrow: '&rarr;', exitLabel: 'nach links', exitArrow: '&larr;' },
    { side: 'right', entryLabel: 'von rechts', entryArrow: '&larr;', exitLabel: 'nach rechts', exitArrow: '&rarr;' },
    { side: 'up', entryLabel: 'von oben', entryArrow: '&darr;', exitLabel: 'nach oben', exitArrow: '&uarr;' },
    { side: 'down', entryLabel: 'von unten', entryArrow: '&uarr;', exitLabel: 'nach unten', exitArrow: '&darr;' }
];

function normalizeAdSide(direction) {
    return adDirectionValues.includes(direction) ? direction : null;
}

function getEntrySideFromDirection(direction) {
    switch (direction) {
        case 'left':
            return 'right';
        case 'right':
            return 'left';
        case 'up':
            return 'down';
        case 'down':
            return 'up';
        default:
            return 'left';
    }
}

function buildAdSideButtons(type, selectedSide) {
    return adSideOptions.map(option => {
        const isSelected = option.side === selectedSide;
        const activeClass = isSelected ? ' is-active' : '';
        const pressed = isSelected ? 'true' : 'false';
        const label = type === 'entry'
            ? `${option.entryArrow} ${option.entryLabel}`
            : `${option.exitArrow} ${option.exitLabel}`;
        const dataAttr = type === 'entry' ? 'data-ad-entry' : 'data-ad-exit';
        return `<button type="button" class="plan-select-btn${activeClass}" ${dataAttr}="${option.side}" aria-pressed="${pressed}">${label}</button>`;
    }).join('');
}

function updateAdSideButtons(card, type, selectedSide) {
    const selector = type === 'entry' ? '[data-ad-entry]' : '[data-ad-exit]';
    const attribute = type === 'entry' ? 'data-ad-entry' : 'data-ad-exit';
    card.querySelectorAll(selector).forEach(button => {
        const isActive = button.getAttribute(attribute) === selectedSide;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function setAdEntrySide(card, side) {
    const normalized = normalizeAdSide(side) || 'left';
    const input = card.querySelector('[name="ad_entry"]');
    if (input) {
        input.value = normalized;
    }
    updateAdSideButtons(card, 'entry', normalized);
}

function setAdExitSide(card, side) {
    const normalized = normalizeAdSide(side) || 'right';
    const input = card.querySelector('[name="ad_exit"]');
    if (input) {
        input.value = normalized;
    }
    updateAdSideButtons(card, 'exit', normalized);
}

// Rendert eine Bildschirm-Karte.
function buildScreenCard(screenId, screen, plans) {
    const mode = screen && screen.mode === 'image' ? 'image' : 'plan';
    const planId = screen && screen.plan_id ? String(screen.plan_id) : '';
    const imagePath = screen && screen.image_path ? String(screen.image_path) : '';
    const backgroundPath = screen && screen.background_path ? String(screen.background_path) : '';
    const legacyDirection = normalizeAdSide(screen && screen.ad_direction ? String(screen.ad_direction) : '') || 'right';
    const entrySide = normalizeAdSide(screen && screen.ad_entry ? String(screen.ad_entry) : '') || getEntrySideFromDirection(legacyDirection);
    const exitSide = normalizeAdSide(screen && screen.ad_exit ? String(screen.ad_exit) : '') || legacyDirection;
    const imageOptions = buildFileOptions(mediaOptions.screens, imagePath, '-- Bild wählen --');
    const backgroundOptions = buildFileOptions(mediaOptions.backgrounds, backgroundPath, '-- Hintergrund wählen --');

    return `
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" data-screen-card data-screen-id="${screenId}" data-image-path="${escapeHtml(imagePath)}" data-background-path="${escapeHtml(backgroundPath)}">
            <div class="flex items-center justify-between mb-3">
                <div class="text-lg font-semibold font-display">Bildschirm ${screenId}</div>
                <a href="../../bildschirm_${screenId}.php" class="text-sm text-blue-600 hover:underline" target="_blank" rel="noopener">Öffnen</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Anzeige</label>
                    <select name="mode" class="w-full border rounded px-3 py-2">
                        <option value="plan"${mode === 'plan' ? ' selected' : ''}>Plan</option>
                        <option value="image"${mode === 'image' ? ' selected' : ''}>Bild</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Plan</label>
                    <select name="plan_id" class="w-full border rounded px-3 py-2">
                        ${buildPlanOptions(plans, planId)}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Bild (Anzeige)</label>
                    <select name="image_select" class="w-full border rounded px-3 py-2 mb-2">
                        ${imageOptions}
                    </select>
                    <input type="file" name="image_upload" data-kind="image" class="w-full text-sm">
                    <div class="mt-2" data-preview="image">
                        ${buildPreview(imagePath)}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Hintergrund</label>
                    <select name="background_select" class="w-full border rounded px-3 py-2 mb-2">
                        ${backgroundOptions}
                    </select>
                    <input type="file" name="background_upload" data-kind="background" class="w-full text-sm">
                    <div class="mt-2" data-preview="background">
                        ${buildMediaPreview(backgroundPath)}
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Werbung rein</label>
                            <div class="grid grid-cols-2 gap-2" data-ad-entry-buttons>
                                ${buildAdSideButtons('entry', entrySide)}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Werbung raus</label>
                            <div class="grid grid-cols-2 gap-2" data-ad-exit-buttons>
                                ${buildAdSideButtons('exit', exitSide)}
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="ad_entry" value="${entrySide}">
                    <input type="hidden" name="ad_exit" value="${exitSide}">
                </div>
            </div>
        </div>
    `;
}

// Aktiviert/Deaktiviert Inputs je Modus.
function updateCardState(card) {
    const mode = card.querySelector('[name="mode"]')?.value || 'plan';
    const planSelect = card.querySelector('[name="plan_id"]');
    const imageInput = card.querySelector('input[data-kind="image"]');
    const imageSelect = card.querySelector('[name="image_select"]');

    const planDisabled = mode !== 'plan';
    const imageDisabled = mode !== 'image';

    if (planSelect) {
        planSelect.disabled = planDisabled;
        planSelect.classList.toggle('opacity-60', planDisabled);
    }
    if (imageSelect) {
        imageSelect.disabled = imageDisabled;
        imageSelect.classList.toggle('opacity-60', imageDisabled);
    }
    if (imageInput) {
        imageInput.disabled = imageDisabled;
        imageInput.classList.toggle('opacity-60', imageDisabled);
    }
}

// Rendert alle Bildschirm-Karten.
function renderScreens(plans, screens) {
    const root = document.getElementById('screen-list');
    if (!root) return;

    const cards = [];
    for (let i = 1; i <= screenCount; i++) {
        const screen = screens[String(i)] || { id: i };
        cards.push(buildScreenCard(i, screen, plans));
    }
    root.innerHTML = cards.join('');

    root.querySelectorAll('[data-screen-card]').forEach(card => {
        updateCardState(card);
    });
}

// Rendert den Global-Ad-Bereich.
function renderGlobalAd() {
    const root = document.getElementById('global-ad-card');
    if (!root) return;
    root.innerHTML = buildGlobalAdCard();
    bindGlobalAdEvents();
    updateSelectedOrderBadges();
}

// Liest Kartenwerte aus dem DOM.
function getCardConfig(card) {
    const mode = card.querySelector('[name="mode"]')?.value || 'plan';
    const planId = card.querySelector('[name="plan_id"]')?.value || '';
    const adEntry = card.querySelector('[name="ad_entry"]')?.value || 'left';
    const adExit = card.querySelector('[name="ad_exit"]')?.value || 'right';
    const imagePath = card.dataset.imagePath || '';
    const backgroundPath = card.dataset.backgroundPath || '';

    return {
        mode,
        planId,
        adEntry,
        adExit,
        imagePath,
        backgroundPath
    };
}

// Aktualisiert die Vorschau der Karte.
function updatePreview(card, kind, path) {
    const preview = card.querySelector(`[data-preview="${kind}"]`);
    if (!preview) return;
    preview.innerHTML = kind === 'background' ? buildMediaPreview(path) : buildPreview(path);
}

// Aktualisiert die Global-Ad-Vorschau.
function updateGlobalAdPreview(path) {
    const root = document.getElementById('global-ad-card');
    if (!root) return;
    const preview = root.querySelector('[data-preview="global-ad"]');
    if (!preview) return;
    preview.innerHTML = buildMediaPreview(path);
}

// Setzt den Werbe-Typ anhand des Pfads.
function updateGlobalAdType(path) {
    if (!path) {
        globalAd.type = '';
        return;
    }
    globalAd.type = isVideoPath(path) ? 'video' : 'image';
}

// Speichert eine Bildschirm-Konfiguration.
function saveScreenConfig(card, options = {}) {
    const { silent } = options;
    const screenId = Number(card.dataset.screenId || 0);
    if (!screenId) return Promise.resolve({ success: false, message: 'Ungueltige Bildschirm-ID' });

    const config = getCardConfig(card);
    const payload = {
        screen_id: screenId,
        mode: config.mode,
        plan_id: config.mode === 'plan' ? config.planId : null,
        image_path: config.mode === 'image' ? config.imagePath : null,
        background_path: config.backgroundPath || null,
        ad_entry: config.adEntry,
        ad_exit: config.adExit
    };

    return fetchJson(screensApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(result => {
            if (result && result.success) {
                if (!silent) {
                    notify('Bildschirm gespeichert.', 'success');
                }
                return { success: true };
            } else {
                const message = result && result.message ? result.message : 'Speichern fehlgeschlagen.';
                if (!silent) {
                    notify(message, 'error');
                }
                return { success: false, message };
            }
        })
        .catch(() => {
            const message = 'Netzwerkfehler beim Speichern.';
            if (!silent) {
                notify(message, 'error');
            }
            return { success: false, message };
        });
}

function handleSave(card) {
    saveScreenConfig(card);
}

function saveAllScreens() {
    const root = document.getElementById('screen-list');
    if (!root) return Promise.resolve({ success: true });
    const cards = Array.from(root.querySelectorAll('[data-screen-card]'));
    const requests = cards.map(card => saveScreenConfig(card, { silent: true }));
    return Promise.all(requests).then(results => {
        const failures = results.filter(result => !result.success);
        return {
            success: failures.length === 0,
            failures
        };
    });
}

// Verarbeitet Uploads fuer Bild/Hintergrund.
function handleUpload(card, input) {
    const file = input.files && input.files[0];
    if (!file) return;

    const screenId = Number(card.dataset.screenId || 0);
    const kind = input.dataset.kind || 'image';

    const formData = new FormData();
    formData.append('screen_id', String(screenId));
    formData.append('kind', kind);
    formData.append('bild', file);

    fetchJson(uploadUrl, {
        method: 'POST',
        body: formData
    })
        .then(result => {
            if (!result || !result.success || !result.data) {
                notify(result && result.error ? result.error : 'Upload fehlgeschlagen.', 'error');
                return;
            }
            const path = result.data.path || '';
            if (kind === 'background') {
                card.dataset.backgroundPath = path;
                if (!mediaOptions.backgrounds.includes(path)) {
                    mediaOptions.backgrounds.push(path);
                }
            } else {
                card.dataset.imagePath = path;
                if (!mediaOptions.screens.includes(path)) {
                    mediaOptions.screens.push(path);
                }
            }
            updatePreview(card, kind === 'background' ? 'background' : 'image', path);
            const selectName = kind === 'background' ? 'background_select' : 'image_select';
            const select = card.querySelector(`[name="${selectName}"]`);
            if (select) {
                const options = buildFileOptions(
                    kind === 'background' ? mediaOptions.backgrounds : mediaOptions.screens,
                    path,
                    kind === 'background' ? '-- Hintergrund wählen --' : '-- Bild wählen --'
                );
                select.innerHTML = options;
                select.value = path;
            }
            notify('Bild hochgeladen.', 'success');
        })
        .catch(() => notify('Netzwerkfehler beim Upload.', 'error'))
        .finally(() => {
            input.value = '';
        });
}

// Verarbeitet Uploads fuer globale Werbung.
function handleGlobalAdUpload(input) {
    const file = input.files && input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('werbung', file);

    fetchJson(globalAdUploadUrl, {
        method: 'POST',
        body: formData
    })
        .then(result => {
            if (!result || !result.success || !result.data) {
                notify(result && result.error ? result.error : 'Upload fehlgeschlagen.', 'error');
                return;
            }
            const path = result.data.path || '';
            globalAd.path = path;
            globalAd.type = result.data.type || (isVideoPath(path) ? 'video' : 'image');
            if (path && !mediaOptions.ads.includes(path)) {
                mediaOptions.ads.push(path);
            }
            renderGlobalAd();
            notify('Werbung hochgeladen.', 'success');
        })
        .catch(() => notify('Netzwerkfehler beim Upload.', 'error'))
        .finally(() => {
            input.value = '';
        });
}

// Speichert die globale Werbung.
function handleGlobalAdSave() {
    const payload = {
        global_ad_path: globalAd.path || null,
        global_ad_type: globalAd.path ? (globalAd.type || null) : null,
        global_ad_enabled: !!globalAd.enabled,
        global_ad_order: Array.isArray(globalAd.order) ? globalAd.order : [],
        global_ad_display_seconds: Number(globalAd.displaySeconds) || 10,
        global_ad_pause_seconds: Number(globalAd.pauseSeconds) || 10
    };

    saveAllScreens()
        .then(screenResult => {
            return fetchJson(screensApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(result => ({ result, screenResult }));
        })
        .then(({ result, screenResult }) => {
            const screenFailed = screenResult && !screenResult.success;
            if (result && result.success && result.data && result.data.global_ad) {
                const serverAd = result.data.global_ad || {};
                const order = Array.isArray(serverAd.order) && serverAd.order.length
                    ? serverAd.order
                    : (Array.isArray(globalAd.order) ? globalAd.order : []);
                globalAd = {
                    path: serverAd.path || globalAd.path || '',
                    type: serverAd.type || globalAd.type || '',
                    enabled: typeof serverAd.enabled === 'boolean' ? serverAd.enabled : !!globalAd.enabled,
                    order,
                    displaySeconds: Number(serverAd.display_seconds ?? globalAd.displaySeconds) || 10,
                    pauseSeconds: Number(serverAd.pause_seconds ?? globalAd.pauseSeconds) || 10
                };
                renderGlobalAd();
                if (screenFailed) {
                    notify('Werbung gespeichert, aber einige Bildschirme konnten nicht gespeichert werden.', 'error');
                } else {
                    notify('Einstellungen gespeichert.', 'success');
                }
            } else {
                notify(result && result.message ? result.message : 'Speichern fehlgeschlagen.', 'error');
            }
        })
        .catch(() => notify('Netzwerkfehler beim Speichern.', 'error'));
}

// Bindet Events fuer Bildschirmkarten.
function bindEvents() {
    const root = document.getElementById('screen-list');
    if (!root) return;

    root.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const card = target.closest('[data-screen-card]');
        if (!card) return;

        if (target.name === 'mode') {
            updateCardState(card);
        }
        if (target.name === 'image_select') {
            const path = target.value || '';
            card.dataset.imagePath = path;
            updatePreview(card, 'image', path);
        }
        if (target.name === 'background_select') {
            const path = target.value || '';
            card.dataset.backgroundPath = path;
            updatePreview(card, 'background', path);
        }
        if (target.type === 'file') {
            handleUpload(card, target);
        }
    });

    root.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const adEntryButton = target.closest('[data-ad-entry]');
        const adExitButton = target.closest('[data-ad-exit]');
        if (adEntryButton || adExitButton) {
            const card = target.closest('[data-screen-card]');
            if (!card) return;
            if (adEntryButton) {
                const side = adEntryButton.getAttribute('data-ad-entry');
                if (side) {
                    setAdEntrySide(card, side);
                }
            } else if (adExitButton) {
                const side = adExitButton.getAttribute('data-ad-exit');
                if (side) {
                    setAdExitSide(card, side);
                }
            }
            return;
        }
    });
}

// Bindet Events fuer globale Werbung.
function bindGlobalAdEvents() {
    const root = document.getElementById('global-ad-card');
    if (!root) return;
    if (root.dataset.bound === 'true') return;
    root.dataset.bound = 'true';

    root.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.name === 'global_ad_select') {
            const path = target.value || '';
            globalAd.path = path;
            updateGlobalAdType(path);
            updateGlobalAdPreview(path);
        }
        if (target.name === 'global_ad_enabled') {
            globalAd.enabled = !!target.checked;
        }
        if (target.name === 'global_ad_display_seconds') {
            globalAd.displaySeconds = Number(target.value) || 10;
        }
        if (target.name === 'global_ad_pause_seconds') {
            globalAd.pauseSeconds = Number(target.value) || 10;
        }
        if (target.name === 'global_ad_order') {
            globalAd.order = sanitizeOrderList(getSelectOrder(target));
            updateSelectedOrderBadges();
            updateOrderButtons(target);
        }
        if (target.type === 'file') {
            handleGlobalAdUpload(target);
        }
    });

    root.addEventListener('mousedown', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const option = target.closest('option');
        if (!option) return;
        const select = option.closest('select');
        if (!select || select.name !== 'global_ad_order') return;
        event.preventDefault();
        option.selected = !option.selected;
        globalAd.order = getSelectOrder(select);
        updateSelectedOrderBadges();
    });

    root.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.closest('[data-order-buttons]')) {
            const button = target.closest('button[data-order-value]');
            const select = root.querySelector('[name="global_ad_order"]');
            if (!button || !select) return;
            const value = Number(button.getAttribute('data-order-value'));
            if (!Number.isFinite(value)) return;
            const nextOrder = sanitizeOrderList(globalAd.order);
            const index = nextOrder.indexOf(value);
            if (index >= 0) {
                nextOrder.splice(index, 1);
            } else {
                nextOrder.push(value);
            }
            globalAd.order = nextOrder;
            rebuildGlobalAdOrderSelect(select, globalAd.order);
            updateSelectedOrderBadges();
            updateOrderButtons(select);
            return;
        }
        if (!target.matches('[data-action="save-global-ad"]')) return;
        handleGlobalAdSave();
    });
}

// Initialisiert Daten und UI fuer die Bildschirme.
function initScreens() {
    Promise.all([fetchJson(plansApiUrl), fetchJson(screensApiUrl)])
        .then(([plansPayload, screensPayload]) => {
            const plans = extractPlans(plansPayload);
            const screens = extractScreens(screensPayload);
            const global = extractGlobalAd(screensPayload);
            if (global) {
                const fallbackOrder = Array.from({ length: screenCount }, (_, i) => i + 1);
                const order = Array.isArray(global.order) && global.order.length ? global.order : fallbackOrder;
                globalAd = {
                    path: global.path || '',
                    type: global.type || '',
                    enabled: !!global.enabled,
                    order,
                    displaySeconds: Number(global.display_seconds) || 10,
                    pauseSeconds: Number(global.pause_seconds) || 10
                };
            }
            renderScreens(plans, screens);
            renderGlobalAd();
            bindEvents();
        })
        .catch(() => {
            notify('Fehler beim Laden der Bildschirme.', 'error');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    initScreens();
});

// Extrahiert globale Werbung aus Payload.
function extractGlobalAd(payload) {
    if (payload && payload.data && payload.data.global_ad) {
        return payload.data.global_ad;
    }
    if (payload && payload.global_ad) {
        return payload.global_ad;
    }
    return null;
}

// Aktualisiert die Reihenfolge-Badges.
function updateSelectedOrderBadges() {
    const root = document.getElementById('global-ad-card');
    if (!root) return;
    const container = root.querySelector('[data-selected-order]');
    if (!container) return;
    container.innerHTML = renderSelectedOrderBadges(globalAd.order);
}

// Rendert die Reihenfolge-Buttons aus dem Select.
function updateOrderButtons(select) {
    const root = document.getElementById('global-ad-card');
    if (!root || !select) return;
    const container = root.querySelector('[data-order-buttons]');
    if (!container) return;
    container.innerHTML = Array.from(select.options)
        .map(option => {
            const isSelected = option.selected;
            const activeClass = isSelected ? ' is-active' : '';
            const pressed = isSelected ? 'true' : 'false';
            const label = escapeHtml(option.textContent || '');
            return `<button type="button" class="plan-select-btn${activeClass}" data-order-value="${escapeHtml(option.value)}" aria-pressed="${pressed}">${label}</button>`;
        })
        .join('');
}


// Liest die Reihenfolge aus dem Mehrfach-Select.
function getSelectOrder(select) {
    return Array.from(select.options)
        .filter(option => option.selected)
        .map(option => Number(option.value))
        .filter(value => Number.isFinite(value) && value > 0);
}



