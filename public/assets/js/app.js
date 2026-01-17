/**
 * HAUPT-JAVASCRIPT FR DIE FFENTLICHE AUFGUSSPLAN-ANZEIGE
 *
 * Dieses Script luft auf der ffentlichen index.php-Seite und ist zustndig fuer:
 * - Automatisches Laden von Aufgussdaten ber AJAX
 * - Regelmige Aktualisierung (alle 30 Sekunden)
 * - Darstellung der Daten im TV-freundlichen Format
 * - Vollbild-Modus fuer TV-Bildschirme
 * - Timer-Funktionalitt fuer laufende Aufgüsse
 *
 * Als Anfnger solltest du wissen:
 * - Dies ist modernes ES6+ JavaScript mit Promises und Fetch API
 * - Die Daten kommen ber AJAX (Asynchronous JavaScript And XML)
 * - setInterval() fhrt Code regelmig aus
 * - Event Listener reagieren auf Benutzeraktionen
 * - Arrow Functions (=>) sind eine moderne Schreibweise
 *
 * Architektur: Browser ue AJAX ue PHP-API ue Datenbank
 */

// Warten bis das DOM (HTML) vollstndig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    console.log('Aufgussplan App geladen');

    /**
     * AUTOMATISCHE AKTUALISIERUNG
     *
     * Alle 30 Sekunden werden die Daten neu geladen.
     * Das ist wichtig fuer TV-Bildschirme, die 24/7 laufen.
     * setInterval fhrt die Funktion wiederholt aus.
     */
    /**
     * ANFANGSLADUNG
     *
     * Beim ersten Laden der Seite Daten sofort laden (nicht warten).
     */
    initScreenConfig().then(startAufgussApp);

    /**
     * VOLLBILD-MODUS FR TV-ANZEIGE
     *
     * Tastenkombination F11 oder Strg+F fuer Vollbild.
     * Wichtig fuer TV-Bildschirme ohne Maus/Tastatur.
     */
    document.addEventListener('keydown', function(e) {
        // Prfen auf F11 oder Strg+F
        if (e.key === 'F11' || (e.ctrlKey && e.key === 'f')) {
            // Standard-Verhalten verhindern (Browser-Vollbild)
            e.preventDefault();
            // Eigenen Vollbild-Modus aktivieren
            toggleFullscreen();
        }
    });

});

let lastAufguesse = [];
let lastPläene = [];
let selectedPlanId = null;
const selectedPlansStorageKey = 'aufgussplanSelectedPlan';
const planChangeStorageKey = 'aufgussplanPlanChanged';
const selectedPlanApiUrl = 'api/selected_plan.php';
const screenConfigApiUrl = 'api/bildschirme.php';
const nextAufgussSettingsApiUrl = 'api/next_aufguss_settings.php';
let serverSelectedPlanId = null;
let serverPlanSyncInFlight = false;
let serverSelectedPlanUpdatedAt = null;
let serverNextAufgussSettings = new Map();
let serverPlanSyncTimer = null;
let planAdIntervalId = null;
let planAdHideTimeout = null;
let activeAdPlanId = null;
let lastPlanAdShownAt = 0;
let lastPlanAdKey = null;
let planAdConfigKey = null;
let currentPlanAdIntervalMs = 0;
let planAdStartTimeout = null;
let planAdAnchorTimeMs = 0;
let videoAutoplayUnlocked = false;
let planAdPreloadVideo = null;
const placeholderMitarbeiter = 'assets/placeholders/Platzhalter_Mitarbeiter.svg';
const placeholderSauna = 'assets/placeholders/Platzhalter_Sauna.svg';
let planAdOffscreenContainer = null;
let planClockTimer = null;
let aufgussById = new Map();
let nextAufgussTimer = null;
let nextAufgussShown = new Set();
let nextAufgussHideTimer = null;
let nextAufgussCountdownTimer = null;
let nextAufgussCountdownTarget = null;
let nextAufgussActive = false;
let nextAufgussActivePlanId = null;
let nextAufgussPlanId = null;
let statsLogged = new Set();
const statsLoggedStorageKey = 'aufgussplanStatsLogged';
let screenId = 0;
let screenMode = null;
let screenImagePath = '';
let screenBackgroundPath = '';
let screenGlobalAdPath = '';
let screenGlobalAdType = '';
let screenGlobalAdSignature = '';
let screenAdDirection = 'right';
let screenConfigUpdatedAt = null;
let screenPlanLocked = false;
let screenConfigSyncTimer = null;
let globalAdTimer = null;
let globalAdServerOffsetMs = 0;
let globalAdConfig = {
    enabled: false,
    path: '',
    type: '',
    order: [],
    displaySeconds: 10,
    pauseSeconds: 10,
    rotationStartedAt: null
};
let globalAdVisible = false;
let globalAdCurrentPath = '';
let globalAdCurrentType = '';
let planAdExitTimer = null;
let globalAdExitTimer = null;

// Startet die Initialisierung der Anzeige.
function startAufgussApp() {
    loadPlans();
    loadAufgussplan();
    initPlanChangeListener();
    initServerPlanSync();
    initNextAufgussTimer();
    loadStatsLogged();
    initVideoAutoplayUnlock();
    initGlobalAdTicker();
}

// Liest die Bildschirm-ID aus dem DOM.
function getScreenId() {
    const container = document.getElementById('aufgussplan');
    const raw = container && container.dataset ? container.dataset.screenId : null;
    const parsed = raw ? Number(raw) : 0;
    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

// Laedt die Bildschirm-Konfiguration vom Server.
function initScreenConfig() {
    screenId = getScreenId();
    if (!screenId) {
        return Promise.resolve();
    }

    return fetchScreenConfig()
        .then(result => {
            applyScreenConfig(result ? result.screen : null, result ? result.globalAd : null, result ? result.serverTime : null);
            initScreenConfigSync();
        })
        .catch(error => {
            console.warn('Screen config load failed:', error);
        });
}

// Startet das Polling fuer Bildschirm-Config.
function initScreenConfigSync() {
    if (!screenId) return;
    if (screenConfigSyncTimer) {
        clearInterval(screenConfigSyncTimer);
    }
    screenConfigSyncTimer = setInterval(() => {
        fetchScreenConfig()
            .then(result => {
                const screen = result ? result.screen : null;
                const globalAd = result ? result.globalAd : null;
                const globalSignature = buildGlobalAdSignature(globalAd);
                const screenUpdated = !!(screen && screen.updated_at && screen.updated_at !== screenConfigUpdatedAt);
                const globalUpdated = globalSignature !== screenGlobalAdSignature;
                if (!screenUpdated && !globalUpdated) {
                    return;
                }
                applyScreenConfig(screen, globalAd, result ? result.serverTime : null);
                renderFilteredAufguesse();
            })
            .catch(() => {});
    }, 5000);
}

// Holt die Bildschirm-Config per API.
function fetchScreenConfig() {
    return fetch(`${screenConfigApiUrl}?screen_id=${encodeURIComponent(screenId)}`, { cache: 'no-store' })
        .then(response => response.ok ? response.json() : null)
        .then(data => {
            const payload = data && data.data ? data.data : null;
            return payload ? {
                screen: payload.screen || null,
                globalAd: payload.global_ad || null,
                serverTime: payload.server_time || null
            } : null;
        });
}

// Uebernimmt die Bildschirm-Config in den State.
function applyScreenConfig(screen, globalAd, serverTime) {
    const wasLocked = screenPlanLocked;
    if (!screen) return;
    screenConfigUpdatedAt = screen.updated_at || null;
    screenMode = screen.mode === 'image' ? 'image' : 'plan';
    screenImagePath = screen.image_path ? String(screen.image_path) : '';
    screenBackgroundPath = screen.background_path ? String(screen.background_path) : '';
    screenGlobalAdPath = globalAd && globalAd.path ? String(globalAd.path) : '';
    screenGlobalAdType = globalAd && globalAd.type ? String(globalAd.type) : '';
    screenGlobalAdSignature = buildGlobalAdSignature(globalAd);
    screenAdDirection = screen.ad_direction ? String(screen.ad_direction) : 'right';
    updateGlobalAdConfig(globalAd, serverTime);

    if (screenMode === 'image') {
        screenPlanLocked = true;
        selectedPlanId = null;
        if (serverPlanSyncTimer) {
            clearInterval(serverPlanSyncTimer);
            serverPlanSyncTimer = null;
        }
        return;
    }

    if (screen.plan_id) {
        screenPlanLocked = true;
        selectedPlanId = String(screen.plan_id);
        saveSelectedPlan();
        if (serverPlanSyncTimer) {
            clearInterval(serverPlanSyncTimer);
            serverPlanSyncTimer = null;
        }
        return;
    }

    screenPlanLocked = false;
    if (!screenPlanLocked && wasLocked) {
        initServerPlanSync();
    }
}

// Normalisiert Pfade fuer Uploads.
function normalizeScreenAssetPath(path) {
    if (!path) return '';
    if (String(path).startsWith('uploads/')) {
        return String(path);
    }
    return `uploads/${path}`;
}

// Rendert den Bildmodus fuer den Bildschirm.
function renderScreenImage() {
    const container = document.getElementById('aufgussplan');
    if (!container) return;
    const imagePath = screenImagePath ? normalizeScreenAssetPath(screenImagePath) : '';
    const backgroundPath = screenBackgroundPath ? normalizeScreenAssetPath(screenBackgroundPath) : '';
    applyPlanBackground(backgroundPath);

    if (!imagePath) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">Kein Bildschirm-Bild hinterlegt.</p>';
        return;
    }

    const safePath = escapeHtml(imagePath);
    container.innerHTML = `
        <div class="w-full h-screen flex items-center justify-center">
            <img src="${safePath}" alt="Bildschirm" class="h-full w-auto max-w-full object-contain object-center">
        </div>
    `;
}

