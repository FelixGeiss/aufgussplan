# AufgussManager - Einsteigerfreundliche Anleitung

## Was ist dieses Projekt?

Ein **webbasiertes Verwaltungssystem** für Saunen, das Aufgusspläne erstellt und anzeigt. Es besteht aus zwei Bereichen:

### 🌐 Öffentlicher Bereich (für Gäste/TV)
- Zeigt den aktuellen Aufgussplan an
- Automatische Aktualisierung alle 30 Sekunden
- TV-freundliches Design (große Schrift, helle Farben)
- Vollbild-Modus möglich (F11 oder Strg+F)

### 🔐 Admin-Bereich (für Mitarbeiter)
- Aufgüsse planen und verwalten
- Mitarbeiter-Datenbank verwalten
- Bilder hochladen (Saunen, Mitarbeiter)
- Moderne Web-Oberfläche mit Tailwind CSS

## 🏗️ Wie funktioniert die Technik? (Einfach erklärt)

### Die Architektur (MVC-Pattern)
```
Browser → PHP-Seiten → Services → Models → Datenbank
```

- **PHP-Seiten** (Controller): Verarbeiten Browser-Anfragen
- **Services**: Geschäftslogik (Validierung, Sicherheit)
- **Models**: Datenbank-Zugriff (CRUD-Operationen)
- **Datenbank**: Speichert alle Informationen (MySQL)

### Welche Technologien werden verwendet?

| Technologie | Wofür? | Warum das? |
|-------------|--------|------------|
| **PHP 8.5+** | Server-seitige Programmierung | Sicher, schnell, weit verbreitet |
| **MySQL 8.4+** | Datenbank | Zuverlässig, kostenlos, standard |
| **Tailwind CSS** | Styling/Design | Schnell, modern, responsive |
| **JavaScript (ES6+)** | Interaktivität | AJAX, Timer, Drag & Drop |
| **PDO** | Datenbank-Verbindung | Sicher gegen SQL-Injection |

## 📁 Detaillierte Projektstruktur

```
AufgussManager/
├── 📂 public/               # 🌐 BROWSER-ZUGÄNGLICH (Web-Root)
│   ├── index.php            # 🏠 Öffentliche Aufgussplan-Anzeige
│   ├── test_db.php          # 🧪 Datenbank-Verbindung testen
│   ├── 📂 admin/            # 🔒 Admin-Bereich
│   │   ├── index.php        # 📊 Dashboard + Aufguss-Formular
│   │   ├── mitarbeiter.php  # 👥 Mitarbeiter verwalten
│   │   └── aufguesse.php    # 🕐 Aufgüsse planen
│   ├── 📂 assets/           # 🎨 Frontend-Dateien
│   │   ├── 📂 css/          # Zusätzliche Styles
│   │   └── 📂 js/           # JavaScript-Funktionen
│   ├── 📂 uploads/          # 📸 Hochgeladene Bilder
│   └── .htaccess            # ⚙️ Apache-Konfiguration
│
├── 📂 src/                  # 🚫 NICHT browser-zugänglich (Backend)
│   ├── 📂 config/           # 🔧 Konfiguration
│   │   └── config.php       # DB-Zugangsdaten, Pfade
│   ├── 📂 db/               # 💾 Datenbank
│   │   └── connection.php   # PDO-Verbindung + Hilfsfunktionen
│   ├── 📂 models/           # 📋 Daten-Modelle
│   │   └── aufguss.php      # Aufguss-Verwaltung (CRUD)
│   └── 📂 services/         # 🧠 Geschäftslogik
│       └── aufgussService.php # Validierung, Formularverarbeitung
│
├── 📂 node_modules/         # 📦 Node.js Dependencies (Tailwind)
├── package.json             # 📋 Node.js Konfiguration
├── tailwind.config.js       # 🎨 Tailwind CSS Setup
├── postcss.config.js        # 🔄 CSS-Verarbeitung
└── README.md                # 📖 Diese Anleitung
```

## 🚀 Installation - Schritt für Schritt

### Voraussetzungen prüfen
Bevor du beginnst, stelle sicher, dass du hast:
- ✅ **XAMPP** (oder ähnlicher Webserver mit Apache + MySQL + PHP)
- ✅ **PHP 8.5+** (in XAMPP enthalten)
- ✅ **MySQL 8.4+** (in XAMPP enthalten)
- ✅ **Node.js** (für Tailwind CSS)
- ✅ **Git** (optional, für Repository-Zugriff)

