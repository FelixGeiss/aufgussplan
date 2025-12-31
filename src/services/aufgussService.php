<?php
/**
 * Aufguss-Service-Klasse
 *
 * Ein "Service" ist eine Zwischenschicht zwischen den PHP-Seiten (Controllern)
 * und den Models. Services sind zuständig für:
 * - Geschäftslogik (Business Logic)
 * - Datenvalidierung
 * - Koordination zwischen verschiedenen Models
 * - Fehlerbehandlung
 * - Transaktionen (mehrere Datenbankoperationen als Einheit)
 *
 * Als Anfänger solltest du wissen:
 * - Services trennen die Geschäftslogik von der Darstellung (PHP-Seiten)
 * - Services können von verschiedenen Stellen wiederverwendet werden
 * - Services geben strukturierte Ergebnisse zurück (Arrays mit success/errors)
 * - Diese Architektur macht den Code wartbarer und testbarer
 *
 * Architektur: Controller → Service → Model → Datenbank
 */

// Aufguss-Model einbinden (enthält auch die Datenbankverbindung)
require_once __DIR__ . '/../models/aufguss.php';

/**
 * Service-Klasse für alle Aufguss-bezogenen Operationen
 *
 * Behandelt die komplette Geschäftslogik rund um Aufgüsse:
 * - Formularverarbeitung
 * - Validierung
 * - Fehlerbehandlung
 * - Koordination mit dem Aufguss-Model
 */
class AufgussService {
    /**
     * Instanz des Aufguss-Models
     * Wird für alle Datenbankoperationen verwendet
     */
    private $aufgussModel;

    /**
     * Konstruktor: Model initialisieren
     *
     * Wird automatisch aufgerufen, wenn new AufgussService() verwendet wird.
     * Stellt sicher, dass der Service Zugriff auf das Model hat.
     */
    public function __construct() {
        $this->aufgussModel = new Aufguss();
    }

    /**
     * Formulardaten verarbeiten (Hauptmethode)
     *
     * Diese Methode wird von den Admin-PHP-Seiten aufgerufen, wenn ein
     * Aufguss-Formular abgesendet wird. Sie führt alle nötigen Schritte aus:
     * 1. Daten validieren
     * 2. Bei Fehlern: Validierungsfehler zurückgeben
     * 3. Bei Erfolg: Daten an Model weitergeben
     * 4. Erfolgs-/Fehlermeldung zurückgeben
     *
     * Beispiel-Aufruf aus admin/index.php:
     * $service = new AufgussService();
     * $result = $service->verarbeiteFormular($_POST, $_FILES);
     *
     * @param array $postData - Daten aus $_POST (Formularfelder)
     * @param array $filesData - Daten aus $_FILES (hochgeladene Dateien)
     * @return array - Strukturiertes Ergebnis mit success/message/errors
     */
    public function verarbeiteFormular($postData, $filesData = []) {
        /**
         * SCHRITT 1: DATEN VALIDIEREN
         *
         * Vor dem Speichern prüfen, ob alle erforderlichen Felder ausgefüllt sind.
         * validiereDaten() gibt ein Array mit Fehlermeldungen zurück.
         */
        $errors = $this->validiereDaten($postData);

        if (!empty($errors)) {
            // Validierungsfehler gefunden - Formular nicht verarbeiten
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        /**
         * SCHRITT 2: DATEN SPEICHERN
         *
         * Alle Validierungen bestanden - Daten an das Model weitergeben.
         * Das Model kümmert sich um die Details (Bilder, automatische Erstellung, etc.)
         */
        try {
            // Aufguss erstellen (Model-Methode)
            $this->aufgussModel->create($postData);

            // Erfolg zurückgeben
            return [
                'success' => true,
                'message' => 'Aufguss erfolgreich gespeichert!'
            ];

        } catch (Exception $e) {
            // Datenbankfehler oder andere Exceptions abfangen
            return [
                'success' => false,
                'errors' => ['Datenbankfehler: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Daten validieren
     *
     * Prüft, ob die Endzeit nicht vor der Anfangszeit liegt.
     * Alle anderen Felder sind optional.
     *
     * @param array $data - Zu validierende Daten
     * @return array - Array mit Fehlermeldungen
     */
    private function validiereDaten($data) {
        $errors = [];

        // Zeit-Validierung: Endzeit darf nicht vor Anfangszeit liegen
        if (!empty($data['zeit_anfang']) && !empty($data['zeit_ende'])) {
            $zeitAnfang = strtotime($data['zeit_anfang']);
            $zeitEnde = strtotime($data['zeit_ende']);

            if ($zeitEnde < $zeitAnfang) {
                $errors[] = 'Die Endzeit darf nicht vor der Anfangszeit liegen.';
            }
        }

        // Array mit Fehlermeldungen zurückgeben
        return $errors;
    }

    /**
     * Aufguss aktualisieren
     *
     * Diese Methode wird von der API für Inline-Bearbeitungen verwendet.
     *
     * @param int $aufgussId - ID des zu aktualisierenden Aufgusses
     * @param array $data - Neue Daten
     * @return array - Ergebnis mit success/error
     */
    public function updateAufguss($aufgussId, $data) {
        try {
            // Daten verarbeiten (ähnlich wie bei create)
            $data = $this->processFormData($data);

            // Datenbank-Update durchführen
            $sql = "UPDATE aufguesse SET
                        aufguss_name_id = ?,
                        datum = ?,
                        zeit = ?,
                        zeit_anfang = ?,
                        zeit_ende = ?,
                        duftmittel_id = ?,
                        sauna_id = ?,
                        mitarbeiter_id = ?,
                        staerke = ?,
                        plan_id = ?
                    WHERE id = ?";

            $stmt = $this->aufgussModel->db->prepare($sql);
            $success = $stmt->execute([
                $data['aufguss_name_id'],
                $data['datum'] ?? date('Y-m-d'),
                $data['zeit'] ?? date('H:i'),
                $data['zeit_anfang'] ?? null,
                $data['zeit_ende'] ?? null,
                $data['duftmittel_id'],
                $data['sauna_id'],
                $data['mitarbeiter_id'],
                $data['staerke'],
                $data['plan_id'] ?? null,
                $aufgussId
            ]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Aufguss erfolgreich aktualisiert'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Datenbank-Update fehlgeschlagen'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()
            ];
        }
    }
}
?>