// Laedt Pläene und aktualisiert die Anzeige.
function loadPlans() {
    fetch('api/plaene.php')
        .then(response => response.json())
        .then(data => {
            const Pläene = extractPläene(data);
            lastPläene = Array.isArray(Pläene) ? Pläene : [];
            ensureSelectedPlan(lastPläene);
            renderFilteredAufguesse();
        })
        .catch(error => {
            console.error('Fehler beim Laden der Pläene:', error);
        });
}

// Rendert Aufgüsse basierend auf Filter.
function renderFilteredAufguesse() {
    if (screenMode === 'image') {
        renderScreenImage();
        return;
    }
    const filtered = filterAufguesseByPlan(lastAufguesse);
    renderPlanView(selectedPlanId, lastPläene, filtered);
}


// Filtert Aufgüsse nach Plan.
function filterAufguesseByPlan(aufguesse) {
    if (!Array.isArray(aufguesse) || !selectedPlanId) {
        return aufguesse;
    }

    return aufguesse.filter(aufguss => String(aufguss.plan_id) === selectedPlanId);
}

// Liest die Plan-Auswahl aus LocalStorage.
function restoreSelectedPlans() {
    if (screenPlanLocked) {
        return;
    }
    try {
        const stored = localStorage.getItem(selectedPlansStorageKey);
        selectedPlanId = stored ? String(stored) : null;
    } catch (error) {
        selectedPlanId = null;
    }
}

// Speichert die Plan-Auswahl in LocalStorage.
function saveSelectedPlan() {
    if (!selectedPlanId) {
        localStorage.removeItem(selectedPlansStorageKey);
        return;
    }
    localStorage.setItem(selectedPlansStorageKey, String(selectedPlanId));
}

// Holt den aktuell gewaehlten Plan vom Server.
function fetchSelectedPlanId() {
    return fetch(selectedPlanApiUrl, { cache: 'no-store' })
        .then(response => response.ok ? response.json() : null)
        .then(data => {
            const payload = data && data.data ? data.data : null;
            const planId = payload ? payload.plan_id : null;
            const updatedAt = payload ? payload.updated_at : null;
            if (planId === null || planId === undefined || planId === '') {
                return { planId: null, updatedAt: null };
            }
            return {
                planId: String(planId),
                updatedAt: updatedAt ? String(updatedAt) : null
            };
        });
}

// Holt die Next-Aufguss-Settings vom Server.
function fetchNextAufgussSettings(planId) {
    if (!planId) {
        return Promise.resolve(null);
    }
    return fetch(`${nextAufgussSettingsApiUrl}?plan_id=${encodeURIComponent(planId)}`, { cache: 'no-store' })
        .then(response => response.ok ? response.json() : null)
        .then(data => {
            const payload = data && data.data ? data.data : null;
            if (!payload || !payload.settings) return null;
            return {
                planId: String(payload.plan_id),
                settings: payload.settings
            };
        });
}

// Synchronisiert Plan-Auswahl mit Server.
function syncSelectedPlanIdFromServer() {
    if (screenPlanLocked) return;
    if (serverPlanSyncInFlight) return;
    serverPlanSyncInFlight = true;

    fetchSelectedPlanId()
        .then(result => {
            serverPlanSyncInFlight = false;
            if (!result || !result.planId) return;
            const { planId, updatedAt } = result;
            const samePlanAndTimestamp = serverSelectedPlanId === planId && serverSelectedPlanUpdatedAt === updatedAt;
            if (samePlanAndTimestamp) {
                return;
            }
            serverSelectedPlanId = planId;
            serverSelectedPlanUpdatedAt = updatedAt;
            const parsedUpdatedAt = updatedAt ? Date.parse(updatedAt) : NaN;
            planAdAnchorTimeMs = Number.isNaN(parsedUpdatedAt) ? 0 : parsedUpdatedAt;
            if (selectedPlanId !== planId) {
                selectedPlanId = planId;
                saveSelectedPlan();
            }
            loadPlans();
            loadAufgussplan();
            fetchNextAufgussSettings(planId).then(resultSettings => {
                if (resultSettings && resultSettings.settings) {
                    serverNextAufgussSettings.set(resultSettings.planId, resultSettings.settings);
                }
            }).catch(() => {});
        })
        .catch(error => {
            serverPlanSyncInFlight = false;
            console.warn('Plan sync failed:', error);
        });
}

// Startet das Plan-Sync-Intervall.
function initServerPlanSync() {
    if (screenPlanLocked) return;
    if (serverPlanSyncTimer) {
        clearInterval(serverPlanSyncTimer);
    }
    syncSelectedPlanIdFromServer();
    serverPlanSyncTimer = setInterval(syncSelectedPlanIdFromServer, 5000);
}

// Erlaubt Autoplay nach Nutzeraktion.
function initVideoAutoplayUnlock() {
    const unlock = () => {
        if (videoAutoplayUnlocked) return;
        videoAutoplayUnlocked = true;
        const wrap = document.getElementById('plan-ad-wrap');
        const video = wrap ? wrap.querySelector('video') : null;
        if (video) {
            const playResult = video.play();
            if (playResult && typeof playResult.catch === 'function') {
                playResult.catch(() => {});
            }
        }
        window.removeEventListener('click', unlock);
        window.removeEventListener('touchstart', unlock);
        window.removeEventListener('keydown', unlock);
    };

    window.addEventListener('click', unlock, { once: true });
    window.addEventListener('touchstart', unlock, { once: true });
    window.addEventListener('keydown', unlock, { once: true });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            resumePlanAdVideo();
        }
    });
    window.addEventListener('focus', resumePlanAdVideo);
    window.addEventListener('pageshow', resumePlanAdVideo);
}

// Stellt ein Preload-Video bereit.
function ensurePlanAdPreloadVideo() {
    if (planAdPreloadVideo) return planAdPreloadVideo;
    const video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.setAttribute('muted', '');
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    video.preload = 'auto';
    movePlanAdVideoOffscreen(video);
    planAdPreloadVideo = video;
    return video;
}

// Erstellt Offscreen-Container fuer Video.
function ensurePlanAdOffscreenContainer() {
    if (planAdOffscreenContainer) return planAdOffscreenContainer;
    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.left = '-9999px';
    container.style.top = '0';
    container.style.width = '1px';
    container.style.height = '1px';
    container.style.overflow = 'hidden';
    document.body.appendChild(container);
    planAdOffscreenContainer = container;
    return container;
}

// Verschiebt Werbevideo offscreen.
function movePlanAdVideoOffscreen(video) {
    const container = ensurePlanAdOffscreenContainer();
    if (video.parentElement !== container) {
        container.appendChild(video);
    }
    video.classList.remove('plan-ad-asset');
    video.style.position = 'absolute';
    video.style.left = '0';
    video.style.top = '0';
    video.style.width = '1px';
    video.style.height = '1px';
}

// Bringt Werbevideo in die Anzeige.
function movePlanAdVideoVisible(video, media) {
    if (!video || !media) return;
    if (video.parentElement !== media) {
        media.innerHTML = '';
        media.appendChild(video);
    }
    video.classList.add('plan-ad-asset');
    video.style.position = '';
    video.style.left = '';
    video.style.top = '';
    video.style.width = '';
    video.style.height = '';
}

// Laedt Werbevideo vor.
function preloadPlanAdVideo(mediaPath) {
    if (!mediaPath || !document.body) return;
    const video = ensurePlanAdPreloadVideo();
    if (video.dataset.src !== mediaPath) {
        video.dataset.src = mediaPath;
        video.src = mediaPath;
        if (typeof video.load === 'function') {
            try {
                video.load();
            } catch (error) {
                // Ignore load errors on older browsers.
            }
        }
    }
}

// Setzt Default-Plan falls keiner gewaehlt.
function ensureSelectedPlan(Pläene) {
    if (screenPlanLocked) {
        return;
    }
    restoreSelectedPlans();
    if (!selectedPlanId && Array.isArray(Pläene) && Pläene.length > 0) {
        selectedPlanId = String(Pläene[0].id);
        saveSelectedPlan();
    }
}

// Extrahiert Aufgüsse aus API-Payload.
function extractAufguesse(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (payload && payload.data && Array.isArray(payload.data.aufguesse)) {
        return payload.data.aufguesse;
    }

    if (payload && Array.isArray(payload.aufguesse)) {
        return payload.aufguesse;
    }

    return [];
}

// Extrahiert Pläene aus API-Payload.
function extractPläene(payload) {
    if (payload && payload.data && Array.isArray(payload.data.Pläene)) {
        return payload.data.Pläene;
    }

    if (payload && Array.isArray(payload.Pläene)) {
        return payload.Pläene;
    }

    return [];
}

/**
 * AUFGUSSDATEN LADEN
 *
 * Ldt die aktuellen Aufgussdaten von der PHP-API.
 * Verwendet moderne Fetch API statt altem XMLHttpRequest.
 *
 * API-Endpunkt: api/aufguesse.php (gibt JSON zurck)
 */
