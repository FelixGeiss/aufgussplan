<?php
/**
 * Aufguss-Modell (Model)
 *
 * Diese Klasse repräsentiert die Geschäftslogik für Aufgüsse in unserem System.
 * Ein "Model" ist zuständig für:
 * - Datenbankoperationen (CRUD: Create, Read, Update, Delete)
 * - Datenvalidierung und -bereinigung
 * - Geschäftsregeln (z.B. automatische Erstellung von Duftmitteln/Saunen)
 *
 * Als Anfänger solltest du wissen:
 * - Models trennen die Datenbanklogik vom Rest der Anwendung
 * - Diese Klasse wird vom AufgussService verwendet
 * - Sie handhabt komplexe Operationen wie Datei-Uploads und automatische Datensätze
 *
 * Architektur: Model ↔ Service ↔ Controller (PHP-Seiten)
 */

// Datenbankverbindung einbinden (enthält auch die Database-Klasse)
require_once __DIR__ . '/../db/connection.php';

/**
 * Aufguss-Klasse für alle Aufguss-bezogenen Datenbankoperationen
 */
class Aufguss {
    /**
     * PDO-Datenbankverbindung
     * Wird im Konstruktor gesetzt
     */
    private $db;

    /**
     * Konstruktor: Datenbankverbindung herstellen
     *
     * Wird automatisch aufgerufen, wenn new Aufguss() verwendet wird.
     * Stellt sicher, dass jede Instanz eine Datenbankverbindung hat.
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Neuen Aufguss erstellen
     *
     * Diese Methode führt mehrere komplexe Schritte aus:
     * 1. Bilder hochladen (Sauna + Mitarbeiter)
     * 2. Formulardaten verarbeiten und bereinigen
     * 3. Daten in die aufguesse-Tabelle einfügen
     *
     * Beispiel-Aufruf:
     * $aufguss = new Aufguss();
     * $aufguss->create($_POST); // Daten aus Formular
     *
     * @param array $data - Formulardaten aus $_POST
     * @return bool - true bei Erfolg, false bei Fehler
     */
    public function create($data) {
        /**
         * SCHRITT 1: DATEN BEREINIGEN UND PRIORISIEREN
         *
         * Die processFormData-Methode ist sehr wichtig:
         * - Entscheidet, welche Werte Vorrang haben (Textfeld vs. Auswahl)
         * - Erstellt automatisch neue Duftmittel/Saunen/Mitarbeiter falls nötig
         * - Bereinigt und validiert alle Eingaben
         */
        $data = $this->processFormData($data);

        /**
         * SCHRITT 2: BILDER ZU DEN ENTITÄTEN ZUORDNEN
         *
         * Bilder gehören zu Saunen und Mitarbeitern, nicht zu Aufgüssen.
         * Wenn Bilder hochgeladen wurden, werden sie den entsprechenden Entitäten zugeordnet.
         */
        $this->handleEntityImages($data);

        /**
         * SCHRITT 3: AUFGUSS IN DATENBANK EINFÜGEN
         *
         * SQL-INSERT mit Prepared Statement für Sicherheit.
         * Die Bilder-Spalten wurden aus der aufguesse-Tabelle entfernt!
         */
        $sql = "INSERT INTO aufguesse
                (aufguss_name_id, datum, zeit, zeit_anfang, zeit_ende, duftmittel_id, sauna_id, mitarbeiter_id,
                 staerke, plan_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Prepared Statement vorbereiten
        $stmt = $this->db->prepare($sql);

        // Abfrage ausführen mit den bereinigten Daten
        $success = $stmt->execute([
            $data['aufguss_name_id'],        // ID des Aufgussnamens (kann NULL sein)
            $data['datum'] ?? date('Y-m-d'),  // Datum (heute falls nicht angegeben)
            $data['zeit'] ?? date('H:i'),     // Uhrzeit (jetzt falls nicht angegeben) - für Abwärtskompatibilität
            $data['zeit_anfang'] ?? $data['zeit'] ?? date('H:i'), // Anfangsuhrzeit
            $data['zeit_ende'] ?? null,       // Enduhrzeit (kann NULL sein)
            $data['duftmittel_id'],           // ID des Duftmittels (kann NULL sein)
            $data['sauna_id'],                // ID der Sauna (kann NULL sein)
            $data['mitarbeiter_id'],          // ID des Mitarbeiters (kann NULL sein)
            $data['staerke'],                 // Staerke (1-6)
            $data['plan_id'] ?? null          // ID des Plans (kann NULL sein)
        ]);

        if ($success) {
            $aufgussId = $this->db->lastInsertId();
            $this->saveAufgieserRelations(
                $aufgussId,
                $data['mitarbeiter_ids'] ?? [],
                $data['aufgieser_names'] ?? [],
                $data['mitarbeiter_id'] ?? null,
                $data['aufgieser_name'] ?? null
            );
        }

        return $success;
    }

    /**
     * Bilder zu den entsprechenden Entitäten zuordnen
     *
     * Diese Methode verarbeitet hochgeladene Bilder und ordnet sie den richtigen Entitäten zu:
     * - Sauna-Bilder werden in der saunen.bild Spalte gespeichert
     * - Mitarbeiter-Bilder werden in der mitarbeiter.bild Spalte gespeichert
     *
     * @param array $data - Bereinigte Formulardaten
     */
    private function handleEntityImages($data) {
        // SCHRITT 1: Sauna-Bild verarbeiten
        if (isset($_FILES['sauna_bild']) && $_FILES['sauna_bild']['error'] === UPLOAD_ERR_OK) {
            $saunaBild = $this->uploadImage($_FILES['sauna_bild'], 'sauna');

            if ($saunaBild && isset($data['sauna_id'])) {
                // Bild zur bestehenden Sauna hinzufügen/aktualisieren
                $this->updateSaunaBild($data['sauna_id'], $saunaBild);
            }
        }

        // SCHRITT 2: Mitarbeiter-Bild verarbeiten
        if (isset($_FILES['mitarbeiter_bild']) && $_FILES['mitarbeiter_bild']['error'] === UPLOAD_ERR_OK) {
            $mitarbeiterBild = $this->uploadImage($_FILES['mitarbeiter_bild'], 'mitarbeiter');

            if ($mitarbeiterBild && isset($data['mitarbeiter_id'])) {
                // Bild zum bestehenden Mitarbeiter hinzufügen/aktualisieren
                $this->updateMitarbeiterBild($data['mitarbeiter_id'], $mitarbeiterBild);
            }
        }

        // SCHRITT 3: Staerke-Bild hochladen (ohne DB-Zuordnung)
        if (isset($_FILES['staerke_bild']) && $_FILES['staerke_bild']['error'] === UPLOAD_ERR_OK) {
            $this->uploadImage($_FILES['staerke_bild'], 'staerke');
        }
    }

    /**
     * Formulardaten verarbeiten und bereinigen
     *
     * Diese Methode ist das "Herz" der Geschäftslogik. Sie entscheidet:
     * - Welche Eingabefelder Vorrang haben (Textfeld vs. Auswahl-Dropdown)
     * - Ob neue Datensätze automatisch erstellt werden sollen
     * - Wie die Daten für die Datenbank vorbereitet werden
     *
     * Das Formular hat für jeden Bereich zwei Möglichkeiten:
     * 1. Freitext-Eingabe (erstellt automatisch neue Einträge)
     * 2. Auswahl aus vorhandenen Einträgen (Dropdown)
     *
     * @param array $data - Rohdaten aus dem Formular ($_POST)
     * @return array - Bereinigte Daten für die Datenbank
     */
    private function processFormData($data) {
        /**
         * AUFGUSS-NAME: Textfeld hat Vorrang vor Auswahl
         *
         * Prioritaet:
         * 1. Freitext-Eingabe (aufguss_name)
         * 2. Auswahl aus vorhandenen Namen (aufguss_id / select_aufguss_id)
         * 3. NULL (kein Name)
         */
        $selectedAufgussId = $data['select_aufguss_id'] ?? ($data['aufguss_id'] ?? null);
        if (!empty($data['aufguss_name']) && $data['aufguss_name'] !== '') {
            $data['aufguss_name_id'] = $this->getOrCreateAufgussName($data['aufguss_name']);
        } elseif (!empty($selectedAufgussId)) {
            $data['aufguss_name_id'] = (int)$selectedAufgussId;
        } else {
            $data['aufguss_name_id'] = null;
        }
        /**
         * DUFTMITTEL: Automatische Erstellung neuer Einträge
         *
         * Wenn der Benutzer ein neues Duftmittel eingibt, wird es automatisch
         * in der duftmittel-Tabelle erstellt und die ID zurückgegeben.
         */
        if (!empty($data['duftmittel']) && $data['duftmittel'] !== '') {
            // Neues Duftmittel - automatisch erstellen oder vorhandenes finden
            $data['duftmittel_id'] = $this->getOrCreateDuftmittel($data['duftmittel']);
        } elseif (!empty($data['duftmittel_id']) && $data['duftmittel_id'] !== '') {
            // Vorhandenes Duftmittel ausgewählt
            $data['duftmittel_id'] = $data['duftmittel_id'];
        } else {
            // Kein Duftmittel angegeben
            $data['duftmittel_id'] = null;
        }

        $saunaTempRaw = $data['sauna_temperatur'] ?? null;
        $saunaTemp = null;
        if ($saunaTempRaw !== null && $saunaTempRaw !== '') {
            $saunaTempValue = (int)$saunaTempRaw;
            if ($saunaTempValue >= 0) {
                $saunaTemp = $saunaTempValue;
            }
        }
        $data['sauna_temperatur'] = $saunaTemp;

        /**
         * SAUNA: Automatische Erstellung neuer Einträge
         *
         * Gleiche Logik wie bei Duftmitteln - neue Saunen werden automatisch
         * in der saunen-Tabelle erstellt.
         */
        if (!empty($data['sauna']) && $data['sauna'] !== '') {
            // Neue Sauna - automatisch erstellen oder vorhandenes finden
            $data['sauna_id'] = $this->getOrCreateSauna($data['sauna'], $data['sauna_temperatur']);
        } elseif (!empty($data['sauna_id']) && $data['sauna_id'] !== '') {
            // Vorhandene Sauna ausgewählt
            $data['sauna_id'] = $data['sauna_id'];
        } else {
            // Keine Sauna angegeben
            $data['sauna_id'] = null;
        }
        if (!empty($data['sauna_id']) && $data['sauna_temperatur'] !== null) {
            $this->updateSaunaTemperatur($data['sauna_id'], $data['sauna_temperatur']);
        }

        /**
         * MITARBEITER/AUFGIESSER: Komplexeste Logik
         *
         * Hier gibt es drei Moeglichkeiten:
         * 1. Neuer Mitarbeiter (erstellt automatisch + speichert Name)
         * 2. Vorhandener Mitarbeiter ausgewaehlt (nur ID)
         * 3. Kein Mitarbeiter (nur Name als Text)
         */
        $multiMitarbeiter = $this->normalizeIdArray($data['mitarbeiter_ids'] ?? []);
        $multiNames = $this->normalizeNameList($data['aufgieser_names'] ?? '');

        if (!empty($multiMitarbeiter) || !empty($multiNames)) {
            $data['mitarbeiter_id'] = null;
            $data['aufgieser_name'] = null;
            $data['mitarbeiter_ids'] = $multiMitarbeiter;
            $data['aufgieser_names'] = $multiNames;

        } elseif (!empty($data['aufgieser']) && $data['aufgieser'] !== '') {
            // Neuer Mitarbeiter - automatisch erstellen und beide Felder fuellen
            $data['mitarbeiter_id'] = $this->getOrCreateMitarbeiter($data['aufgieser']);
            $data['aufgieser_name'] = $data['aufgieser']; // Name trotzdem speichern (fuer Anzeige)

        } elseif (!empty($data['mitarbeiter_id']) && $data['mitarbeiter_id'] !== '') {
            // Vorhandener Mitarbeiter ausgewaehlt - nur ID verwenden
            $data['aufgieser_name'] = null; // Name aus Datenbank holen wir spaeter
            $data['mitarbeiter_id'] = $data['mitarbeiter_id'];

        } else {
            // Keine Angabe - beide Felder NULL
            $data['aufgieser_name'] = null;
            $data['mitarbeiter_id'] = null;
        }

        /**
         * PLAN: Auswahl oder neuer Plan
         *
         * Hier gibt es drei Möglichkeiten:
         * 1. Neuer Plan (erstellt automatisch)
         * 2. Vorhandener Plan ausgewählt
         * 3. Kein Plan (NULL)
         */
        if (!empty($data['plan_name']) && $data['plan_name'] !== '') {
            // Neuer Plan - automatisch erstellen
            $data['plan_id'] = $this->createPlan([
                'name' => $data['plan_name'],
                'beschreibung' => $data['plan_beschreibung'] ?? null
            ]);

        } elseif (!empty($data['plan_id']) && $data['plan_id'] !== '') {
            // Vorhandener Plan ausgewählt
            $data['plan_id'] = $data['plan_id'];

        } else {
            // Kein Plan angegeben
            $data['plan_id'] = null;
        }

        /**
         * DATUM UND ZEIT: Verarbeitung
         *
         * Datum und Zeit werden aus dem Formular übernommen.
         * Falls nicht angegeben, wird das aktuelle Datum/Zeit verwendet.
         */
        if (!empty($data['datum'])) {
            $data['datum'] = $data['datum'];
        } else {
            $data['datum'] = date('Y-m-d');
        }

        if (!empty($data['zeit'])) {
            $data['zeit'] = $data['zeit'];
        } else {
            $data['zeit'] = date('H:i');
        }

        // Bereinigte Daten zurückgeben
        return $data;
    }

    /**
     * Aufgiesser-Zuordnung speichern (mehrere moeglich)
     */
    private function saveAufgieserRelations($aufgussId, $mitarbeiterIds, $names, $singleMitarbeiterId = null, $singleName = null) {
        $mitarbeiterIds = $this->normalizeIdArray($mitarbeiterIds);
        $names = $this->normalizeNameList($names);

        if (empty($mitarbeiterIds) && empty($names)) {
            if (!empty($singleMitarbeiterId)) {
                $mitarbeiterIds = [(int)$singleMitarbeiterId];
            } elseif (!empty($singleName)) {
                $names = [$singleName];
            }
        }

        if (empty($mitarbeiterIds) && empty($names)) {
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO aufguss_aufgieser (aufguss_id, mitarbeiter_id, name) VALUES (?, ?, ?)");
        foreach ($mitarbeiterIds as $mitarbeiterId) {
            $stmt->execute([$aufgussId, $mitarbeiterId, null]);
        }
        foreach ($names as $name) {
            $stmt->execute([$aufgussId, null, $name]);
        }
    }

    /**
     * IDs normalisieren
     */
    private function normalizeIdArray($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $clean = [];
        foreach ($ids as $id) {
            $val = (int)$id;
            if ($val > 0) {
                $clean[$val] = true;
            }
        }
        return array_keys($clean);
    }

    /**
     * Namen normalisieren (Komma/Zeilenumbruch getrennt)
     */
    private function normalizeNameList($names) {
        if (is_array($names)) {
            $list = $names;
        } else {
            $list = preg_split('/[\,\n;]/', (string)$names);
        }
        $clean = [];
        foreach ($list as $name) {
            $trimmed = trim($name);
            if ($trimmed !== '') {
                $clean[$trimmed] = true;
            }
        }
        return array_keys($clean);
    }

    /**
     * Sauna-Bild aktualisieren
     *
     * @param int $saunaId - ID der Sauna
     * @param string $bildPfad - Pfad zum Bild
     * @return bool - true bei Erfolg
     */
    private function updateSaunaBild($saunaId, $bildPfad) {
        try {
            $sql = "UPDATE saunen SET bild = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$bildPfad, $saunaId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Sauna-Temperatur aktualisieren
     *
     * @param int $saunaId - ID der Sauna
     * @param int $temperatur - Temperatur in C
     * @return bool - true bei Erfolg
     */
    private function updateSaunaTemperatur($saunaId, $temperatur) {
        try {
            $sql = "UPDATE saunen SET temperatur = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([(int)$temperatur, $saunaId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mitarbeiter-Bild aktualisieren
     *
     * @param int $mitarbeiterId - ID des Mitarbeiters
     * @param string $bildPfad - Pfad zum Bild
     * @return bool - true bei Erfolg
     */
    private function updateMitarbeiterBild($mitarbeiterId, $bildPfad) {
        try {
            $sql = "UPDATE mitarbeiter SET bild = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$bildPfad, $mitarbeiterId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Hilfsmethoden für automatische Datensatz-Erstellung
     *
     * Diese Methoden implementieren die "Get-or-Create" Logik:
     * 1. Prüfen, ob ein Datensatz mit diesem Namen bereits existiert
     * 2. Wenn ja: ID zurückgeben
     * 3. Wenn nein: Neuen Datensatz erstellen und ID zurückgeben
     *
     * Das verhindert doppelte Einträge und macht das System benutzerfreundlich.
     */

    /**
     * Aufguss-Namen anhand der ID finden
     *
     * Wird verwendet, wenn der Benutzer einen vorhandenen Aufguss auswählt.
     *
     * @param int $id - ID des Aufgusses
     * @return string|null - Name des Aufgusses oder null
     */
    private function getAufgussNameById($id) {
        try {
            $stmt = $this->db->prepare("SELECT name FROM aufguss_namen WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            return $result ? $result['name'] : null;
        } catch (Exception $e) {
            // Bei Datenbankfehlern: null zurueckgeben (nicht abstuerzen)
            return null;
        }
    }

    /**
     * Aufguss-Namen finden oder neu erstellen
     *
     * @param string $name - Name des Aufgusses
     * @return int - ID des Aufgussnamens
     */
    private function getOrCreateAufgussName($name) {
        $stmt = $this->db->prepare("SELECT id FROM aufguss_namen WHERE name = ?");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($temperatur !== null) {
                $this->updateSaunaTemperatur($existing['id'], $temperatur);
            }
            return $existing['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO aufguss_namen (name) VALUES (?)");
        $stmt->execute([$name]);
        return $this->db->lastInsertId();
    }

    /**
     * Duftmittel finden oder neu erstellen
     *
     * Beispiel: Benutzer gibt "Eukalyptus" ein
     * - Existiert bereits? → ID zurückgeben
     * - Existiert nicht? → Neuen Datensatz erstellen → ID zurückgeben
     *
     * @param string $name - Name des Duftmittels
     * @return int - ID des Duftmittels
     */
    private function getOrCreateDuftmittel($name) {
        // SCHRITT 1: Prüfen, ob Duftmittel bereits existiert
        $stmt = $this->db->prepare("SELECT id FROM duftmittel WHERE name = ?");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Datensatz existiert bereits - ID zurückgeben
            return $existing['id'];
        }

        // SCHRITT 2: Neuen Eintrag erstellen
        $stmt = $this->db->prepare("INSERT INTO duftmittel (name) VALUES (?)");
        $stmt->execute([$name]);

        // ID des neuen Datensatzes zurückgeben
        return $this->db->lastInsertId();
    }

    /**
     * Sauna finden oder neu erstellen
     *
     * Gleiche Logik wie bei Duftmitteln.
     * Beispiel: "Finnische Sauna", "Bio-Sauna", etc.
     *
     * @param string $name - Name der Sauna
     * @return int - ID der Sauna
     */
    private function getOrCreateSauna($name, $temperatur = null) {
        // Prüfen, ob Sauna bereits existiert
        $stmt = $this->db->prepare("SELECT id FROM saunen WHERE name = ?");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['id'];
        }

        // Neuen Eintrag erstellen
        $stmt = $this->db->prepare("INSERT INTO saunen (name, temperatur) VALUES (?, ?)");
        $stmt->execute([$name, $temperatur]);
        return $this->db->lastInsertId();
    }

    /**
     * Mitarbeiter finden oder neu erstellen
     *
     * Wichtig: Hier werden nur grundlegende Mitarbeiter erstellt.
     * Für detaillierte Mitarbeiter-Daten (Position, Bild, etc.) sollte
     * der Admin-Bereich verwendet werden.
     *
     * @param string $name - Name des Mitarbeiters
     * @return int - ID des Mitarbeiters
     */
    private function getOrCreateMitarbeiter($name) {
        // Prüfen, ob Mitarbeiter bereits existiert
        $stmt = $this->db->prepare("SELECT id FROM mitarbeiter WHERE name = ?");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['id'];
        }

        // Neuen Eintrag erstellen (nur mit Name)
        $stmt = $this->db->prepare("INSERT INTO mitarbeiter (name) VALUES (?)");
        $stmt->execute([$name]);
        return $this->db->lastInsertId();
    }

    /**
     * Bild hochladen und sicher speichern
     *
     * Diese Methode handhabt den sicheren Upload von Bildern:
     * 1. Prüft, ob ein gültiges Bild hochgeladen wurde
     * 2. Erstellt Upload-Verzeichnis falls nötig
     * 3. Generiert eindeutigen Dateinamen (verhindert Überschreibungen)
     * 4. Verschiebt Datei an sicheren Ort
     *
     * Sicherheit:
     * - Keine Ausführung von PHP-Code in Bildern möglich
     * - Eindeutige Dateinamen verhindern Überschreibungen
     * - Bilder werden außerhalb des webroot gespeichert
     *
     * @param array|null $file - Datei-Array aus $_FILES
     * @param string $type - Typ ('sauna' oder 'mitarbeiter')
     * @return string|null - Relativer Pfad zur Datei oder null bei Fehler
     */
    private function uploadImage($file, $type) {
        // SCHRITT 1: Prüfen, ob eine gültige Datei hochgeladen wurde
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null; // Keine Datei oder Upload-Fehler
        }

        // SCHRITT 2: Upload-Verzeichnis vorbereiten
        $uploadDir = UPLOAD_PATH . $type . '/';

        // Verzeichnis erstellen, falls es nicht existiert
        // 0755 = Lesen/Schreiben für Owner, Lesen für Group/Others
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // SCHRITT 3: Sicheren Dateinamen generieren
        // uniqid() erstellt eindeutige ID + Timestamp
        // basename() verhindert Directory-Traversal-Angriffe
        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $uploadDir . $filename;

        // SCHRITT 4: Datei an sicheren Ort verschieben
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Erfolg: Relativen Pfad zurückgeben (für Datenbank)
            return $type . '/' . $filename;
        }

        // Fehler beim Verschieben
        return null;
    }

    /**
     * Alle Aufgüsse mit verwandten Daten abrufen
     *
     * Diese Methode führt eine komplexe SQL-Abfrage aus, die Daten aus
     * mehreren Tabellen zusammenführt (JOIN).
     *
     * SQL-Logik:
     * - Haupttabelle: aufguesse (a)
     * - LEFT JOIN mitarbeiter (m) - für Mitarbeiter-Namen
     * - LEFT JOIN saunen (s) - für Sauna-Namen
     * - LEFT JOIN duftmittel (d) - für Duftmittel-Namen
     * - LEFT JOIN Pläene (p) - für Plan-Namen
     *
     * LEFT JOIN bedeutet: Auch Aufgüsse ohne Mitarbeiter/Sauna/etc. werden angezeigt
     *
     * @return array - Array mit allen Aufguss-Datensätzen
     */
    public function getAll() {
        $sql = "SELECT a.*,
                       an.name as name,
                       aa_list.aufgieser_namen,
                       aa_list.aufgieser_items,
                       m.name as mitarbeiter_name, m.bild as mitarbeiter_bild,
                       s.name as sauna_name, s.bild as sauna_bild, s.temperatur as sauna_temperatur,
                       d.name as duftmittel_name, d.bild as duftmittel_bild,
                       p.name as plan_name
                FROM aufguesse a
                LEFT JOIN (
                    SELECT aa.aufguss_id,
                           GROUP_CONCAT(COALESCE(m2.name, aa.name) ORDER BY aa.id SEPARATOR ', ') as aufgieser_namen,
                           GROUP_CONCAT(CONCAT(COALESCE(m2.name, aa.name), '||', IFNULL(m2.bild, '')) ORDER BY aa.id SEPARATOR ';;') as aufgieser_items
                    FROM aufguss_aufgieser aa
                    LEFT JOIN mitarbeiter m2 ON aa.mitarbeiter_id = m2.id
                    GROUP BY aa.aufguss_id
                ) aa_list ON aa_list.aufguss_id = a.id
                LEFT JOIN aufguss_namen an ON a.aufguss_name_id = an.id
                LEFT JOIN mitarbeiter m ON a.mitarbeiter_id = m.id
                LEFT JOIN saunen s ON a.sauna_id = s.id
                LEFT JOIN duftmittel d ON a.duftmittel_id = d.id
                LEFT JOIN plaene p ON a.plan_id = p.id
                ORDER BY a.zeit_anfang ASC";

        // Abfrage ausführen und alle Ergebnisse zurückgeben
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * ============================================================================
     * PLAN-VERWALTUNG
     * ============================================================================
     *
     * Diese Methoden verwalten die verschiedenen Aufguss-Pläne.
     * Pläne gruppieren Aufgüsse thematisch (z.B. "Wellness-Tag", "Power-Aufgüsse").
     */

    /**
     * Neuen Plan erstellen
     *
     * @param array $data - Daten für den neuen Plan ['name', 'beschreibung']
     * @return int - ID des neuen Plans
     */
    public function createPlan($data) {
        $sql = "INSERT INTO plaene (name, beschreibung) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['beschreibung'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Alle Pläne abrufen
     *
     * @return array - Array mit allen Plänen
     */
    public function getAllPlans() {
        $sql = "SELECT * FROM plaene ORDER BY erstellt_am DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Plan anhand der ID finden
     *
     * @param int $id - Plan-ID
     * @return array|null - Plan-Daten oder null
     */
    public function getPlanById($id) {
        $sql = "SELECT * FROM plaene WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Plan aktualisieren
     *
     * @param int $id - Plan-ID
     * @param array $data - Neue Daten ['name', 'beschreibung']
     * @return bool - true bei Erfolg
     */
    public function updatePlan($id, $data) {
        $sql = "UPDATE plaene SET name = ?, beschreibung = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['beschreibung'] ?? null,
            $id
        ]);
    }

    /**
     * Plan löschen
     *
     * @param int $id - Plan-ID
     * @return bool - true bei Erfolg
     */
    public function deletePlan($id) {
        // Zuerst alle Aufgüsse von diesem Plan entfernen (plan_id auf NULL setzen)
        $sql = "UPDATE aufguesse SET plan_id = NULL WHERE plan_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        // Dann den Plan löschen
        $sql = "DELETE FROM plaene WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Aufgüsse eines bestimmten Plans abrufen
     *
     * @param int $planId - Plan-ID
     * @return array - Array mit Aufgüssen des Plans
     */
    public function getAufgüsseByPlan($planId) {
        // Sicherstellen, dass planId numerisch und nicht null ist
        if (!is_numeric($planId) || $planId === null) {
            error_log('getAufgüsseByPlan: Invalid planId: ' . $planId);
            return [];
        }

        $sql = "SELECT a.*,
                       an.name as name,
                       aa_list.aufgieser_namen,
                       aa_list.aufgieser_items,
                       m.name as mitarbeiter_name, m.bild as mitarbeiter_bild,
                       s.name as sauna_name, s.bild as sauna_bild, s.temperatur as sauna_temperatur,
                       d.name as duftmittel_name, d.bild as duftmittel_bild,
                       p.name as plan_name
                FROM aufguesse a
                LEFT JOIN (
                    SELECT aa.aufguss_id,
                           GROUP_CONCAT(COALESCE(m2.name, aa.name) ORDER BY aa.id SEPARATOR ', ') as aufgieser_namen,
                           GROUP_CONCAT(CONCAT(COALESCE(m2.name, aa.name), '||', IFNULL(m2.bild, '')) ORDER BY aa.id SEPARATOR ';;') as aufgieser_items
                    FROM aufguss_aufgieser aa
                    LEFT JOIN mitarbeiter m2 ON aa.mitarbeiter_id = m2.id
                    GROUP BY aa.aufguss_id
                ) aa_list ON aa_list.aufguss_id = a.id
                LEFT JOIN aufguss_namen an ON a.aufguss_name_id = an.id
                LEFT JOIN mitarbeiter m ON a.mitarbeiter_id = m.id
                LEFT JOIN saunen s ON a.sauna_id = s.id
                LEFT JOIN duftmittel d ON a.duftmittel_id = d.id
                LEFT JOIN plaene p ON a.plan_id = p.id
                WHERE a.plan_id = ?
                ORDER BY a.zeit_anfang ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$planId]);
        return $stmt->fetchAll();
    }

    /**
     * Prüft, ob ein Aufguss existiert
     *
     * @param int $id - Aufguss-ID
     * @return bool - true wenn existiert
     */
    public function checkAufgussExists($id) {
        $sql = "SELECT COUNT(*) as count FROM aufguesse WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Aufguss löschen
     *
     * @param int $id - Aufguss-ID
     * @return bool - true bei Erfolg
     */
    public function deleteAufguss($id) {
        $sql = "DELETE FROM aufguesse WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>


