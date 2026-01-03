/**
 * HAUPT-JAVASCRIPT FR DIE FFENTLICHE AUFGUSSPLAN-ANZEIGE
 *
 * Dieses Script luft auf der ffentlichen index.php-Seite und ist zustndig fuer:
 * - Automatisches Laden von Aufgussdaten ber AJAX
 * - Regelmige Aktualisierung (alle 30 Sekunden)
 * - Darstellung der Daten im TV-freundlichen Format
 * - Vollbild-Modus fuer TV-Bildschirme
 * - Timer-Funktionalitt fuer laufende Aufguesse
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
    loadPlans();
    loadAufgussplan();
    initPlanChangeListener();
    initServerPlanSync();
    initNextAufgussTimer();
    loadStatsLogged();
    initVideoAutoplayUnlock();

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
let lastPlaene = [];
let selectedPlanId = null;
const selectedPlansStorageKey = 'aufgussplanSelectedPlan';
const planChangeStorageKey = 'aufgussplanPlanChanged';
const selectedPlanApiUrl = 'api/selected_plan.php';
const nextAufgussSettingsApiUrl = 'api/next_aufguss_settings.php';
let serverSelectedPlanId = null;
let serverPlanSyncInFlight = false;
let serverSelectedPlanUpdatedAt = null;
let serverNextAufgussSettings = new Map();
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

function loadPlans() {
    fetch('api/plaene.php')
        .then(response => response.json())
        .then(data => {
            const plaene = extractPlaene(data);
            lastPlaene = Array.isArray(plaene) ? plaene : [];
            ensureSelectedPlan(lastPlaene);
            renderFilteredAufguesse();
        })
        .catch(error => {
            console.error('Fehler beim Laden der Plaene:', error);
        });
}

function renderFilteredAufguesse() {
    const filtered = filterAufguesseByPlan(lastAufguesse);
    renderPlanView(selectedPlanId, lastPlaene, filtered);
}


function filterAufguesseByPlan(aufguesse) {
    if (!Array.isArray(aufguesse) || !selectedPlanId) {
        return aufguesse;
    }

    return aufguesse.filter(aufguss => String(aufguss.plan_id) === selectedPlanId);
}

function restoreSelectedPlans() {
    try {
        const stored = localStorage.getItem(selectedPlansStorageKey);
        selectedPlanId = stored ? String(stored) : null;
    } catch (error) {
        selectedPlanId = null;
    }
}

function saveSelectedPlan() {
    if (!selectedPlanId) {
        localStorage.removeItem(selectedPlansStorageKey);
        return;
    }
    localStorage.setItem(selectedPlansStorageKey, String(selectedPlanId));
}

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

function syncSelectedPlanIdFromServer() {
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

function initServerPlanSync() {
    syncSelectedPlanIdFromServer();
    setInterval(syncSelectedPlanIdFromServer, 5000);
}

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

function ensureSelectedPlan(plaene) {
    restoreSelectedPlans();
    if (!selectedPlanId && Array.isArray(plaene) && plaene.length > 0) {
        selectedPlanId = String(plaene[0].id);
        saveSelectedPlan();
    }
}

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

function extractPlaene(payload) {
    if (payload && payload.data && Array.isArray(payload.data.plaene)) {
        return payload.data.plaene;
    }

    if (payload && Array.isArray(payload.plaene)) {
        return payload.plaene;
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
            if (!selectedPlanId && lastPlaene.length > 0) {
                ensureSelectedPlan(lastPlaene);
            }
            renderFilteredAufguesse();
        })
        // Fehler behandeln
        .catch(error => {
            console.error('Fehler beim Laden des Aufgussplans:', error);
            showError('Fehler beim Laden der Daten');
        });
}

/**
 * AUFGUSSPLAN DARSTELLEN
 *
 * Wandelt die JSON-Daten vom Server in HTML um.
 * Findet den aktuell laufenden Aufguss und zeigt alle Aufguesse an.
 *
 * @param {Array} aufguesse - Array mit Aufguss-Objekten aus der API
 */