### Schritt 1: Projekt herunterladen
```bash
# In den XAMPP htdocs-Ordner wechseln
cd C:\xampp\htdocs

# Repository klonen (oder ZIP herunterladen und entpacken)
git clone [repository-url] AufgussManager

# Oder: ZIP-Datei herunterladen und nach C:\xampp\htdocs\ entpacken
```

### Schritt 2: Datenbank einrichten

#### 2.1 Datenbank erstellen
1. **XAMPP Control Panel öffnen** und **MySQL starten**
2. **phpMyAdmin öffnen**: http://localhost/phpmyadmin
3. **Neue Datenbank erstellen**:
   - Name: `aufgussplan`
   - Zeichensatz: `utf8mb4_unicode_ci`
4. **SQL-Datei importieren** (falls vorhanden):
   - Gehe zu "Importieren"
   - Wähle `database/schema.sql` aus dem Projekt

#### 2.2 Tabellen-Struktur verstehen
```sql
-- WICHTIGE TABELLEN:
CREATE TABLE mitarbeiter (
    id INT PRIMARY KEY AUTO_INCREMENT,  -- Eindeutige ID
    name VARCHAR(100) NOT NULL,         -- Name des Mitarbeiters
    position VARCHAR(100),              -- Job-Titel
    bild VARCHAR(255),                  -- Pfad zum Bild
    aktiv BOOLEAN DEFAULT TRUE          -- Aktiv/Inaktiv
);

CREATE TABLE aufguesse (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),                  -- Name des Aufgusses
    duftmittel_id INT,                  -- Verknüpfung zu Duftmitteln
    sauna_id INT,                       -- Verknüpfung zu Saunen
    aufgieser_name VARCHAR(255),        -- Name des Aufgießers
    mitarbeiter_id INT,                 -- Mitarbeiter-ID
    staerke INT,                        -- Stärke (1-6)
    sauna_bild VARCHAR(255),            -- Pfad zum Sauna-Bild
    mitarbeiter_bild VARCHAR(255),      -- Pfad zum Mitarbeiter-Bild
    datum DATE,                         -- Datum des Aufgusses
    zeit TIME,                          -- Uhrzeit
    dauer INT DEFAULT 15                -- Dauer in Minuten
);
```

### Schritt 3: Konfiguration anpassen

#### 3.1 config.php bearbeiten
```php
// src/config/config.php öffnen und anpassen:

// DATENBANK (XAMPP Standardwerte)
define('DB_HOST', 'localhost');
define('DB_NAME', 'aufgussplan');  // Deine Datenbank
define('DB_USER', 'root');         // XAMPP Standard
define('DB_PASS', '');             // Leer bei XAMPP

// URL (an deine Installation anpassen)
define('BASE_URL', 'http://localhost/AufgussManager/');
```

#### 3.2 Automatische Pfad-Konfiguration
Die folgenden Pfade werden automatisch berechnet - **nicht ändern**:
```php
define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('PUBLIC_PATH', ROOT_PATH . 'public' . DIRECTORY_SEPARATOR);
define('SRC_PATH', ROOT_PATH . 'src' . DIRECTORY_SEPARATOR);
```

### Schritt 4: Frontend-Abhängigkeiten installieren

#### 4.1 Node.js Dependencies
```bash
# Im Projekt-Verzeichnis:
cd C:\xampp\htdocs\AufgussManager

# Dependencies installieren
npm install

# Tailwind CSS kompilieren (Entwicklung)
npm run dev

# ODER: Einmalig kompilieren (Produktion)
npm run build
```

#### 4.2 Was macht das?
- **`npm install`**: Lädt Tailwind CSS und PostCSS herunter
- **`npm run dev`**: Überwacht CSS-Dateien und kompiliert automatisch
- **`npm run build`**: Erstellt optimierte CSS-Datei für Produktion

### Schritt 5: Webserver konfigurieren

#### 5.1 Apache mod_rewrite aktivieren
1. **XAMPP Control Panel** → **Apache** → **Config** → **httpd.conf**
2. Suche nach: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Entferne das `#` am Anfang der Zeile
4. **Apache neu starten**

