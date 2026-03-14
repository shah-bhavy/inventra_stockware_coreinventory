/* Initialize Lucide icons after every DOM update */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

/* ── Modal helpers ─────────────────────────────── */
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
}

/* Close on backdrop click */
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

/* ── Confirm destructive actions ───────────────── */
function confirmAction(msg) {
    return confirm(msg || 'Are you sure? This action cannot be undone.');
}

function confirmDelete(form, msg) {
    if (confirmAction(msg || 'Delete this record?')) form.submit();
    return false;
}

/* ── Dynamic item rows ─────────────────────────── */
let _rowIdx = 100;

function addItemRow(tbodyId, tplFn) {
    _rowIdx++;
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = tplFn(_rowIdx);
    tbody.appendChild(tr);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (row) row.remove();
}

/* ── Alert auto-dismiss ────────────────────────── */
setTimeout(function () {
    document.querySelectorAll('.alert[data-auto]').forEach(function (el) {
        el.style.transition = 'opacity 0.5s, margin 0.5s, padding 0.5s';
        el.style.opacity = '0';
        el.style.marginBottom = '0';
        el.style.padding = '0';
        setTimeout(function () { el.remove(); }, 600);
    });
}, 3500);

/* ── Live table search ─────────────────────────── */
function bindSearch(inputId, tableId) {
    const inp = document.getElementById(inputId);
    if (!inp) return;
    inp.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function (tr) {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

/* ── Fill edit modal from data attributes ─────── */
function fillModal(modalId, data) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    Object.entries(data).forEach(function ([k, v]) {
        const el = modal.querySelector('[name="' + k + '"]');
        if (el) el.value = v;
    });
    openModal(modalId);
}

/* ── Populate "system qty" in adjustment rows ─── */
function fetchSystemQty(selectEl, targetInputId) {
    const productId = selectEl.value;
    const locationId = document.querySelector('[name="location_id"]')
        ? document.querySelector('[name="location_id"]').value : '';
    if (!productId) return;
    fetch('api/stock.php?product_id=' + productId + '&location_id=' + locationId)
        .then(r => r.json())
        .then(d => {
            const el = document.getElementById(targetInputId);
            if (el) el.value = d.qty || 0;
        })
        .catch(() => {});
}