function displayAufgussplan(aufguesse) {
    // HTML-Container fuer den Aufgussplan finden
    const container = document.getElementById('aufgussplan');

    // LEERE LISTE: Keine Aufguesse vorhanden
    if (!aufguesse || aufguesse.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">Keine Aufguesse geplant fuer heute.</p>';
        return; // Funktion beenden
    }

    // AKTUELLE ZEIT fuer Vergleiche
    const now = new Date();
    let currentAufguss = null;

    /**
     * AKTUELLEN AUFGUSS FINDEN
     *
     * Durchsucht alle Aufguesse und findet heraus, welcher gerade luft.
     * Ein Aufguss "luft" von startZeit bis startZeit + Dauer.
     */
    for (let aufguss of aufguesse) {
        // Startzeit des Aufgusses als Date-Objekt
        const aufgussTime = new Date(aufguss.datum + ' ' + aufguss.zeit);

        // Endzeit = Startzeit + Dauer (Standard: 15 Minuten)
        const nextAufgussTime = new Date(aufgussTime.getTime() + (aufguss.dauer || 15) * 60000);

        // Prfen, ob aktuelle Zeit zwischen Start und Ende liegt
        if (now >= aufgussTime && now <= nextAufgussTime) {
            currentAufguss = aufguss;
            break; // Ersten passenden Aufguss nehmen
        }
    }

    /**
     * HTML GENERIEREN
     *
     * Erstellt fuer jeden Aufguss eine Karte mit Zeit, Mitarbeiter, etc.
     * Der aktuelle Aufguss bekommt eine spezielle CSS-Klasse.
     */
    let html = '<div class="space-y-4">'; // Container mit Abstnden

    aufguesse.forEach((aufguss, index) => {
        // Prfen, ob dies der aktuelle Aufguss ist
        const isCurrent = currentAufguss && currentAufguss.id === aufguss.id;

        // CSS-Klassen: Normal oder hervorgehoben
        const classes = isCurrent ? 'aufguss-card current' : 'aufguss-card';

        // HTML fuer eine Aufguss-Karte generieren
        html += `
            <div class="${classes}">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold">${aufguss.zeit}</h3>
                        <p class="text-lg">${aufguss.mitarbeiter_name}</p>
                        ${aufguss.beschreibung ? `<p class="text-sm opacity-90 mt-2">${aufguss.beschreibung}</p>` : ''}
                    </div>
                    ${isCurrent ? '<div class="timer" id="timer">Laeuft...</div>' : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';

    // Generiertes HTML in den Container einfgen
    container.innerHTML = html;

    /**
     * TIMER STARTEN
     *
     * Wenn ein Aufguss luft, wird ein Countdown-Timer gestartet.
     */
    if (currentAufguss) {
        startTimer(currentAufguss);
    }
}

function initPlanChangeListener() {
    window.addEventListener('storage', event => {
        if (!event || !event.key) return;
        if (event.key !== selectedPlansStorageKey && event.key !== planChangeStorageKey) return;
        restoreSelectedPlans();
        loadPlans();
        loadAufgussplan();
    });
}

function renderPlanView(planId, plaene, aufguesse) {
    const container = document.getElementById('aufgussplan');
    const hideHeader = !!(container && container.dataset && container.dataset.hidePlanHeader === 'true');
    const plan = Array.isArray(plaene)
        ? plaene.find(item => String(item.id) === String(planId))
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
    const backgroundImage = plan.hintergrund_bild ? `uploads/${plan.hintergrund_bild}` : '';
    const adMediaPath = plan.werbung_media ? `uploads/${plan.werbung_media}` : '';
    const adMediaType = plan.werbung_media_typ || '';
    const planSettings = getNextAufgussSettings(plan.id);
    const clockEnabled = !!(planSettings && planSettings.clockEnabled);
    const clockClass = clockEnabled ? ' plan-view-with-clock' : '';
    const clockHtml = clockEnabled
        ? `
            <div class="plan-clock" id="plan-clock">
                <div class="plan-clock-time">--:--</div>
                <div class="plan-clock-date">--.--.----</div>
            </div>
        `
        : '';

    applyPlanBackground(backgroundImage);

    let tableHtml = '';
    if (!aufguesse || aufguesse.length === 0) {
        tableHtml = `
            <div class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg bg-white/70">
                <div class="text-4xl mb-2">?</div>
                <p class="text-lg font-medium">Noch keine Aufguesse in diesem Plan</p>
            </div>
        `;
    } else if (hideHeader) {
        const rows = aufguesse.map(aufguss => renderPlanRowDiv(aufguss)).join('');
        tableHtml = `
            <div class="plan-list">
                <div class="plan-list-head">
                    <div class="plan-list-cell">Zeit</div>
                    <div class="plan-list-cell">Aufguss</div>
                    <div class="plan-list-cell">Staerke</div>
                    <div class="plan-list-cell">Aufgiesser</div>
                    <div class="plan-list-cell">Sauna</div>
                    <div class="plan-list-cell">Duftmittel</div>
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
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Staerke</th>
                            <th class="px-6 py-4 text-lg font-bold text-black-500 uppercase tracking-wider border-b sticky top-0 z-20 bg-white">Aufgiesser</th>
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
            <div class="relative rounded-lg overflow-hidden${clockClass}">
                ${backgroundImage ? `<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${backgroundImage}');"></div>` : ''}
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
        setupPlanAd(plan, adMediaPath, adMediaType);
        initPlanClock(clockEnabled ? document.getElementById('plan-clock') : null);
        updateNextAufgussIndicators();
        return;
    }

    container.innerHTML = `
        <div class="bg-white rounded-lg shadow-md relative">
            <div class="relative p-6">
                ${headerHtml}
                <div class="relative rounded-lg overflow-hidden${clockClass}">
                    ${backgroundImage ? `<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${backgroundImage}');"></div>` : ''}
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

    setupPlanAd(plan, adMediaPath, adMediaType);
    initPlanClock(clockEnabled ? document.getElementById('plan-clock') : null);
    updateNextAufgussIndicators();
}

function applyPlanBackground(imagePath) {
    const body = document.body;
    if (!body) return;
    if (imagePath) {
        body.style.backgroundImage = `url('${imagePath}')`;
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

function setupPlanAd(plan, adMediaPath, adMediaType) {
    const enabled = !!plan.werbung_aktiv;
    const intervalMinutes = Number(plan.werbung_interval_minuten) || 10;
    const durationSeconds = Number(plan.werbung_dauer_sekunden) || 10;
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

    activeAdPlanId = String(plan.id);
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

function formatClockTime(value) {
    return value.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

function formatClockDate(value) {
    return value.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function updatePlanClock(clockEl) {
    if (!clockEl) return;
    const now = new Date();
    const timeEl = clockEl.querySelector('.plan-clock-time');
    const dateEl = clockEl.querySelector('.plan-clock-date');
    if (timeEl) timeEl.textContent = formatClockTime(now);
    if (dateEl) dateEl.textContent = formatClockDate(now);
}

function initPlanClock(clockEl) {
    if (planClockTimer) {
        clearInterval(planClockTimer);
        planClockTimer = null;
    }
    if (!clockEl) return;
    updatePlanClock(clockEl);
    planClockTimer = setInterval(() => updatePlanClock(clockEl), 1000);
}

function resumePlanAdVideo() {
    const wrap = document.getElementById('plan-ad-wrap');
    if (!wrap || !wrap.classList.contains('is-visible')) return;
    const video = wrap.querySelector('video');
    if (!video) return;
    if (video.paused || video.ended) {
        tryPlayPlanAdVideo(video, false);
    }
}

function showPlanAd(mediaPath, mediaType, durationMs) {
    const wrap = document.getElementById('plan-ad-wrap');
    const media = document.getElementById('plan-ad-media');

    if (!wrap || !media) return;

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

function hidePlanAd() {
    const wrap = document.getElementById('plan-ad-wrap');
    if (wrap) {
        wrap.classList.remove('is-visible');
    }
}

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

function renderPlanRow(aufguss) {
    const timeText = formatAufgussTime(aufguss);
    const nameText = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerke = formatStaerke(aufguss.staerke);
    const aufgiesserHtml = formatAufgiesserHtml(aufguss);
    const saunaHtml = formatSaunaHtml(aufguss);
    const duftmittel = aufguss.duftmittel_name || aufguss.duftmittel || '-';
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
            <td class="px-6 py-4 whitespace-normal break-words text-sm text-gray-900">${escapeHtml(duftmittel)}</td>
        </tr>
    `;
}

function renderPlanRowDiv(aufguss) {
    const timeParts = formatAufgussTimeParts(aufguss);
    const nameText = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerke = formatStaerke(aufguss.staerke);
    const aufgiesserHtml = formatAufgiesserHtml(aufguss);
    const saunaHtml = formatSaunaHtmlStacked(aufguss);
    const duftmittel = aufguss.duftmittel_name || aufguss.duftmittel || '-';
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
            <div class="plan-list-cell text-sm text-gray-900">${escapeHtml(duftmittel)}</div>
        </div>
    `;
}

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

function formatSaunaHtmlStacked(aufguss) {
    const name = aufguss.sauna_name || aufguss.sauna || '-';
    const image = aufguss.sauna_bild || '';
    const tempBadge = formatSaunaTempBadge(aufguss);
    if (!image) {
        const tempText = formatSaunaTempText(aufguss);
        const tempSuffix = tempText ? ` <span class="text-xs text-gray-500">(${escapeHtml(tempText)}&deg;C)</span>` : '';
        return `<div class="plan-media-stack"><span>${escapeHtml(name)}${tempSuffix}</span></div>`;
    }
    return `
        <div class="plan-media-stack">
            <div class="relative">
                <img src="uploads/${image}" alt="${escapeHtml(name)}" class="h-12 w-12 rounded-full object-cover border border-gray-200">
                ${tempBadge}
            </div>
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}</span>
        </div>
    `;
}

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

function formatTime(value) {
    if (!value) return '--:--';
    const parts = String(value).split(':');
    if (parts.length >= 2) {
        return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
    }
    return value;
}

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

function formatPlanDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}.${month}.${date.getFullYear()}`;
}

function formatStaerke(value) {
    const level = Number(value) || 0;
    const map = {
        1: { text: '1 - Sehr leicht', bgClass: 'bg-green-100', textClass: 'text-green-800' },
        2: { text: '2 - Leicht', bgClass: 'bg-green-200', textClass: 'text-green-800' },
        3: { text: '3 - Mittel', bgClass: 'bg-yellow-100', textClass: 'text-yellow-800' },
        4: { text: '4 - Stark', bgClass: 'bg-orange-100', textClass: 'text-orange-800' },
        5: { text: '5 - Stark+', bgClass: 'bg-red-100', textClass: 'text-red-800' },
        6: { text: '6 - Extrem', bgClass: 'bg-red-200', textClass: 'text-red-900' }
    };
    return map[level] || { text: 'Unbekannt', bgClass: 'bg-gray-100', textClass: 'text-gray-800' };
}

function formatAufgiesser(aufguss) {
    if (aufguss.aufgieser_namen) {
        return aufguss.aufgieser_namen;
    }
    return aufguss.mitarbeiter_name || aufguss.aufgieser_name || 'Unbekannt';
}

function formatAufgiesserHtml(aufguss) {
    const people = parseAufgiesserItems(aufguss);
    if (!people.length) {
        return escapeHtml(formatAufgiesser(aufguss));
    }

    const cards = people.map(person => {
        const name = person.name || 'Unbekannt';
        if (person.image) {
            return `
                <div class="flex flex-col items-center">
                    <img src="uploads/${person.image}" alt="${escapeHtml(name)}" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                    <div class="mt-2 text-sm font-bold text-gray-900 text-center">${escapeHtml(name)}</div>
                </div>
            `;
        }
        const initial = name.trim() ? name.trim().charAt(0).toUpperCase() : '?';
        return `
            <div class="flex flex-col items-center">
                <div class="h-10 w-10 bg-gray-300 rounded-full flex items-center justify-center">
                    <span class="text-gray-700 font-semibold text-sm">${escapeHtml(initial)}</span>
                </div>
                <div class="mt-2 text-sm font-bold text-gray-900 text-center">${escapeHtml(name)}</div>
            </div>
        `;
    });

    return `<div class="flex flex-wrap justify-center gap-4 w-full">${cards.join('')}</div>`;
}

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

function formatSaunaHtml(aufguss) {
    const name = aufguss.sauna_name || aufguss.sauna || '-';
    const image = aufguss.sauna_bild || '';
    const tempBadge = formatSaunaTempBadge(aufguss);
    if (!image) {
        const tempText = formatSaunaTempText(aufguss);
        return tempText ? `${escapeHtml(name)} <span class="text-xs text-gray-500">(${escapeHtml(tempText)}&deg;C)</span>` : escapeHtml(name);
    }
    return `
        <div class="flex items-center gap-3">
            <div class="relative">
                <img src="uploads/${image}" alt="${escapeHtml(name)}" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                ${tempBadge}
            </div>
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}</span>
        </div>
    `;
}

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

function formatSaunaTempBadge(aufguss) {
    const tempText = formatSaunaTempText(aufguss);
    if (!tempText) return '';
    return `<span class="absolute -top-1 -right-8 bg-white text-sm leading-none px-3 py-1.5 rounded-full border border-gray-200 text-gray-700">${escapeHtml(tempText)}&deg;C</span>`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
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

function getStatsKey(aufguss) {
    const datum = aufguss.datum || '';
    return `${aufguss.id}:${datum}`;
}

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

function saveStatsLogged() {
    try {
        localStorage.setItem(statsLoggedStorageKey, JSON.stringify(Array.from(statsLogged)));
    } catch (error) {
        // ignore
    }
}

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

function initNextAufgussTimer() {
    if (nextAufgussTimer) return;
    nextAufgussTimer = setInterval(updateNextAufgussIndicators, 1000);
}

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

function focusNextRow(row) {
    if (!row) return;
    const rect = row.getBoundingClientRect();
    const viewHeight = window.innerHeight || document.documentElement.clientHeight;
    if (rect.bottom > viewHeight || rect.top < 0) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function clearNextAufgussHighlight() {
    document.querySelectorAll('[data-start-ts]').forEach(row => row.classList.remove('next-aufguss-row'));
}

function getNextAufgussSettings(planId) {
    const enabledKey = `nextAufgussEnabled_${planId}`;
    const leadKey = `nextAufgussLeadSeconds_${planId}`;
    const highlightKey = `nextAufgussHighlightEnabled_${planId}`;
    const clockKey = `nextAufgussClockEnabled_${planId}`;

    const serverSettings = serverNextAufgussSettings.get(String(planId)) || null;
    const enabledStored = localStorage.getItem(enabledKey);
    const leadStored = localStorage.getItem(leadKey);
    const highlightStored = localStorage.getItem(highlightKey);
    const clockStored = localStorage.getItem(clockKey);

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

    return {
        enabled,
        leadSeconds,
        highlightEnabled,
        clockEnabled
    };
}

function buildNextAufgussHtml(aufguss) {
    const aufgussName = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerkeText = aufguss.staerke ? `Staerke: ${aufguss.staerke}` : 'Staerke: -';
    const saunaName = aufguss.sauna_name || aufguss.sauna || '-';
    const saunaTempText = formatSaunaTempText(aufguss);
    const saunaTempLine = saunaTempText ? `Temperatur: ${saunaTempText}\u00b0C` : 'Temperatur: -';
    const duftmittel = aufguss.duftmittel_name || aufguss.duftmittel || '-';
    const people = parseAufgiesserItems(aufguss);

    const personCards = people.map(person => {
        const name = person.name || 'Aufgiesser';
        const img = person.image
            ? `<img src="uploads/${person.image}" alt="${escapeHtml(name)}" class="w-full h-40 object-contain rounded-lg bg-gray-100">`
            : `<div class="w-full h-40 rounded-lg bg-gray-100 flex items-center justify-center text-xs text-gray-500">Kein Bild</div>`;
        return `<div class="flex flex-col gap-2 text-center"><div>${img}</div><div class="text-sm font-semibold text-gray-900">${escapeHtml(name)}</div></div>`;
    });

    const mitarbeiterImg = personCards.length > 0
        ? `<div class="flex flex-col gap-3">${personCards.join('')}</div>`
        : (aufguss.mitarbeiter_bild
            ? `<img src="uploads/${aufguss.mitarbeiter_bild}" alt="Aufgiesser" class="w-full h-72 object-contain rounded-lg bg-gray-100">`
            : `<div class="w-full h-72 rounded-lg bg-gray-100 flex items-center justify-center text-sm text-gray-500">Kein Aufgiesser-Bild</div>`);

    const saunaBadge = saunaTempText
        ? `<span class="absolute -top-2 -right-8 bg-white text-sm leading-none px-3 py-1.5 rounded-full border border-gray-200 text-gray-700">${escapeHtml(saunaTempText)}&deg;C</span>`
        : '';
    const saunaImg = aufguss.sauna_bild
        ? `<div class="relative">${saunaBadge}<img src="uploads/${aufguss.sauna_bild}" alt="${escapeHtml(saunaName)}" class="w-full h-72 object-contain rounded-lg bg-gray-100"></div>`
        : `<div class="w-full h-72 rounded-lg bg-gray-100 flex items-center justify-center text-sm text-gray-500">Kein Sauna-Bild</div>`;

    return `
        <div class="relative flex flex-col gap-4">
            <div class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none">
                <div class="text-8xl font-bold text-gray-900 bg-white/80 border border-white/80 rounded-full px-10 py-4 shadow-lg" id="next-aufguss-countdown">--</div>
            </div>
            <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-6 min-h-[70vh]">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <div class="text-3xl font-bold text-gray-900">${escapeHtml(aufgussName)}</div>
                        <div class="text-lg text-gray-600">${escapeHtml(staerkeText)}</div>
                        <div class="text-lg text-gray-600">Duftmittel: ${escapeHtml(duftmittel)}</div>
                        <div class="text-lg text-gray-600">${escapeHtml(saunaTempLine)}</div>
                    </div>
                    <div class="flex flex-col gap-2">
                        ${saunaImg}
                        <div class="text-sm font-semibold text-gray-900 text-center">Sauna: ${escapeHtml(saunaName)}</div>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    ${mitarbeiterImg}
                </div>
            </div>
        </div>
    `;
}

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
    }
    nextAufgussHideTimer = setTimeout(hideNextAufgussPopup, 10000);
}

function updateNextAufgussCountdown() {
    const countdown = document.getElementById('next-aufguss-countdown');
    if (!countdown || !nextAufgussCountdownTarget) return;
    const diffMs = nextAufgussCountdownTarget - Date.now();
    if (diffMs <= 0) {
        countdown.textContent = 'Startet jetzt';
        return;
    }
    const seconds = Math.ceil(diffMs / 1000);
    countdown.textContent = `${seconds}s`;
}

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
