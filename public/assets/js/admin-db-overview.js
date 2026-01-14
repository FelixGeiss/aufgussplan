// Datenbank-Uebersicht: Tabs und Loeschen
function showTab(tabName) {
    // Verstecke alle Tab-Inhalte
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Entferne aktive Tab-Stile
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-indigo-500', 'text-indigo-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Zeige ausgewaehlten Tab-Inhalt
    document.getElementById('content-' + tabName).classList.remove('hidden');

    // Setze aktiven Tab-Stil
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tabName).classList.add('border-indigo-500', 'text-indigo-600');
}

// Umfragen-Tabelle filtern
function initUmfragenSearch() {
    const input = document.getElementById('umfragen-search');
    const table = document.querySelector('#content-umfragen table');
    if (!input || !table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    const filterRows = () => {
        const query = input.value.trim().toLowerCase();
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.classList.toggle('hidden', query !== '' && !text.includes(query));
        });
    };

    input.addEventListener('input', filterRows);
}

document.addEventListener('DOMContentLoaded', initUmfragenSearch);

// Loeschfunktion fuer Datenbank-Eintraege
function deleteDatenbankEintrag(type, id, name) {
    if (confirm('Moechten Sie wirklich "' + name + '" loeschen? Diese Aktion kann nicht rueckgaengig gemacht werden.')) {
        // Erstelle ein Formular fuer den DELETE-Request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../deletes/delete-entry.php';

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        typeInput.value = type;

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        form.appendChild(typeInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