#### 5.2 DocumentRoot setzen (Alternative)
Falls du nicht den ganzen htdocs verwenden willst:
1. **httpd.conf** öffnen
2. `DocumentRoot "C:/xampp/htdocs"` ändern zu:
   `DocumentRoot "C:/xampp/htdocs/AufgussManager/public"`
3. `DirectoryIndex index.php` hinzufügen

### Schritt 6: Installation testen

#### 6.1 Datenbankverbindung prüfen
Öffne im Browser: http://localhost/AufgussManager/test_db.php

**Erwartete Ausgabe:**
- ✅ Verbindung erfolgreich hergestellt
- ✅ Abfrage erfolgreich
- ✅ Tabellen existieren (oder Anleitung zum Erstellen)

#### 6.2 Anwendung testen
- **Öffentlicher Bereich**: http://localhost/AufgussManager/
- **Admin-Bereich**: http://localhost/AufgussManager/admin/

### Häufige Probleme und Lösungen

#### ❌ "Datenbankverbindung fehlgeschlagen"
**Lösung:**
- XAMPP MySQL ist gestartet?
- Datenbank `aufgussplan` existiert?
- config.php Zugangsdaten korrekt?

#### ❌ "Seite nicht gefunden" (404)
**Lösung:**
- mod_rewrite aktiviert?
- .htaccess-Datei vorhanden?
- Apache neu gestartet?

#### ❌ CSS/JavaScript lädt nicht
**Lösung:**
- `npm run build` ausgeführt?
- dist/style.css existiert?
- Browser-Cache geleert (Strg+F5)?

#### ❌ Bilder werden nicht hochgeladen
**Lösung:**
- uploads/-Verzeichnis beschreibbar?
- PHP upload_max_filesize = 10M?
- Dateityp erlaubt (jpg, png, gif)?

## 🎯 Verwendung - Wie benutzt man das System?

### 🌐 Öffentlicher Bereich (TV-Anzeige)

#### URL aufrufen
```
http://localhost/AufgussManager/
```

#### Was du siehst:
- **Aktuelle Aufgüsse** mit Uhrzeit und Mitarbeiter
- **Countdown-Timer** für laufende Aufgüsse (grün → gelb → rot)
- **Automatische Aktualisierung** alle 30 Sekunden
- **TV-optimierte Darstellung** (große Schrift, klare Farben)

#### Tastenkombinationen:
- **F11**: Vollbild-Modus für TV-Bildschirme
- **Strg+F**: Alternative Vollbild-Taste

#### Wie die Daten aktualisiert werden:
```javascript
// Aus app.js - Automatische Aktualisierung
setInterval(loadAufgussplan, 30000); // Alle 30 Sekunden

function loadAufgussplan() {
    fetch('api/aufguesse.php')        // API aufrufen
        .then(response => response.json())  // JSON empfangen
        .then(data => displayAufgussplan(data)); // Anzeigen
}
```

### 🔐 Admin-Bereich (Verwaltung)

#### URL aufrufen
```
http://localhost/AufgussManager/admin/
```

#### Verfügbare Funktionen:

#### 📊 Dashboard (admin/index.php)
**Hauptseite mit Aufguss-Formular**

**Aufguss erstellen:**
1. **Name eingeben** oder aus vorhandenen wählen
2. **Duftmittel** eingeben (neu) oder auswählen (bestehend)
3. **Sauna** eingeben (neu) oder auswählen (bestehend)
4. **Aufgießer** eingeben (neu) oder auswählen (bestehend)
5. **Stärke wählen** (1-6 Skala)
6. **Bilder hochladen** (optional, per Drag & Drop)
7. **Speichern** klicken

**Intelligente Funktionen:**
- **Automatische Erstellung**: Neue Duftmittel/Saunen/Mitarbeiter werden automatisch angelegt
- **Priorisierung**: Textfelder haben Vorrang vor Dropdown-Auswahlen
- **Validierung**: Pflichtfelder werden geprüft vor dem Speichern

#### 👥 Mitarbeiter verwalten (admin/mitarbeiter.php)
- **Neue Mitarbeiter hinzufügen** (Name, Position)
- **Liste aller Mitarbeiter** anzeigen
- **Bearbeiten/Löschen** (zukünftig)
- **Drag & Drop** für Profilbilder

#### 🕐 Aufgüsse planen (admin/aufguesse.php)
- **Datum auswählen** für verschiedene Tage
- **Aufgüsse hinzufügen** mit Uhrzeit und Details
- **Kalender-ähnliche Ansicht** der geplanten Aufgüsse
- **Bearbeiten/Löschen** vorhandener Aufgüsse