function loadAufgussplan() {
    // HTTP-Request an die API senden
    fetch('api/aufguesse.php')
        // Antwort als JSON parsen
        .then(response => response.json())
        // Erfolgreiche Daten verarbeiten
        .then(data => {
            lastAufguesse = extractAufguesse(data);
            aufgussById = new Map(lastAufguesse.map(item => [String(item.id), item]));
            restoreSelectedPlans();
            if (!selectedPlanId && lastPläene.length > 0) {
                ensureSelectedPlan(lastPläene);
            }
            renderFilteredAufguesse();
        })
        // Fehler behandeln
        .catch(error => {
            console.error('Fehler beim Laden des Aufgussplans:', error);
            showError('Fehler beim Laden der Daten');
        });
}

// Reagiert auf Plan-Wechsel im Storage.
function initPlanChangeListener() {
    window.addEventListener('storage', event => {
        if (!event || !event.key) return;
        if (event.key === 'aufgussplanForceReload') {
            window.location.reload();
            return;
        }
        if (screenPlanLocked) return;
        if (event.key !== selectedPlansStorageKey && event.key !== planChangeStorageKey) return;
        restoreSelectedPlans();
        loadPlans();
        loadAufgussplan();
    });
}

// Baut die komplette Plan-Ansicht.
function renderPlanView(planId, Pläene, aufguesse) {
    const container = document.getElementById('aufgussplan');
    const hideHeader = !!(container && container.dataset && container.dataset.hidePlanHeader === 'true');
    if (screenMode === 'image') {
        renderScreenImage();
        return;
    }
    const plan = Array.isArray(Pläene)
        ? Pläene.find(item => String(item.id) === String(planId))
        : null;

    if (!plan) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">Kein Plan ausgewaehlt.</p>';
        return;
    }

    if (nextAufgussPlanId !== String(plan.id)) {
        nextAufgussShown = new Set();
        hideNextAufgussPopup();
        nextAufgussPlanId = String(plan.id);
    }

    const planName = plan.name || 'Plan';
    const planBeschreibung = plan.beschreibung || '';
    const planInitial = planName.trim() ? planName.trim().charAt(0).toUpperCase() : 'P';
    const createdAt = formatPlanDate(plan.erstellt_am);
    const forcedBackgroundImage = screenBackgroundPath ? normalizeScreenAssetPath(screenBackgroundPath) : '';
    const backgroundImage = forcedBackgroundImage || (plan.hintergrund_bild ? `uploads/${plan.hintergrund_bild}` : '');
    const planAdEnabled = !!plan.werbung_aktiv && !!plan.werbung_media;
    const adConfig = planAdEnabled
        ? {
            enabled: true,
            intervalMinutes: Number(plan.werbung_interval_minuten) || 10,
            durationSeconds: Number(plan.werbung_dauer_sekunden) || 10,
            mediaPath: `uploads/${plan.werbung_media}`,
            mediaType: plan.werbung_media_typ || ''
        }
        : { enabled: false, mediaPath: '', mediaType: '' };
    const adMediaPath = adConfig.mediaPath || '';
    const adMediaType = adConfig.mediaType || '';
    const planSettings = getNextAufgussSettings(plan.id);
    const clockEnabled = !!(planSettings && planSettings.clockEnabled);
    const bannerEnabled = !!(planSettings && planSettings.bannerEnabled);
    const bannerMode = planSettings ? planSettings.bannerMode : 'text';
    const bannerText = planSettings ? planSettings.bannerText : '';
    const bannerImage = planSettings ? planSettings.bannerImage : '';
    const bannerWidth = planSettings ? planSettings.bannerWidth : 220;
    const bannerHeight = planSettings ? planSettings.bannerHeight : 160;
    const textColor = planSettings ? planSettings.textColor : '';
    const themeColor = planSettings ? planSettings.themeColor : '';
    const clockBlockHeight = 96;
    const clockClass = (clockEnabled || bannerEnabled) ? ' plan-view-with-clock' : '';
    const bannerImagePath = bannerEnabled && bannerMode === 'image'
        ? normalizeBannerImagePath(bannerImage)
        : '';
    const bannerIsVideo = bannerEnabled && bannerMode === 'image' && isBannerVideoPath(bannerImagePath);
    const bannerContent = bannerEnabled
        ? (bannerMode === 'image'
            ? (bannerImagePath
                ? (bannerIsVideo
                    ? `<video src="${escapeHtml(bannerImagePath)}" class="plan-clock-banner-video" autoplay muted loop playsinline></video>`
                    : `<img src="${escapeHtml(bannerImagePath)}" alt="Banner" />`)
                : '')
            : (bannerText ? `<div class="plan-clock-banner-text">${escapeHtml(bannerText)}</div>` : ''))
        : '';
    const clockStackHeight = clockEnabled ? clockBlockHeight : 0;
    const safeBannerWidth = Number.isFinite(Number(bannerWidth)) ? Math.max(1, Number(bannerWidth)) : 220;
    const safeBannerHeight = Number.isFinite(Number(bannerHeight)) ? Math.max(1, Number(bannerHeight)) : 160;
    const bannerAspect = safeBannerWidth / safeBannerHeight;
    const clockVars = (clockEnabled || bannerContent)
        ? `--plan-clock-width: ${safeBannerWidth}px; --plan-clock-stack-height: ${clockStackHeight}px; --plan-banner-aspect: ${bannerAspect};`
        : '';
    const textVars = textColor ? `--plan-text-color: ${textColor};` : '';
    const accentVars = themeColor ? `--plan-accent-color: ${themeColor};` : '';
    const wrapperStyle = (clockVars || accentVars || textVars)
        ? ` style="${clockVars}${accentVars ? ` ${accentVars}` : ''}${textVars ? ` ${textVars}` : ''}"`
        : '';
    const outerStyle = textVars ? ` style="${textVars}"` : '';
    const clockStackStyle = clockVars ? ` style="${clockVars}"` : '';
    const bannerHtml = bannerContent
        ? `<div class="plan-clock-banner">${bannerContent}</div>`
        : '';
    const clockHtml = (clockEnabled || bannerContent)
        ? `
            <div class="plan-clock-stack"${clockStackStyle}>
                ${clockEnabled ? `
                    <div class="plan-clock" id="plan-clock">
                        <div class="plan-clock-time">--:--</div>
                        <div class="plan-clock-date">--.--.----</div>
                    </div>
                ` : ''}
                ${bannerHtml}
            </div>
        `
        : '';

    applyPlanBackground(backgroundImage);

    let tableHtml = '';
    if (!aufguesse || aufguesse.length === 0) {
        tableHtml = `
            <div class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg bg-white/70">
                <div class="text-4xl mb-2">?</div>
                <p class="text-lg font-medium">Noch keine Aufgüsse in diesem Plan</p>
            </div>
        `;
    } else if (hideHeader) {
        const rows = aufguesse.map(aufguss => renderPlanRowDiv(aufguss)).join('');
        tableHtml = `
            <div class="plan-list">
                <div class="plan-list-head font-display">
                    <div class="plan-list-cell">Zeit</div>
                    <div class="plan-list-cell">Aufguss</div>
                    <div class="plan-list-cell">Stärke</div>
                    <div class="plan-list-cell">Aufgießer</div>
                    <div class="plan-list-cell">Sauna</div>
                    <div class="plan-list-cell">Duft</div>
                </div>
                <div class="plan-list-body">
                    ${rows}
                </div>
            </div>
        `;
    } else {
        const rows = aufguesse.map(aufguss => renderPlanRow(aufguss)).join('');
        tableHtml = `
            <div class="overflow-x-auto">
                <table class="min-w-full bg-transparent border border-gray-200 rounded-lg">
                    <thead class="bg-white plan-table-head">
                        <tr>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Zeit</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Aufguss</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Stärke</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Aufgießer</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Sauna</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Duftmittel</th>
                        </tr>
                    </thead>
                    <tbody class="bg-transparent divide-y divide-gray-200">
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
    }

    const headerHtml = hideHeader
        ? ''
        : `
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center">
                        <span class="text-white font-bold text-lg">${planInitial}</span>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">${escapeHtml(planName)}</h2>
                        ${planBeschreibung ? `<p class="text-lg text-gray-600 mt-1">${escapeHtml(planBeschreibung)}</p>` : ''}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Erstellt am</div>
                    <div class="text-sm font-medium text-gray-900">${createdAt}</div>
                </div>
            </div>
        `;

    if (hideHeader) {
        container.innerHTML = `
            <div class="relative rounded-lg overflow-hidden${clockClass} plan-color-scope"${wrapperStyle}>
                ${renderPlanBackground(backgroundImage)}
                <div class="relative">
                    ${clockHtml}
                    <div class="plan-table-wrap">
                        ${tableHtml}
                    </div>
                    <div id="plan-ad-wrap" class="plan-ad-wrap is-fullscreen${adMediaPath ? '' : ' is-hidden'}">
                        <div id="plan-ad-media" class="plan-ad-media"></div>
                    </div>
                </div>
            </div>
        `;
        setupPlanAd(plan.id, adConfig);
        initPlanClock(clockEnabled ? document.getElementById('plan-clock') : null);
        requestAnimationFrame(() => updatePlanClockLayout(container.querySelector('.plan-view-with-clock') || container.firstElementChild));
        updateNextAufgussIndicators();
        return;
    }

    container.innerHTML = `
            <div class="bg-white rounded-lg shadow-md relative plan-color-scope"${outerStyle}>
            <div class="relative p-6">
                ${headerHtml}
                <div class="relative rounded-lg overflow-hidden${clockClass} plan-color-scope"${wrapperStyle}>
                    ${renderPlanBackground(backgroundImage)}
                    <div class="relative">
                        ${clockHtml}
                        <div class="plan-table-wrap">
                            ${tableHtml}
                        </div>
                        <div id="plan-ad-wrap" class="plan-ad-wrap${adMediaPath ? '' : ' is-hidden'}">
                            <div id="plan-ad-media" class="plan-ad-media"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    setupPlanAd(plan.id, adConfig);
    initPlanClock(clockEnabled ? document.getElementById('plan-clock') : null);
    requestAnimationFrame(() => updatePlanClockLayout(container.querySelector('.plan-view-with-clock')));
    updateNextAufgussIndicators();
}

