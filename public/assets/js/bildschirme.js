const screensApiUrl = '../api/bildschirme.php';
const plansApiUrl = '../api/plaene.php';
const uploadUrl = 'upload_screen_media.php';
const screenCount = 5;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function notify(message, type = 'info') {
    if (window.AdminUtils && typeof window.AdminUtils.showToast === 'function') {
        window.AdminUtils.showToast(message, type);
        return;
    }
    alert(message);
}

function fetchJson(url, options) {
    return fetch(url, options).then(response => response.ok ? response.json() : null);
}

function extractPlans(payload) {
    if (payload && payload.data && Array.isArray(payload.data.plaene)) {
        return payload.data.plaene;
    }
    if (payload && Array.isArray(payload.plaene)) {
        return payload.plaene;
    }
    return [];
}

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

function buildPlanOptions(plans, selectedId) {
    if (!plans.length) {
        return '<option value="">Keine Plaene</option>';
    }
    return [
        '<option value="">Plan waehlen</option>',
        ...plans.map(plan => {
            const id = String(plan.id);
            const label = escapeHtml(plan.name || `Plan ${id}`);
            const selected = String(selectedId) === id ? ' selected' : '';
            return `<option value="${id}"${selected}>${label}</option>`;
        })
    ].join('');
}

function buildPreview(path) {
    if (!path) {
        return '<div class="text-xs text-gray-500">Kein Bild hinterlegt.</div>';
    }
    const safe = escapeHtml(`../uploads/${path}`);
    return `<img src="${safe}" alt="Preview" class="w-full max-h-40 object-contain rounded border">`;
}

function buildScreenCard(screenId, screen, plans) {
    const mode = screen && screen.mode === 'image' ? 'image' : 'plan';
    const planId = screen && screen.plan_id ? String(screen.plan_id) : '';
    const imagePath = screen && screen.image_path ? String(screen.image_path) : '';
    const backgroundPath = screen && screen.background_path ? String(screen.background_path) : '';

    return `
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" data-screen-card data-screen-id="${screenId}" data-image-path="${escapeHtml(imagePath)}" data-background-path="${escapeHtml(backgroundPath)}">
            <div class="flex items-center justify-between mb-3">
                <div class="text-lg font-semibold">Bildschirm ${screenId}</div>
                <a href="../bildschirm_${screenId}.php" class="text-sm text-blue-600 hover:underline" target="_blank" rel="noopener">Oeffnen</a>
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
                    <input type="file" name="image_upload" data-kind="image" class="w-full text-sm">
                    <div class="mt-2" data-preview="image">
                        ${buildPreview(imagePath)}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Hintergrund</label>
                    <input type="file" name="background_upload" data-kind="background" class="w-full text-sm">
                    <div class="mt-2" data-preview="background">
                        ${buildPreview(backgroundPath)}
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button type="button" data-action="save" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Speichern</button>
                <span class="text-xs text-gray-500">Aenderungen am Modus oder Plan bitte speichern.</span>
            </div>
        </div>
    `;
}

function updateCardState(card) {
    const mode = card.querySelector('[name="mode"]')?.value || 'plan';
    const planSelect = card.querySelector('[name="plan_id"]');
    const imageInput = card.querySelector('input[data-kind="image"]');

    const planDisabled = mode !== 'plan';
    const imageDisabled = mode !== 'image';

    if (planSelect) {
        planSelect.disabled = planDisabled;
        planSelect.classList.toggle('opacity-60', planDisabled);
    }
    if (imageInput) {
        imageInput.disabled = imageDisabled;
        imageInput.classList.toggle('opacity-60', imageDisabled);
    }
}

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

function getCardConfig(card) {
    const mode = card.querySelector('[name="mode"]')?.value || 'plan';
    const planId = card.querySelector('[name="plan_id"]')?.value || '';
    const imagePath = card.dataset.imagePath || '';
    const backgroundPath = card.dataset.backgroundPath || '';

    return {
        mode,
        planId,
        imagePath,
        backgroundPath
    };
}

function updatePreview(card, kind, path) {
    const preview = card.querySelector(`[data-preview="${kind}"]`);
    if (!preview) return;
    preview.innerHTML = buildPreview(path);
}

function handleSave(card) {
    const screenId = Number(card.dataset.screenId || 0);
    if (!screenId) return;

    const config = getCardConfig(card);
    const payload = {
        screen_id: screenId,
        mode: config.mode,
        plan_id: config.mode === 'plan' ? config.planId : null,
        image_path: config.mode === 'image' ? config.imagePath : null,
        background_path: config.backgroundPath || null
    };

    fetchJson(screensApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(result => {
            if (result && result.success) {
                notify('Bildschirm gespeichert.', 'success');
            } else {
                notify(result && result.message ? result.message : 'Speichern fehlgeschlagen.', 'error');
            }
        })
        .catch(() => notify('Netzwerkfehler beim Speichern.', 'error'));
}

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
            } else {
                card.dataset.imagePath = path;
            }
            updatePreview(card, kind === 'background' ? 'background' : 'image', path);
            notify('Bild hochgeladen.', 'success');
        })
        .catch(() => notify('Netzwerkfehler beim Upload.', 'error'))
        .finally(() => {
            input.value = '';
        });
}

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
        if (target.type === 'file') {
            handleUpload(card, target);
        }
    });

    root.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (!target.matches('[data-action="save"]')) return;
        const card = target.closest('[data-screen-card]');
        if (!card) return;
        handleSave(card);
    });
}

function initScreens() {
    Promise.all([fetchJson(plansApiUrl), fetchJson(screensApiUrl)])
        .then(([plansPayload, screensPayload]) => {
            const plans = extractPlans(plansPayload);
            const screens = extractScreens(screensPayload);
            renderScreens(plans, screens);
            bindEvents();
        })
        .catch(() => {
            notify('Fehler beim Laden der Bildschirme.', 'error');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    initScreens();
});