### 🔄 Arbeitsablauf verstehen

#### Beispiel: Neuen Aufguss planen
1. **Admin öffnet** http://localhost/AufgussManager/admin/
2. **Formular ausfüllen:**
   - Aufguss: "Wellness-Aufguss"
   - Duftmittel: "Eukalyptus-Minze" (neu - wird automatisch erstellt)
   - Sauna: "Finnische Sauna" (bestehend - aus Dropdown wählen)
   - Aufgießer: "Max Mustermann" (neu - wird automatisch erstellt)
   - Stärke: 4 (mittel-stark)
3. **Bilder hochladen** (optional)
4. **Speichern** klicken
5. **System verarbeitet:**
   ```php
   // Service-Schicht validiert Daten
   $errors = $this->validiereDaten($postData);

   // Model-Schicht speichert in Datenbank
   $this->aufgussModel->create($postData);

   // Bilder werden verarbeitet und gespeichert
   $this->uploadImage($_FILES['sauna_bild'], 'sauna');
   ```
6. **Öffentlicher Bereich** zeigt automatisch den neuen Aufguss

### 📱 Responsive Design
Das System funktioniert auf:
- **📺 TV-Bildschirmen** (öffentlicher Bereich, großes Format)
- **💻 Desktop-Computern** (Admin-Bereich, volle Funktionalität)
- **📱 Tablets** (angepasste Layouts)
- **📱 Smartphones** (mobile Optimierung)

## ✨ Features - Was kann das System?

### 🎨 Benutzeroberfläche
- **📱 Responsive Design**: Passt sich automatisch an Bildschirmgröße an
  - TV: Großformat für Fernseher
  - Desktop: Volle Funktionalität
  - Tablet/Mobile: Optimierte Layouts
- **🎯 Moderne UI**: Tailwind CSS für professionelles Aussehen
- **⚡ Performant**: Schnell ladende Seiten, optimierte Assets

### 🔄 Automatische Funktionen
- **⏰ Echtzeit-Aktualisierung**: Daten werden alle 30 Sekunden neu geladen
- **🎪 Animierte Aufgüsse**: Laufende Aufgüsse pulsieren und haben Timer
- **🎨 Farbcodierte Timer**: Grün → Gelb → Rot bei wenig Zeit
- **📺 Vollbild-Modus**: F11 für TV-Bildschirme

### 🛡️ Sicherheit & Stabilität
- **🔒 Geschützter Admin-Bereich**: Login-System (auskommentiert)
- **🛡️ CSRF-Schutz**: Verhindert Cross-Site-Request-Forgery-Angriffe
- **💉 SQL-Injection-Schutz**: PDO mit Prepared Statements
- **📁 Sichere Datei-Uploads**: Validierung, Umbenennung, Zugriffsrechte

### 🧠 Intelligente Features
- **🤖 Automatische Datensätze**: Neue Duftmittel/Saunen/Mitarbeiter werden automatisch erstellt
- **🎯 Priorisierung**: Text-Eingaben haben Vorrang vor Dropdown-Auswahlen
- **✅ Validierung**: Formulare prüfen Eingaben vor dem Speichern
- **🗂️ Kategorisierung**: Saunen, Duftmittel, Mitarbeiter werden wiederverwendet

### 📤 Datei-Uploads
- **🎯 Drag & Drop**: Einfaches Hochladen per Ziehen-und-Ablegen
- **🖼️ Bildoptimierung**: Automatische Verarbeitung und Speicherung
- **🔒 Sicherheit**: Dateityp-Prüfung, Größenbeschränkung
- **📂 Organisiert**: Separate Ordner für verschiedene Bildtypen

## 🛠️ Technologien - Detaillierte Übersicht

### Backend (Server-seitig)
| Technologie | Version | Zweck | Warum? |
|-------------|---------|-------|--------|
| **PHP** | 8.5+ | Serverseitige Programmierung | Sicher, schnell, OOP-fähig |
| **PDO** | - | Datenbank-Abstraktion | SQL-Injection-Schutz, Datenbank-unabhängig |
| **MySQL** | 8.4+ | Datenbank | Zuverlässig, standard, ACID-kompatibel |