// Erstellt Signatur fuer globale Werbung.
function buildGlobalAdSignature(globalAd) {
    if (!globalAd) return '';
    const order = Array.isArray(globalAd.order) ? globalAd.order.join(',') : '';
    return [
        globalAd.path || '',
        globalAd.type || '',
        globalAd.enabled ? '1' : '0',
        order,
        globalAd.display_seconds || '',
        globalAd.pause_seconds || '',
        globalAd.rotation_started_at || ''
    ].join('|');
}

// Uebernimmt globale Werbe-Config.
function updateGlobalAdConfig(globalAd, serverTime) {
    const serverMs = serverTime ? Date.parse(serverTime) : NaN;
    if (!Number.isNaN(serverMs)) {
        globalAdServerOffsetMs = serverMs - Date.now();
    }

    if (!globalAd) {
        globalAdConfig = {
            enabled: false,
            path: '',
            type: '',
            order: [],
            displaySeconds: 10,
            pauseSeconds: 10,
            rotationStartedAt: null
        };
        updateGlobalAdOverlay();
        return;
    }

    globalAdConfig = {
        enabled: !!globalAd.enabled,
        path: globalAd.path ? String(globalAd.path) : '',
        type: globalAd.type ? String(globalAd.type) : '',
        order: Array.isArray(globalAd.order)
            ? globalAd.order.map(Number).filter(value => Number.isFinite(value))
            : [],
        displaySeconds: Number(globalAd.display_seconds) || 10,
        pauseSeconds: Number(globalAd.pause_seconds) || 10,
        rotationStartedAt: globalAd.rotation_started_at ? String(globalAd.rotation_started_at) : null
    };
    updateGlobalAdOverlay();
}

function isVideoPath(path) {
    return /\.(mp4|webm|ogg)$/i.test(path || '');
}

function renderPlanBackground(path) {
    if (!path) return '';
    if (isVideoPath(path)) {
        return `<video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline>
            <source src="${path}">
        </video>`;
    }
    return `<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${path}');"></div>`;
}

// Setzt Hintergrund fuer Plan.
function applyPlanBackground(path) {
    const body = document.body;
    if (!body) return;

    const existingVideo = document.getElementById('plan-background-video');
    const isVideo = isVideoPath(path);

    if (isVideo && path) {
        if (!existingVideo) {
            const video = document.createElement('video');
            video.id = 'plan-background-video';
            video.autoplay = true;
            video.loop = true;
            video.muted = true;
            video.playsInline = true;
            video.className = 'plan-background-video';
            video.style.position = 'fixed';
            video.style.inset = '0';
            video.style.width = '100%';
            video.style.height = '100%';
            video.style.objectFit = 'cover';
            video.style.zIndex = '-1';
            video.style.pointerEvents = 'none';
            body.prepend(video);
            video.src = path;
        } else if (existingVideo.src !== path) {
            existingVideo.src = path;
        }

        body.style.backgroundImage = '';
        body.style.backgroundSize = '';
        body.style.backgroundPosition = '';
        body.style.backgroundRepeat = '';
        body.style.backgroundAttachment = '';
        return;
    }

    if (existingVideo) {
        existingVideo.remove();
    }

    if (path) {
        body.style.backgroundImage = `url('${path}')`;
        body.style.backgroundSize = 'cover';
        body.style.backgroundPosition = 'center';
        body.style.backgroundRepeat = 'no-repeat';
        body.style.backgroundAttachment = 'fixed';
    } else {
        body.style.backgroundImage = '';
        body.style.backgroundSize = '';
        body.style.backgroundPosition = '';
        body.style.backgroundRepeat = '';
        body.style.backgroundAttachment = '';
    }
}

// Initialisiert Plan-Werbung.
function setupPlanAd(planId, adConfig) {
    const enabled = !!(adConfig && adConfig.enabled);
    const intervalMinutes = Number(adConfig && adConfig.intervalMinutes) || 10;
    const durationSeconds = Number(adConfig && adConfig.durationSeconds) || 10;
    const adMediaPath = adConfig && adConfig.mediaPath ? adConfig.mediaPath : '';
    const adMediaType = adConfig && adConfig.mediaType ? adConfig.mediaType : '';
    const intervalMs = Math.max(1, intervalMinutes) * 60000;
    const durationMs = durationSeconds * 1000;

    if (!enabled || !adMediaPath) {
        clearPlanAdTimers();
        activeAdPlanId = null;
        hidePlanAd();
        planAdConfigKey = null;
        currentPlanAdIntervalMs = 0;
        return;
    }

    activeAdPlanId = String(planId);
    if (adMediaType === 'video') {
        preloadPlanAdVideo(adMediaPath);
    }
    const adKey = `${activeAdPlanId}|${adMediaPath}`;
    if (lastPlanAdKey !== adKey) {
        lastPlanAdKey = adKey;
        lastPlanAdShownAt = 0;
    }
    const newConfigKey = `${adKey}|${intervalMs}|${durationMs}`;
    if (planAdConfigKey === newConfigKey && planAdIntervalId) {
        return;
    }
    planAdConfigKey = newConfigKey;
    currentPlanAdIntervalMs = intervalMs;
    clearPlanAdTimers();
    schedulePlanAd(intervalMs, durationMs, adMediaPath, adMediaType, planAdAnchorTimeMs);
}

// Plant die Anzeige der Plan-Werbung.
function schedulePlanAd(intervalMs, durationMs, mediaPath, mediaType, anchorMs) {
    const now = Date.now();
    let nextDelay = 0;

    if (anchorMs && !Number.isNaN(anchorMs)) {
        const elapsed = ((now - anchorMs) % intervalMs + intervalMs) % intervalMs;
        if (elapsed < durationMs) {
            showPlanAd(mediaPath, mediaType, durationMs);
            nextDelay = intervalMs - elapsed;
        } else {
            nextDelay = intervalMs - elapsed;
        }
    } else {
        showPlanAd(mediaPath, mediaType, durationMs);
        nextDelay = intervalMs;
    }

    planAdStartTimeout = setTimeout(() => {
        showPlanAd(mediaPath, mediaType, durationMs);
        planAdIntervalId = setInterval(() => {
            showPlanAd(mediaPath, mediaType, durationMs);
        }, intervalMs);
    }, Math.max(0, nextDelay));
}

// Startet Werbevideo, falls moeglich.
function tryPlayPlanAdVideo(video, forceLoad = false) {
    if (!video) return;
    if (forceLoad && typeof video.load === 'function') {
        try {
            video.load();
        } catch (error) {
            // Ignore load errors on older browsers.
        }
    }
    if (video.ended) {
        video.currentTime = 0;
    }
    const playResult = video.play();
    if (playResult && typeof playResult.catch === 'function') {
        playResult.catch(() => {});
    }
}

// Normalisiert Banner-Bildpfad.
function normalizeBannerImagePath(path) {
    if (!path) return '';
    const trimmed = String(path).trim();
    if (!trimmed) return '';
    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }
    if (trimmed.startsWith('uploads/')) {
        return trimmed;
    }
    return `uploads/${trimmed.replace(/^\/+/, '')}`;
}

// Startet Rotation fuer globale Werbung.
function initGlobalAdTicker() {
    if (globalAdTimer) return;
    globalAdTimer = setInterval(updateGlobalAdOverlay, 500);
    updateGlobalAdOverlay();
}

// Berechnet Zeitbasis fuer globale Werbung.
function getGlobalAdNowMs() {
    return Date.now() + globalAdServerOffsetMs;
}

// Aktualisiert globales Werbe-Overlay.
function updateGlobalAdOverlay() {
    const state = computeGlobalAdState();
    if (!state.show) {
        hideGlobalAd();
        return;
    }
    showGlobalAd(state.mediaPath, state.mediaType);
}

// Berechnet aktuellen Werbe-Status.
function computeGlobalAdState() {
    if (!screenId || !globalAdConfig.enabled) {
        return { show: false };
    }
    const order = Array.isArray(globalAdConfig.order)
        ? globalAdConfig.order.map(Number).filter(value => Number.isFinite(value))
        : [];
    if (!order.length || !order.includes(Number(screenId))) {
        return { show: false };
    }
    const mediaPath = globalAdConfig.path ? normalizeBannerImagePath(globalAdConfig.path) : '';
    if (!mediaPath) {
        return { show: false };
    }

    const displayMs = Math.max(1, Number(globalAdConfig.displaySeconds) || 10) * 1000;
    const pauseMs = Math.max(0, Number(globalAdConfig.pauseSeconds) || 10) * 1000;
    const sequenceMs = displayMs * order.length;
    const cycleMs = sequenceMs + pauseMs;
    if (!cycleMs) {
        return { show: false };
    }

    const nowMs = getGlobalAdNowMs();
    const startMs = globalAdConfig.rotationStartedAt
        ? Date.parse(globalAdConfig.rotationStartedAt)
        : nowMs;
    const elapsed = ((nowMs - startMs) % cycleMs + cycleMs) % cycleMs;
    if (elapsed >= sequenceMs) {
        return { show: false };
    }
    const index = Math.floor(elapsed / displayMs);
    const activeScreenId = order[index];
    const shouldShow = Number(activeScreenId) === Number(screenId);
    if (!shouldShow) {
        return { show: false };
    }

    const mediaType = globalAdConfig.type
        ? globalAdConfig.type
        : (isBannerVideoPath(mediaPath) ? 'video' : 'image');
    return {
        show: true,
        mediaPath,
        mediaType
    };
}

