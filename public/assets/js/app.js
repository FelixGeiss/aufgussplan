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
    initNextAufgussTimer();

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
let planAdIntervalId = null;
let planAdHideTimeout = null;
let activeAdPlanId = null;
let aufgussById = new Map();
let nextAufgussTimer = null;
let nextAufgussShown = new Set();
let nextAufgussHideTimer = null;
let nextAufgussCountdownTimer = null;
let nextAufgussCountdownTarget = null;
let nextAufgussActive = false;
let nextAufgussActivePlanId = null;
let nextAufgussPlanId = null;

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
            <div class="relative rounded-lg overflow-hidden">
                ${backgroundImage ? `<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${backgroundImage}');"></div>` : ''}
                <div class="relative">
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
        updateNextAufgussIndicators();
        return;
    }

    container.innerHTML = `
        <div class="bg-white rounded-lg shadow-md relative">
            <div class="relative p-6">
                ${headerHtml}
                <div class="relative rounded-lg overflow-hidden">
                    ${backgroundImage ? `<div class="absolute inset-0 bg-cover bg-center" style="background-image: url('${backgroundImage}');"></div>` : ''}
                    <div class="relative">
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

    clearPlanAdTimers();

    if (!enabled || !adMediaPath) {
        activeAdPlanId = null;
        return;
    }

    activeAdPlanId = String(plan.id);
    const intervalMs = Math.max(1, intervalMinutes) * 60000;
    schedulePlanAd(intervalMs, durationSeconds * 1000, adMediaPath, adMediaType);
}

function schedulePlanAd(intervalMs, durationMs, mediaPath, mediaType) {
    planAdIntervalId = setInterval(() => {
        showPlanAd(mediaPath, mediaType, durationMs);
    }, intervalMs);
}

function showPlanAd(mediaPath, mediaType, durationMs) {
    const wrap = document.getElementById('plan-ad-wrap');
    const media = document.getElementById('plan-ad-media');

    if (!wrap || !media) return;

    if (mediaType === 'video') {
        media.innerHTML = `<video src="${mediaPath}" class="plan-ad-asset" autoplay muted playsinline loop></video>`;
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
    if (!image) {
        return `<div class="plan-media-stack"><span>${escapeHtml(name)}</span></div>`;
    }
    return `
        <div class="plan-media-stack">
            <img src="uploads/${image}" alt="${escapeHtml(name)}" class="h-12 w-12 rounded-full object-cover border border-gray-200">
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
    if (!image) {
        return escapeHtml(name);
    }
    return `
        <div class="flex items-center gap-3">
            <img src="uploads/${image}" alt="${escapeHtml(name)}" class="h-10 w-10 rounded-full object-cover border border-gray-200">
            <span class="text-sm font-bold text-gray-900">${escapeHtml(name)}</span>
        </div>
    `;
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

    let nextRow = null;
    let nextStart = null;
    rows.forEach(row => {
        const startTs = Number(row.getAttribute('data-start-ts') || 0);
        if (!startTs || startTs <= now) return;
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
        const key = `${selectedPlanId}:${aufgussId}`;
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

    const enabledStored = localStorage.getItem(enabledKey);
    const leadStored = localStorage.getItem(leadKey);
    const highlightStored = localStorage.getItem(highlightKey);

    const enabled = enabledStored === null ? true : enabledStored === 'true';
    const leadSeconds = leadStored ? Math.max(1, parseInt(leadStored, 10)) : 5;
    const highlightEnabled = highlightStored === null ? true : highlightStored === 'true';

    return {
        enabled,
        leadSeconds,
        highlightEnabled
    };
}

function buildNextAufgussHtml(aufguss) {
    const aufgussName = aufguss.name || aufguss.aufguss_name || 'Aufguss';
    const staerkeText = aufguss.staerke ? `Staerke: ${aufguss.staerke}` : 'Staerke: -';
    const saunaName = aufguss.sauna_name || aufguss.sauna || '-';
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

    const saunaImg = aufguss.sauna_bild
        ? `<img src="uploads/${aufguss.sauna_bild}" alt="${escapeHtml(saunaName)}" class="w-full h-72 object-contain rounded-lg bg-gray-100">`
        : `<div class="w-full h-72 rounded-lg bg-gray-100 flex items-center justify-center text-sm text-gray-500">Kein Sauna-Bild</div>`;

    return `
        <div class="relative flex flex-col gap-4">
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="text-8xl font-bold text-gray-900 bg-white/80 border border-white/80 rounded-full px-10 py-4 shadow-lg" id="next-aufguss-countdown">--</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 min-h-[70vh]">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <div class="text-3xl font-bold text-gray-900">${escapeHtml(aufgussName)}</div>
                        <div class="text-lg text-gray-600">${escapeHtml(staerkeText)}</div>
                        <div class="text-lg text-gray-600">Duftmittel: ${escapeHtml(duftmittel)}</div>
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