### Frontend (Browser-seitig)
| Technologie | Version | Zweck | Warum? |
|-------------|---------|-------|--------|
| **HTML5** | - | Struktur | Semantisch, zugänglich, moderne APIs |
| **CSS3** | - | Styling | Responsive, Animationen, moderne Layouts |
| **JavaScript** | ES6+ | Interaktivität | AJAX, DOM-Manipulation, moderne Syntax |
| **Tailwind CSS** | 3.4+ | Utility-First CSS | Schnell, konsistent, responsive |
| **PostCSS** | 8.4+ | CSS-Verarbeitung | Autoprefixer, Optimierung |

### Build-Tools & Entwicklung
| Tool | Zweck | Befehl |
|------|-------|--------|
| **npm** | Package-Management | `npm install`, `npm run build` |
| **Tailwind CLI** | CSS-Kompilierung | `npx tailwindcss -i ./src/input.css -o ./public/dist/style.css` |
| **PostCSS** | CSS-Transformation | Automatische Verarbeitung |
| **Autoprefixer** | Browser-Kompatibilität | Vendor-Prefixes hinzufügen |

### Server & Deployment
| Komponente | Zweck | Konfiguration |
|------------|-------|---------------|
| **Apache** | Webserver | .htaccess für URL-Rewriting |
| **mod_rewrite** | URL-Umschreibung | Saubere URLs ohne .php |
| **mod_expires** | Caching | Browser-Caching für Performance |
| **mod_deflate** | Kompression | GZIP-Kompression |

### Architektur-Patterns
- **MVC**: Model-View-Controller für saubere Trennung
- **Singleton**: Datenbankverbindung (eine Instanz)
- **Service Layer**: Geschäftslogik zwischen Controller und Model
- **Prepared Statements**: Sichere Datenbankabfragen

## 💻 Entwicklung - Wie programmierst du mit?

### 🛠️ Entwicklungsumgebung einrichten

#### 1. Grundlegende Tools installieren
```bash
# PHP-Version prüfen
php -v  # Sollte 8.5+ zeigen

# Node.js-Version prüfen
node -v  # Sollte installiert sein

# npm-Version prüfen
npm -v   # Sollte installiert sein
```

#### 2. Projekt-Abhängigkeiten installieren
```bash
# PHP-Abhängigkeiten (falls Composer verwendet wird)
composer install

# Node.js-Abhängigkeiten (für Tailwind CSS)
npm install
```

#### 3. CSS kompilieren
```bash
# ENTWICKLUNG: Automatische Überwachung
npm run dev
# → Überwacht src/input.css und kompiliert bei Änderungen

# PRODUKTION: Einmaliger Build
npm run build
# → Erstellt optimierte, minifizierte CSS-Datei
```

### 📝 Code-Style und Best Practices

#### PHP-Konventionen (PSR-12)
```php
<?php
// Datei-Kopf mit Namespace
namespace App\Models;

// Klassenname in PascalCase
class Aufguss {
    // Eigenschaften in camelCase
    private $dbConnection;

    // Methoden in camelCase
    public function createAufguss($data) {
        // Logik hier
    }
}
```

#### CSS/HTML-Konventionen
```html
<!-- Semantische HTML-Elemente -->
<article class="aufguss-card">
    <header>
        <h2>Titel</h2>
    </header>
    <section>
        Inhalt
    </section>
</article>
```

```css
/* Tailwind Utility Classes */
.aufguss-card {
    @apply bg-white rounded-lg shadow-md p-6;
}

/* Zusätzliche Custom Styles nur wenn nötig */
.aufguss-card.current {
    animation: pulse 2s infinite;
}
```

#### JavaScript-Konventionen (ES6+)
```javascript
// Arrow Functions
const loadData = () => {
    fetch('/api/data')
        .then(response => response.json())
        .then(data => displayData(data))
        .catch(error => console.error(error));
};

// Template Literals
const html = `
    <div class="card">
        <h3>${data.title}</h3>
        <p>${data.description}</p>
    </div>
`;

// Destructuring
const { name, email } = user;
```

### 🔧 Häufige Entwicklungsaufgaben

#### CSS ändern
1. **`src/input.css`** bearbeiten (Tailwind-Direktiven)
2. **Custom Styles** in `public/assets/css/` hinzufügen
3. **`npm run dev`** laufen lassen für automatische Kompilierung
4. **Browser-Cache leeren** (Strg+F5) um Änderungen zu sehen