// Erstellt DOM-Elemente fuer globale Werbung.
function ensureGlobalAdElements() {
    let wrap = document.getElementById('global-ad-wrap');
    if (wrap) {
        return {
            wrap,
            media: wrap.querySelector('.plan-ad-media')
        };
    }
    wrap = document.createElement('div');
    wrap.id = 'global-ad-wrap';
    wrap.className = 'plan-ad-wrap is-fullscreen global-ad-wrap';
    const media = document.createElement('div');
    media.className = 'plan-ad-media';
    wrap.appendChild(media);
    document.body.appendChild(wrap);
    return { wrap, media };
}

// Zeigt globale Werbung an.
function showGlobalAd(mediaPath, mediaType) {
    const { wrap, media } = ensureGlobalAdElements();
    if (!wrap || !media) return;

    if (globalAdExitTimer) {
        clearTimeout(globalAdExitTimer);
        globalAdExitTimer = null;
    }
    wrap.classList.remove('is-exiting');
    setPlanAdDirectionVars(wrap);

    if (mediaPath !== globalAdCurrentPath || mediaType !== globalAdCurrentType) {
        if (mediaType === 'video') {
            media.innerHTML = `<video src="${escapeHtml(mediaPath)}" class="plan-ad-asset" autoplay muted loop playsinline></video>`;
        } else {
            media.innerHTML = `<img src="${escapeHtml(mediaPath)}" alt="Werbung" class="plan-ad-asset">`;
        }
        globalAdCurrentPath = mediaPath;
        globalAdCurrentType = mediaType;
    }

    if (!globalAdVisible) {
        wrap.classList.add('is-visible');
        globalAdVisible = true;
    }
}

// Blendet globale Werbung aus.
function hideGlobalAd() {
    const wrap = document.getElementById('global-ad-wrap');
    if (!wrap || !globalAdVisible) {
        return;
    }
    setPlanAdDirectionVars(wrap);
    wrap.classList.add('is-exiting');
    if (globalAdExitTimer) {
        clearTimeout(globalAdExitTimer);
    }
    globalAdExitTimer = setTimeout(() => {
        wrap.classList.remove('is-visible');
        wrap.classList.remove('is-exiting');
        globalAdVisible = false;
    }, 320);
}

