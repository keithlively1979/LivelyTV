    </div><!-- /#content -->
</div><!-- /#main -->

<script>
// Shared drag-and-drop reorder for episode tables
function initDragSort(tableId, saveUrl) {
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if (!tbody) return;
    let dragged = null;

    tbody.addEventListener('dragstart', e => {
        dragged = e.target.closest('tr');
        if (dragged) {
            dragged.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });
    tbody.addEventListener('dragend', e => {
        const row = e.target.closest('tr');
        if (row) row.classList.remove('dragging');
        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
        if (saveUrl) saveOrder(tbody, saveUrl);
    });
    tbody.addEventListener('dragover', e => {
        e.preventDefault();
        const target = e.target.closest('tr');
        if (!target || target === dragged) return;
        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
        target.classList.add('drag-over');
        const rect = target.getBoundingClientRect();
        const mid  = rect.top + rect.height / 2;
        if (e.clientY < mid) {
            tbody.insertBefore(dragged, target);
        } else {
            tbody.insertBefore(dragged, target.nextSibling);
        }
    });
}

function saveOrder(tbody, url) {
    const ids = Array.from(tbody.querySelectorAll('tr[data-id]'))
                     .map(r => r.dataset.id);
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: ids })
    });
}

// Modal helpers
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open')
                .forEach(m => m.classList.remove('open'));
    }
});
</script>
</body>
</html>