#### JavaScript debuggen
```javascript
// Konsolen-Ausgaben für Debugging
console.log('Variable:', variable);
console.table(array);  // Arrays als Tabelle

// Breakpoints setzen
debugger;  // Stoppt Ausführung im Browser-Dev-Tools

// Fehler behandeln
try {
    riskyOperation();
} catch (error) {
    console.error('Fehler:', error);
}
```

#### Datenbank-Entwicklung
```sql
-- Neue Tabelle erstellen
CREATE TABLE test_table (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Daten einfügen
INSERT INTO test_table (name) VALUES ('Test-Eintrag');

-- Daten abfragen
SELECT * FROM test_table WHERE name LIKE '%test%';
```

#### PHP debuggen
```php
// Variablen ausgeben (Entwicklung nur!)
var_dump($variable);
print_r($array);

// Fehler-Logging
error_log('Debug-Nachricht: ' . $variable);

// Browser-Ausgabe (temporär)
echo '<pre>';
print_r($data);
echo '</pre>';
```

### 📊 Testing und Qualitätssicherung

#### Datenbankverbindung testen
```
http://localhost/AufgussManager/test_db.php
```

#### Funktionale Tests
- **Öffentlicher Bereich**: Aufgüsse anzeigen
- **Admin-Bereich**: Formulare ausfüllen
- **Datei-Uploads**: Bilder hochladen
- **Responsive**: Verschiedene Bildschirmgrößen

#### Performance prüfen
- **Ladezeiten**: Browser-Dev-Tools → Network
- **CSS-Größe**: `npm run build` für Optimierung
- **Datenbank**: EXPLAIN für langsame Queries

### 🚀 Deployment (Veröffentlichung)

#### Für Produktionsserver:
1. **Konfiguration anpassen**:
   ```php
   // config.php
   define('BASE_URL', 'https://ihre-sauna.de/');
   define('DB_PASS', 'sicheres_passwort');
   ```

2. **Sicherheit aktivieren**:
   - Login-System in Admin-Bereich aktivieren
   - HTTPS erzwingen
   - Sicherheits-Header setzen

3. **Performance optimieren**:
   - `npm run build` für minifizierte CSS
   - Datenbank-Indexes prüfen
   - Caching aktivieren

4. **Backup-Strategie**:
   - Regelmäßige Datenbank-Backups
   - Datei-Backups (uploads/)
   - Konfigurations-Backups

## Datenbank-Schema

```sql
-- Mitarbeiter-Tabelle
CREATE TABLE mitarbeiter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    bild VARCHAR(255),
    aktiv BOOLEAN DEFAULT TRUE,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Aufgüsse-Tabelle
CREATE TABLE aufguesse (
    id INT PRIMARY KEY AUTO_INCREMENT,
    datum DATE NOT NULL,
    zeit TIME NOT NULL,
    mitarbeiter_id INT,
    beschreibung TEXT,
    dauer INT DEFAULT 15, -- in Minuten
    FOREIGN KEY (mitarbeiter_id) REFERENCES mitarbeiter(id)
);
```

## 🔒 Sicherheit - Wie sicher ist das System?

### Implementierte Sicherheitsmaßnahmen

#### 🛡️ Datenbank-Sicherheit
- **PDO Prepared Statements**: Verhindern SQL-Injection
- **Input-Filterung**: htmlspecialchars() gegen XSS
- **UTF8MB4-Encoding**: Unterstützt alle Sprachen

#### 🔐 Webserver-Sicherheit
- **.htaccess**: Sensible Dateien geschützt
- **CSRF-Schutz**: Tokens für Formulare (JavaScript)
- **Login-System**: Geschützter Admin-Bereich (auskommentiert)

#### 📁 Datei-Upload-Sicherheit
- **MIME-Type-Prüfung**: Nur erlaubte Dateitypen
- **Dateigröße-Limit**: Max. 5MB pro Bild
- **Umbenennung**: Eindeutige Dateinamen
- **Isolierter Speicher**: Uploads außerhalb Webroot

### Empfohlene zusätzliche Sicherheitsmaßnahmen

#### Für Produktionsbetrieb:
```php
// HTTPS erzwingen
if ($_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Sicherheits-Header
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'');
```

## 📚 Lernressourcen - Wie lerne ich die Technologien?