// Formatiert Uhrzeit.
function formatClockTime(value) {
    return value.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

// Formatiert Datum.
function formatClockDate(value) {
    return value.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Aktualisiert die Uhr im Plan.
function updatePlanClock(clockEl) {
    if (!clockEl) return;
    const now = new Date();
    const timeEl = clockEl.querySelector('.plan-clock-time');
    const dateEl = clockEl.querySelector('.plan-clock-date');
    if (timeEl) timeEl.textContent = formatClockTime(now);
    if (dateEl) dateEl.textContent = formatClockDate(now);
}

// Initialisiert die Plan-Uhr.
function initPlanClock(clockEl) {
    if (planClockTimer) {
        clearInterval(planClockTimer);
        planClockTimer = null;
    }
    if (!clockEl) return;
    updatePlanClock(clockEl);
    planClockTimer = setInterval(() => updatePlanClock(clockEl), 1000);
}

// Aktualisiert Clock/Banner Layout.
function updatePlanClockLayout(container) {
    if (!container) return;
    const stack = container.querySelector('.plan-clock-stack');
    if (!stack) {
        container.style.setProperty('--plan-clock-stack-height', '0px');
        return;
    }
    const height = stack.offsetHeight || 0;
    container.style.setProperty('--plan-clock-stack-height', `${height + 8}px`);
}

// Setzt Werbevideo fort.
function resumePlanAdVideo() {
    const wrap = document.getElementById('plan-ad-wrap');
    if (!wrap || !wrap.classList.contains('is-visible')) return;
    const video = wrap.querySelector('video');
    if (!video) return;
    if (video.paused || video.ended) {
        tryPlayPlanAdVideo(video, false);
    }
}

// Zeigt Plan-Werbung an.
function showPlanAd(mediaPath, mediaType, durationMs) {
    const wrap = document.getElementById('plan-ad-wrap');
    const media = document.getElementById('plan-ad-media');

    if (!wrap || !media) return;

    if (planAdExitTimer) {
        clearTimeout(planAdExitTimer);
        planAdExitTimer = null;
    }
    wrap.classList.remove('is-exiting');
    setPlanAdDirectionVars(wrap);

    const now = Date.now();
    if (lastPlanAdShownAt && (now - lastPlanAdShownAt) < 500) {
        return;
    }
    lastPlanAdShownAt = now;

    if (mediaType === 'video') {
        let video = media.querySelector('video');
        const needsReplace = !video || video.dataset.src !== mediaPath;
        if (needsReplace) {
            video = ensurePlanAdPreloadVideo();
        }
        if (video) {
            movePlanAdVideoOffscreen(video);
            video.dataset.src = mediaPath;
            if (video.src !== mediaPath) {
                video.src = mediaPath;
            }
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            video.setAttribute('playsinline', '');
            video.setAttribute('webkit-playsinline', '');
            video.setAttribute('muted', '');
            video.loop = true;

            const reveal = () => movePlanAdVideoVisible(video, media);
            const tryPlay = () => {
                tryPlayPlanAdVideo(video, true);
            };

            video.addEventListener('playing', reveal, { once: true });
            video.addEventListener('loadedmetadata', tryPlay, { once: true });
            setTimeout(tryPlay, 150);
            setTimeout(reveal, 1200);
        }
    } else {
        media.innerHTML = `<img src="${mediaPath}" alt="Werbung" class="plan-ad-asset">`;
    }

    wrap.classList.add('is-visible');

    if (planAdHideTimeout) {
        clearTimeout(planAdHideTimeout);
    }
    planAdHideTimeout = setTimeout(() => {
        hidePlanAd();
    }, durationMs);
}

// Blendet Plan-Werbung aus.
function hidePlanAd() {
    const wrap = document.getElementById('plan-ad-wrap');
    if (!wrap) return;
    setPlanAdDirectionVars(wrap);
    wrap.classList.add('is-exiting');
    if (planAdExitTimer) {
        clearTimeout(planAdExitTimer);
    }
    planAdExitTimer = setTimeout(() => {
        wrap.classList.remove('is-visible');
        wrap.classList.remove('is-exiting');
    }, 320);
}

// Setzt CSS-Variablen fuer Richtung.
function setPlanAdDirectionVars(wrap) {
    const direction = screenAdDirection || 'right';
    let enterX = '-110%';
    let exitX = '110%';
    let enterY = '0';
    let exitY = '0';
    if (direction === 'left') {
        enterX = '110%';
        exitX = '-110%';
    } else if (direction === 'up') {
        enterX = '0';
        exitX = '0';
        enterY = '110%';
        exitY = '-110%';
    } else if (direction === 'down') {
        enterX = '0';
        exitX = '0';
        enterY = '-110%';
        exitY = '110%';
    }
    wrap.style.setProperty('--plan-ad-enter-x', enterX);
    wrap.style.setProperty('--plan-ad-exit-x', exitX);
    wrap.style.setProperty('--plan-ad-enter-y', enterY);
    wrap.style.setProperty('--plan-ad-exit-y', exitY);
}

// Stoppt Timer der Plan-Werbung.
function clearPlanAdTimers() {
    if (planAdStartTimeout) {
        clearTimeout(planAdStartTimeout);
        planAdStartTimeout = null;
    }
    if (planAdIntervalId) {
        clearInterval(planAdIntervalId);
        planAdIntervalId = null;
    }
    if (planAdHideTimeout) {
        clearTimeout(planAdHideTimeout);
        planAdHideTimeout = null;
    }
}

// Rendert eine Tabellenzeile fuer Aufguss.
function renderPlanRow(aufguss) {
    const timeText = formatAufgussTime(aufguss);
    const nameText = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerke = formatStaerke(aufguss.staerke);
    const aufgiesserHtml = formatAufgiesserHtml(aufguss);
    const saunaHtml = formatSaunaHtml(aufguss);
    const duftmittelHtml = formatDuftmittelHtml(aufguss);
    const startTs = getAufgussStartTimestamp(aufguss);

    return `
        <tr class="bg-white/35" data-aufguss-id="${escapeHtml(aufguss.id)}" data-start-ts="${startTs || ''}">
            <td class="px-6 py-4 whitespace-normal break-words text-lg font-bold text-gray-900">${escapeHtml(timeText)}</td>
            <td class="px-6 py-4 whitespace-normal break-words text-sm font-medium text-gray-900">${escapeHtml(nameText)}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${staerke.bgClass} ${staerke.textClass}">
                    ${escapeHtml(staerke.text)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-normal break-words text-sm text-gray-900">${aufgiesserHtml}</td>
            <td class="px-6 py-4 whitespace-normal break-words text-sm text-gray-900">${saunaHtml}</td>
            <td class="px-6 py-4 whitespace-normal break-words text-sm text-gray-900">${duftmittelHtml}</td>
        </tr>
    `;
}

// Rendert eine Aufgusskarte im Grid.
function renderPlanRowDiv(aufguss) {
    const timeParts = formatAufgussTimeParts(aufguss);
    const nameText = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerke = formatStaerke(aufguss.staerke);
    const aufgiesserHtml = formatAufgiesserHtml(aufguss);
    const saunaHtml = formatSaunaHtmlStacked(aufguss);
    const duftmittelHtml = formatDuftmittelHtml(aufguss, true);
    const startTs = getAufgussStartTimestamp(aufguss);
    const timeHtml = timeParts.end
        ? `<div class="plan-list-time"><span>${escapeHtml(timeParts.start)}</span><span>${escapeHtml(timeParts.end)}</span></div>`
        : `<div class="plan-list-time"><span>${escapeHtml(timeParts.start)}</span></div>`;

    return `
        <div class="plan-list-row" data-aufguss-id="${escapeHtml(aufguss.id)}" data-start-ts="${startTs || ''}">
            <div class="plan-list-cell text-lg font-bold text-gray-900">${timeHtml}</div>
            <div class="plan-list-cell text-sm font-bold text-gray-900">${escapeHtml(nameText)}</div>
            <div class="plan-list-cell">
                <span class="plan-strength-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${staerke.bgClass} ${staerke.textClass}">
                    ${escapeHtml(staerke.text)}
                </span>
            </div>
            <div class="plan-list-cell text-sm text-gray-900"><div class="plan-list-people">${aufgiesserHtml}</div></div>
            <div class="plan-list-cell text-sm text-gray-900">${saunaHtml}</div>
            <div class="plan-list-cell text-sm text-gray-900">${duftmittelHtml}</div>
        </div>
    `;
}

// Ermittelt Start/Ende fuer Anzeige.
function formatAufgussTimeParts(aufguss) {
    const start = aufguss.zeit_anfang || aufguss.zeit || '';
    const end = aufguss.zeit_ende || '';
    if (start && end) {
        return { start: formatTime(start), end: formatTime(end) };
    }
    if (start) {
        return { start: formatTime(start), end: '' };
    }
    return { start: '--:--', end: '' };
}

// Baut Sauna-Block fuer Anzeige.
function formatSaunaHtmlStacked(aufguss) {
    const name = aufguss.sauna_name || aufguss.sauna || '-';
    const image = aufguss.sauna_bild || '';
    const tempBadge = formatSaunaTempBadge(aufguss);
    const tempText = formatSaunaTempText(aufguss);
    const tempSuffix = '';
    if (!image) {
        return `<div class="plan-media-stack"><span>${escapeHtml(name)}${tempSuffix}</span></div>`;
    }
    const imagePath = `uploads/${image}`;
    const imgTag = buildImageTag(imagePath, placeholderSauna, name, 'h-12 w-12 rounded-full object-cover border border-gray-200');
    return `
        <div class="plan-media-stack">
            <div class="relative">
                ${imgTag}
                ${tempBadge}
            </div>
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}${tempSuffix}</span>
        </div>
    `;
}

// Formatiert die Aufgusszeit.
function formatAufgussTime(aufguss) {
    const start = aufguss.zeit_anfang || '';
    const end = aufguss.zeit_ende || '';
    if (start && end) {
        return `${formatTime(start)} - ${formatTime(end)}`;
    }
    if (aufguss.zeit) {
        return formatTime(aufguss.zeit);
    }
    return '--:--';
}

// Formatiert Zeitwerte.
function formatTime(value) {
    if (!value) return '--:--';
    const parts = String(value).split(':');
    if (parts.length >= 2) {
        return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
    }
    return value;
}

// Berechnet Start-Zeitstempel.
function getAufgussStartTimestamp(aufguss) {
    let dateValue = aufguss.datum || '';
    const timeValue = aufguss.zeit_anfang || aufguss.zeit || '';
    if (!timeValue) return null;
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const todayValue = `${today.getFullYear()}-${month}-${day}`;
    if (!dateValue) {
        dateValue = todayValue;
    }
    const normalizedTime = timeValue.length === 5 ? `${timeValue}:00` : timeValue;
    let ts = new Date(`${dateValue}T${normalizedTime}`).getTime();
    if (!Number.isNaN(ts)) {
        const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
        if (ts < startOfToday) {
            ts = new Date(`${todayValue}T${normalizedTime}`).getTime();
        }
    }
    if (Number.isNaN(ts)) {
        const fallbackDate = todayValue;
        ts = new Date(`${fallbackDate}T${normalizedTime}`).getTime();
    }
    if (Number.isNaN(ts)) return null;
    return ts;
}

// Formatiert Datum fuer Kopfzeile.
function formatPlanDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}.${month}.${date.getFullYear()}`;
}

// Formatiert Staerke-Anzeige.
function formatStaerke(value) {
    const level = Number(value) || 0;
    const map = {
        1: { text: '1 Sehr leicht', bgClass: 'bg-green-100', textClass: 'text-green-800' },
        2: { text: '2 Leicht', bgClass: 'bg-green-200', textClass: 'text-green-800' },
        3: { text: '3 Mittel', bgClass: 'bg-yellow-100', textClass: 'text-yellow-800' },
        4: { text: '4 Stark', bgClass: 'bg-orange-100', textClass: 'text-orange-800' },
        5: { text: '5 Stark+', bgClass: 'bg-red-100', textClass: 'text-red-800' },
        6: { text: '6 Extrem', bgClass: 'bg-red-200', textClass: 'text-red-900' }
    };
    return map[level] || { text: 'Unbekannt', bgClass: 'bg-gray-100', textClass: 'text-gray-800' };
}

// Formatiert Aufgiesser-Text.
function formatAufgiesser(aufguss) {
    if (aufguss.aufgieser_namen) {
        return aufguss.aufgieser_namen;
    }
    return aufguss.mitarbeiter_name || aufguss.aufgieser_name || 'Unbekannt';
}

// Baut Aufgiesser-HTML.
function formatAufgiesserHtml(aufguss) {
    const people = parseAufgiesserItems(aufguss);
    if (!people.length) {
        return escapeHtml(formatAufgiesser(aufguss));
    }

    const cards = people.map(person => {
        const name = person.name || 'Unbekannt';
        if (!person.image) {
            return `
                <div class="flex flex-col items-center justify-center">
                    <div class="text-sm font-bold text-gray-900 text-center">${escapeHtml(name)}</div>
                </div>
            `;
        }
        const imagePath = `uploads/${person.image}`;
        const imgTag = buildImageTag(imagePath, placeholderMitarbeiter, name, 'h-10 w-10 rounded-full object-cover border border-gray-200');
        return `
            <div class="flex flex-col items-center">
                ${imgTag}
                <div class="mt-2 text-sm font-bold text-gray-900 text-center">${escapeHtml(name)}</div>
            </div>
        `;
    });

    const wrapClass = people.length > 1 ? 'plan-list-people-wrap has-multiple' : 'plan-list-people-wrap';
    return `<div class="flex flex-wrap justify-center gap-4 w-full ${wrapClass}">${cards.join('')}</div>`;
}

// Extrahiert Aufgiesser aus Daten.
function parseAufgiesserItems(aufguss) {
    const raw = aufguss.aufgieser_items || '';
    const people = [];

    if (raw) {
        raw.split(';;').forEach(item => {
            const parts = item.split('||');
            const name = (parts[0] || '').trim();
            const image = (parts[1] || '').trim();
            if (name) {
                people.push({ name, image });
            }
        });
    }

    if (!people.length) {
        const name = aufguss.mitarbeiter_name || aufguss.aufgieser_name || '';
        const image = aufguss.mitarbeiter_bild || '';
        if (name) {
            people.push({ name, image });
        }
    }

    return people;
}

// Baut Sauna-HTML.
function formatSaunaHtml(aufguss) {
    const name = aufguss.sauna_name || aufguss.sauna || '-';
    const image = aufguss.sauna_bild || '';
    const tempBadge = formatSaunaTempBadge(aufguss);
    const tempText = formatSaunaTempText(aufguss);
    const tempSuffix = '';
    if (!image) {
        return tempText ? `${escapeHtml(name)}${tempSuffix}` : escapeHtml(name);
    }
    const imagePath = `uploads/${image}`;
    const imgTag = buildImageTag(imagePath, placeholderSauna, name, 'h-10 w-10 rounded-full object-cover border border-gray-200');
    return `
        <div class="flex items-center gap-3">
            <div class="relative">
                ${imgTag}
                ${tempBadge}
            </div>
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}${tempSuffix}</span>
        </div>
    `;
}

// Baut Duftmittel-HTML (optional mit Bild).
function formatDuftmittelHtml(aufguss, stacked = false) {
    const name = aufguss.duftmittel_name || aufguss.duftmittel || '-';
    const image = aufguss.duftmittel_bild || '';
    if (!image) {
        return escapeHtml(name);
    }
    const imgTag = `<img src="uploads/${image}" alt="${escapeHtml(name)}" class="h-10 w-10 rounded-full object-cover border border-gray-200" onerror="this.onerror=null;this.remove();">`;
    if (stacked) {
        return `
            <div class="plan-media-stack">
                <div class="relative">${imgTag}</div>
                <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}</span>
            </div>
        `;
    }
    return `
        <div class="flex items-center gap-3">
            ${imgTag}
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}</span>
        </div>
    `;
}

// Formatiert Sauna-Temperatur.
function formatSaunaTempText(aufguss) {
    const tempValue = aufguss.sauna_temperatur;
    if (tempValue === null || tempValue === undefined || tempValue === '') {
        return '';
    }
    const numeric = Number(tempValue);
    if (Number.isNaN(numeric)) {
        return '';
    }
    return String(numeric);
}

// Baut Temperatur-Badge.
function formatSaunaTempBadge(aufguss) {
    const tempText = formatSaunaTempText(aufguss);
    if (!tempText) return '';
    return `<span class="plan-temp-badge absolute -top-1 -right-8 text-sm leading-none px-3 py-1.5 rounded-full border">${escapeHtml(tempText)}&deg;C</span>`;
}

// Prueft Banner-Pfad auf Video.
function isBannerVideoPath(path) {
    if (!path) return false;
    const cleanPath = String(path).split('?')[0].split('#')[0];
    return /\.(mp4|webm|ogg)$/i.test(cleanPath);
}

// Escaped HTML fuer sichere Ausgabe.
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildImageTag(src, fallbackSrc, alt, className) {
    const safeAlt = escapeHtml(alt || '');
    const safeSrc = src || fallbackSrc;
    return `<img src="${safeSrc}" alt="${safeAlt}" class="${className}" onerror="this.onerror=null;this.src='${fallbackSrc}'">`;
}

/**
 * TIMER FR LAUFENDEN AUFGUSS STARTEN
 *
 * Zeigt einen Live-Countdown fuer den aktuell laufenden Aufguss an.
 * Aktualisiert sich jede Sekunde und ndert die Farbe bei wenig Zeit.
 *
 * @param {Object} aufguss - Das Aufguss-Objekt mit datum, zeit, dauer
 */
function startTimer(aufguss) {
    // Start- und Endzeit berechnen
    const aufgussTime = new Date(aufguss.datum + ' ' + aufguss.zeit);
    const endTime = new Date(aufgussTime.getTime() + (aufguss.dauer || 15) * 60000);
    const timerElement = document.getElementById('timer');

    /**
     * TIMER UPDATE FUNKTION
     *
     * Wird jede Sekunde aufgerufen, um die Anzeige zu aktualisieren.
     */
    function updateTimer() {
        const now = new Date();
        const remaining = Math.max(0, endTime - now); // Verbleibende Millisekunden

        // AUFGUSS BEENDET
        if (remaining === 0) {
            timerElement.textContent = 'Beendet';
            timerElement.className = 'timer'; // CSS-Klassen zurcksetzen
            return; // Timer stoppen
        }

        // ZEIT BERECHNEN
        const minutes = Math.floor(remaining / 60000);           // Ganzzahlige Minuten
        const seconds = Math.floor((remaining % 60000) / 1000);  // Sekunden im Bereich 0-59

        // FORMAT mm:ss mit fhrenden Nullen
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        /**
         * FARBKODIERUNG NACH VERBLEIBENDER ZEIT
         *
         * warning: Unter 5 Minuten (gelb)
         * danger: Unter 1 Minute (rot) + blinken
         */
        if (remaining < 300000) { // 5 Minuten = 300.000 Millisekunden
            timerElement.classList.add('warning');
        }
        if (remaining < 60000) { // 1 Minute = 60.000 Millisekunden
            timerElement.classList.add('danger');
        }
    }

    // Timer sofort starten
    updateTimer();

    // Alle 1000ms (1 Sekunde) aktualisieren
    setInterval(updateTimer, 1000);
}
// Berechnet End-Zeitstempel.
function getAufgussEndTimestamp(aufguss, startTs) {
    const baseTs = startTs || getAufgussStartTimestamp(aufguss);
    if (!baseTs) return null;
    if (aufguss.zeit_ende) {
        const endTs = buildAufgussTimestamp(aufguss.datum, aufguss.zeit_ende);
        if (endTs) return endTs;
    }
    const minutes = Number(aufguss.dauer) || 15;
    return baseTs + (minutes * 60000);
}

// Baut Zeitstempel aus Datum/Zeit.
function buildAufgussTimestamp(dateValue, timeValue) {
    if (!timeValue) return null;
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const todayValue = `${today.getFullYear()}-${month}-${day}`;
    const datePart = dateValue || todayValue;
    const normalizedTime = timeValue.length === 5 ? `${timeValue}:00` : timeValue;
    const ts = new Date(`${datePart}T${normalizedTime}`).getTime();
    return Number.isNaN(ts) ? null : ts;
}

// Erzeugt Statistik-Schluessel.
function getStatsKey(aufguss) {
    const datum = aufguss.datum || '';
    return `${aufguss.id}:${datum}`;
}

// Laedt bereits geloggte Stats.
function loadStatsLogged() {
    try {
        const raw = localStorage.getItem(statsLoggedStorageKey);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return;
        statsLogged = new Set(parsed);
    } catch (error) {
        statsLogged = new Set();
    }
}

// Speichert geloggte Stats.
function saveStatsLogged() {
    try {
        localStorage.setItem(statsLoggedStorageKey, JSON.stringify(Array.from(statsLogged)));
    } catch (error) {
        // ignore
    }
}

// Sendet Abschluss-Statistik.
function logAufgussCompleted(aufguss) {
    const key = getStatsKey(aufguss);
    if (statsLogged.has(key)) return;
    statsLogged.add(key);
    saveStatsLogged();

    fetch('api/statistik.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aufguss_id: aufguss.id })
    }).catch(() => {
        // ignore logging failures
    });
}


/**
 * VOLLBILD-MODUS EIN-/AUSSCHALTEN
 *
 * Fr TV-Bildschirme: Vollbild aktivieren/deaktivieren.
 * Verwendet die Fullscreen API des Browsers.
 */
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        // Nicht im Vollbild: Vollbild aktivieren
        document.documentElement.requestFullscreen();
    } else {
        // Im Vollbild: Vollbild beenden
        document.exitFullscreen();
    }
}

/**
 * FEHLERMELDUNG ANZEIGEN
 *
 * Zeigt eine rote Fehlermeldung anstelle des Aufgussplans an.
 * Wird verwendet, wenn die API nicht erreichbar ist.
 *
 * @param {string} message - Die anzuzeigende Fehlermeldung
 */
function showError(message) {
    const container = document.getElementById('aufgussplan');

    // Fehler-HTML generieren (rot, mit Rahmen)
    container.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <p>${message}</p>
    </div>`;
}