### PHP lernen
- **📖 Offizielle Dokumentation**: https://php.net/docs
- **🎓 PHP: The Right Way**: https://phptherightway.com
- **📚 Bücher**: "PHP 8 Objects, Patterns, and Practice"
- **🎥 YouTube**: "Traversy Media PHP Tutorials"

### MySQL/Datenbanken
- **📖 MySQL Docs**: https://dev.mysql.com/doc/
- **🎓 SQLZoo**: https://sqlzoo.net (interaktive Übungen)
- **📚 Bücher**: "SQL for Dummies"
- **🛠️ phpMyAdmin**: Für visuelles Datenbank-Management

### JavaScript (ES6+)
- **📖 MDN Web Docs**: https://developer.mozilla.org/de/docs/Web/JavaScript
- **🎓 freeCodeCamp**: https://www.freecodecamp.org/learn/javascript-algorithms-and-data-structures/
- **📚 Bücher**: "Eloquent JavaScript"
- **🎥 YouTube**: "JavaScript Mastery"

### Tailwind CSS
- **📖 Offizielle Docs**: https://tailwindcss.com/docs
- **🎓 Tailwind Play**: https://play.tailwindcss.com (Experimentieren)
- **📚 Bücher**: "Tailwind CSS: From Zero to Production"
- **🎥 YouTube**: "Tailwind Labs"

### Webentwicklung allgemein
- **🎓 freeCodeCamp**: Komplette Web-Entwicklungs-Kurse
- **📖 MDN Web Docs**: Umfassende Referenz
- **🛠️ Chrome DevTools**: Browser-Entwicklertools
- **💬 Stack Overflow**: Community für Fragen

## 🚀 Nächste Schritte - Was kannst du erweitern?

### Einfache Erweiterungen
- [ ] **Login-System aktivieren** (Admin-Bereich sichern)
- [ ] **Mehr Datenbanktabellen** (z.B. für Kunden, Buchungen)
- [ ] **API-Endpunkte** für mobile Apps
- [ ] **E-Mail-Benachrichtigungen** bei neuen Aufgüssen

### Fortgeschrittene Features
- [ ] **Kalender-Integration** (Google Calendar, Outlook)
- [ ] **Berichte & Statistiken** (Aufguss-Häufigkeit, Beliebtheit)
- [ ] **Mehrsprachigkeit** (i18n) für internationale Saunen
- [ ] **PWA-Features** (Offline-Fähigkeit, Push-Benachrichtigungen)

### Technische Verbesserungen
- [ ] **Unit-Tests** für PHP-Funktionen
- [ ] **Docker-Setup** für einfache Installation
- [ ] **Caching-System** (Redis, Memcached)
- [ ] **Backup-Automatisierung** für Datenbank

### Deployment-Optionen
- [ ] **Heroku** für einfaches Hosting
- [ ] **DigitalOcean** für VPS
- [ ] **AWS/Azure** für skalierbare Cloud-Lösungen
- [ ] **GitHub Pages** für statische Teile

## 📄 Lizenz

Dieses Projekt ist Open Source und steht unter der **MIT-Lizenz**.

Das bedeutet:
- ✅ **Kostenlos verwenden** für privat und kommerziell
- ✅ **Ändern und anpassen** wie gewünscht
- ✅ **Verteilen** an andere
- ❌ **Keine Garantie** (nutze auf eigenes Risiko)
- 📝 **Attribution**: Erwähne den ursprünglichen Autor

## 🆘 Support & Hilfe

### Bei Problemen:
1. **📋 Logs prüfen**: `logs/error.log` (falls vorhanden)
2. **🔍 Abhängigkeiten**: `npm install` und `composer install`
3. **💾 Datenbank**: `test_db.php` für Verbindungsprobleme
4. **🌐 Browser**: DevTools (F12) für JavaScript-Fehler

### Wo Hilfe bekommen:
- **🐛 Bugs melden**: GitHub Issues (falls Repository)
- **💬 Fragen stellen**: Stack Overflow, Reddit r/PHP
- **📚 Dokumentation**: Offizielle PHP/MySQL Docs
- **👥 Community**: PHP-Usergroups, Webdev-Foren

### Debug-Tipps:
```php
// PHP-Fehler anzeigen (Entwicklung)
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

```javascript
// JavaScript debuggen
console.log('Debug:', variable);
debugger; // Stoppt Ausführung
```

Ich habe viel mit Codex geschrieben.