// Startet Timer fuer Naechster-Aufguss.
function initNextAufgussTimer() {
    if (nextAufgussTimer) return;
    nextAufgussTimer = setInterval(updateNextAufgussIndicators, 1000);
}

// Aktualisiert Next-Aufguss-Status.
function updateNextAufgussIndicators() {
    if (!selectedPlanId) {
        clearNextAufgussHighlight();
        return;
    }

    const settings = getNextAufgussSettings(selectedPlanId);
    const shouldShow = settings.enabled;
    const shouldHighlight = settings.highlightEnabled;

    if (!shouldShow && !shouldHighlight) {
        clearNextAufgussHighlight();
    }

    const rows = Array.from(document.querySelectorAll('[data-start-ts]'));
    rows.forEach(row => row.classList.remove('next-aufguss-row'));
    const now = Date.now();

    rows.forEach(row => {
        const aufgussId = row.getAttribute('data-aufguss-id');
        if (!aufgussId) return;
        const aufguss = aufgussById.get(String(aufgussId));
        if (!aufguss) return;
        const startTs = Number(row.getAttribute('data-start-ts') || 0) || getAufgussStartTimestamp(aufguss);
        const endTs = getAufgussEndTimestamp(aufguss, startTs);
        if (!startTs || !endTs) return;
        if (now >= startTs && now <= endTs) {
            logAufgussCompleted(aufguss);
        }
    });

    let nextRow = null;
    let nextStart = null;
    const dayMs = 24 * 60 * 60 * 1000;
    rows.forEach(row => {
        let startTs = Number(row.getAttribute('data-start-ts') || 0);
        if (!startTs) {
            const aufgussId = row.getAttribute('data-aufguss-id');
            const aufguss = aufgussId ? aufgussById.get(String(aufgussId)) : null;
            startTs = aufguss ? (getAufgussStartTimestamp(aufguss) || 0) : 0;
        }
        if (!startTs) return;
        if (startTs <= now) {
            startTs += dayMs;
        }
        if (nextStart === null || startTs < nextStart) {
            nextStart = startTs;
            nextRow = row;
        }
    });

    if (nextRow && shouldHighlight) {
        nextRow.classList.add('next-aufguss-row');
    }
    if (nextRow) {
        focusNextRow(nextRow);
    }

    if (!shouldShow || !nextRow) return;

    const leadMs = Math.max(1, settings.leadSeconds) * 1000;
    if (nextStart - now <= leadMs && nextStart - now > 0) {
        const aufgussId = nextRow.getAttribute('data-aufguss-id');
        const nextDate = new Date(nextStart);
        const dayKey = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}-${String(nextDate.getDate()).padStart(2, '0')}`;
        const key = `${selectedPlanId}:${aufgussId}:${dayKey}`;
        if (!nextAufgussShown.has(key)) {
            const aufguss = aufgussById.get(String(aufgussId));
            if (aufguss) {
                showNextAufgussPopup(aufguss, nextStart);
                nextAufgussShown.add(key);
            }
        }
    }
}

// Scrollt zur naechsten Zeile.
function focusNextRow(row) {
    if (!row) return;
    const rect = row.getBoundingClientRect();
    const viewHeight = window.innerHeight || document.documentElement.clientHeight;
    if (rect.bottom > viewHeight || rect.top < 0) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Entfernt Next-Aufguss-Markierung.
function clearNextAufgussHighlight() {
    document.querySelectorAll('[data-start-ts]').forEach(row => row.classList.remove('next-aufguss-row'));
}

// Liest Next-Aufguss-Settings.
function getNextAufgussSettings(planId) {
    const enabledKey = `nextAufgussEnabled_${planId}`;
    const leadKey = `nextAufgussLeadSeconds_${planId}`;
    const highlightKey = `nextAufgussHighlightEnabled_${planId}`;
    const clockKey = `nextAufgussClockEnabled_${planId}`;
    const bannerEnabledKey = `nextAufgussBannerEnabled_${planId}`;
    const bannerModeKey = `nextAufgussBannerMode_${planId}`;
    const bannerTextKey = `nextAufgussBannerText_${planId}`;
    const bannerImageKey = `nextAufgussBannerImage_${planId}`;
    const bannerHeightKey = `nextAufgussBannerHeight_${planId}`;
    const bannerWidthKey = `nextAufgussBannerWidth_${planId}`;
    const themeColorKey = `nextAufgussThemeColor_${planId}`;

    const serverSettings = serverNextAufgussSettings.get(String(planId)) || null;
    const enabledStored = localStorage.getItem(enabledKey);
    const leadStored = localStorage.getItem(leadKey);
    const highlightStored = localStorage.getItem(highlightKey);
    const clockStored = localStorage.getItem(clockKey);
    const bannerEnabledStored = localStorage.getItem(bannerEnabledKey);
    const bannerModeStored = localStorage.getItem(bannerModeKey);
    const bannerTextStored = localStorage.getItem(bannerTextKey);
    const bannerImageStored = localStorage.getItem(bannerImageKey);
    const bannerHeightStored = localStorage.getItem(bannerHeightKey);
    const bannerWidthStored = localStorage.getItem(bannerWidthKey);
    const themeColorStored = localStorage.getItem(themeColorKey);
    const textColorStored = localStorage.getItem(`nextAufgussTextColor_${planId}`);

    const enabled = serverSettings && typeof serverSettings.enabled === 'boolean'
        ? serverSettings.enabled
        : (enabledStored === null ? true : enabledStored === 'true');
    const leadSeconds = serverSettings && Number.isFinite(Number(serverSettings.lead_seconds))
        ? Math.max(1, parseInt(serverSettings.lead_seconds, 10))
        : (leadStored ? Math.max(1, parseInt(leadStored, 10)) : 5);
    const highlightEnabled = serverSettings && typeof serverSettings.highlight_enabled === 'boolean'
        ? serverSettings.highlight_enabled
        : (highlightStored === null ? true : highlightStored === 'true');
    const clockEnabled = serverSettings && typeof serverSettings.clock_enabled === 'boolean'
        ? serverSettings.clock_enabled
        : (clockStored === null ? false : clockStored === 'true');
    const bannerEnabled = serverSettings && typeof serverSettings.banner_enabled === 'boolean'
        ? serverSettings.banner_enabled
        : (bannerEnabledStored === null ? false : bannerEnabledStored === 'true');
    const bannerMode = serverSettings && typeof serverSettings.banner_mode === 'string'
        ? serverSettings.banner_mode
        : (bannerModeStored === 'image' ? 'image' : 'text');
    const bannerText = serverSettings && typeof serverSettings.banner_text === 'string'
        ? serverSettings.banner_text
        : (bannerTextStored || '');
    const bannerImage = serverSettings && typeof serverSettings.banner_image === 'string'
        ? serverSettings.banner_image
        : (bannerImageStored || '');
    const bannerHeight = serverSettings && Number.isFinite(Number(serverSettings.banner_height))
        ? Math.max(40, parseInt(serverSettings.banner_height, 10))
        : (bannerHeightStored ? Math.max(40, parseInt(bannerHeightStored, 10)) : 160);
    const bannerWidth = serverSettings && Number.isFinite(Number(serverSettings.banner_width))
        ? Math.max(160, parseInt(serverSettings.banner_width, 10))
        : (bannerWidthStored ? Math.max(160, parseInt(bannerWidthStored, 10)) : 220);
    const themeColor = serverSettings && typeof serverSettings.theme_color === 'string'
        ? serverSettings.theme_color
        : (themeColorStored || '#ffffff');
    const textColor = serverSettings && typeof serverSettings.text_color === 'string'
        ? serverSettings.text_color
        : (textColorStored || '#111827');

    return {
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
    };
}

// Baut HTML fuer Next-Aufguss.
function buildNextAufgussHtml(aufguss) {
    const aufgussName = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerkeText = aufguss.staerke ? `Stärke: ${aufguss.staerke}` : 'Stärke: -';
    const saunaName = aufguss.sauna_name || aufguss.sauna || '-';
    const saunaTempText = formatSaunaTempText(aufguss);
    const saunaTempLine = saunaTempText ? `Temperatur: ${saunaTempText}\u00b0C` : 'Temperatur: -';
    const duftmittel = aufguss.duftmittel_name || aufguss.duftmittel || '-';
    const people = parseAufgiesserItems(aufguss);

    const personCards = people.map(person => {
        const name = person.name || 'Aufgießer';
        if (!person.image) {
            return `<div class="flex flex-col gap-2 text-center"><div class="text-sm font-semibold text-gray-900">${escapeHtml(name)}</div></div>`;
        }
        const img = buildImageTag(
            `uploads/${person.image}`,
            placeholderMitarbeiter,
            name,
            'w-full h-40 object-contain rounded-lg bg-gray-100'
        );
        return `<div class="flex flex-col gap-2 text-center"><div>${img}</div><div class="text-sm font-semibold text-gray-900">${escapeHtml(name)}</div></div>`;
    });

    const peopleClass = personCards.length >= 3
        ? 'flex flex-col gap-3 next-aufguss-people next-aufguss-people--stacked'
        : 'flex flex-col gap-3 next-aufguss-people';
    const mitarbeiterImg = personCards.length > 0
        ? `<div class="${peopleClass}">${personCards.join('')}</div>`
        : (aufguss.mitarbeiter_bild
            ? buildImageTag(
                `uploads/${aufguss.mitarbeiter_bild}`,
                placeholderMitarbeiter,
                'Aufgießer',
                'w-full h-72 object-contain rounded-lg bg-gray-100'
            )
            : `<div class="text-sm font-semibold text-gray-900 text-center">${escapeHtml(formatAufgiesser(aufguss))}</div>`);

    const saunaImg = aufguss.sauna_bild
        ? buildImageTag(
            `uploads/${aufguss.sauna_bild}`,
            placeholderSauna,
            saunaName,
            'w-full h-72 object-contain rounded-lg bg-gray-100'
        )
        : `<div class="text-sm font-semibold text-gray-900 text-center">${escapeHtml(saunaName)}</div>`;

    return `
        <div class="relative flex flex-col gap-4">
            <div class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none">
                <div class="text-8xl font-bold text-gray-900 bg-white/80 border border-white/80 rounded-full px-10 py-4 shadow-lg" id="next-aufguss-countdown">--</div>
            </div>
            <div class="relative z-10 flex flex-col gap-6 min-h-[70vh]">
                <div class="flex flex-col gap-2">
                    <div class="text-3xl font-bold text-gray-900">${escapeHtml(aufgussName)}</div>
                    <div class="text-lg font-semibold text-gray-900">${escapeHtml(staerkeText)}</div>
                    <div class="text-lg font-semibold text-gray-900">Duftmittel: ${escapeHtml(duftmittel)}</div>
                    <div class="text-lg font-semibold text-gray-900">${escapeHtml(saunaTempLine)}</div>
                </div>
                <div class="mt-auto grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex flex-col gap-2">
                        ${saunaImg}
                        <div class="text-sm font-semibold text-gray-900 text-center">Sauna: ${escapeHtml(saunaName)}</div>
                    </div>
                    <div class="flex flex-col gap-3">
                        ${mitarbeiterImg}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Zeigt Next-Aufguss-Popup.
function showNextAufgussPopup(aufguss, startTs) {
    if (nextAufgussActive && nextAufgussActivePlanId !== String(selectedPlanId)) {
        return;
    }

    nextAufgussActive = true;
    nextAufgussActivePlanId = String(selectedPlanId);

    const overlay = document.getElementById('next-aufguss-overlay');
    const body = document.getElementById('next-aufguss-body');
    if (!overlay || !body) return;
    body.innerHTML = buildNextAufgussHtml(aufguss);

    overlay.classList.remove('hidden');
    nextAufgussCountdownTarget = startTs;
    updateNextAufgussCountdown();

    if (nextAufgussCountdownTimer) {
        clearInterval(nextAufgussCountdownTimer);
    }
    nextAufgussCountdownTimer = setInterval(updateNextAufgussCountdown, 200);

    if (nextAufgussHideTimer) {
        clearTimeout(nextAufgussHideTimer);
        nextAufgussHideTimer = null;
    }
}

// Aktualisiert Countdown im Popup.
function updateNextAufgussCountdown() {
    const countdown = document.getElementById('next-aufguss-countdown');
    if (!countdown || !nextAufgussCountdownTarget) return;
    const diffMs = nextAufgussCountdownTarget - Date.now();
    if (diffMs <= 0) {
        countdown.textContent = 'Startet jetzt';
        if (!nextAufgussHideTimer) {
            nextAufgussHideTimer = setTimeout(hideNextAufgussPopup, 2000);
        }
        return;
    }
    const seconds = Math.ceil(diffMs / 1000);
    countdown.textContent = `${seconds}s`;
}

// Blendet Popup aus.
function hideNextAufgussPopup() {
    const overlay = document.getElementById('next-aufguss-overlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
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
}
