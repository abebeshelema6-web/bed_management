/**
 * Kuyu HBMS — Main JavaScript
 */
'use strict';

// ── Sidebar Toggle ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        // Restore saved state
        const collapsed = localStorage.getItem('sidebarCollapsed') === '1';
        if (window.innerWidth > 991) {
            if (collapsed) document.body.classList.add('sidebar-collapsed');
        }

        toggle.addEventListener('click', function () {
            if (window.innerWidth > 991) {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed',
                    document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
            } else {
                sidebar.classList.toggle('show');
            }
        });
    }

    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 991 && sidebar) {
            if (!sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });

    // ── Auto-dismiss alerts ───────────────────────────────────────────────────
    document.querySelectorAll('.alert-success, .alert-info').forEach(function (el) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(el);
            bsAlert.close();
        }, 5000);
    });

    // ── Confirm dialogs ───────────────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // ── Form loading state ────────────────────────────────────────────────────
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('[type=submit]');
            if (btn) {
                btn.disabled = true;
                const orig = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
                setTimeout(function () { btn.disabled = false; btn.innerHTML = orig; }, 15000);
            }
        });
    });

    // ── Print support ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // ── Bed map auto-refresh ──────────────────────────────────────────────────
    if (document.getElementById('bedMapContainer')) {
        setInterval(function () {
            const container = document.getElementById('bedMapContainer');
            if (!container) return;
            fetch('/kghbms/beds/map_partial.php' + window.location.search)
                .then(function (r) { return r.text(); })
                .then(function (html) { container.innerHTML = html; })
                .catch(function () {/* silent */});
        }, 30000); // refresh every 30 s
    }

    // ── Tooltip initialization ────────────────────────────────────────────────
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(function (el) { new bootstrap.Tooltip(el); });

    // ── Ward filter for bed map ───────────────────────────────────────────────
    const wardFilter = document.getElementById('wardFilter');
    if (wardFilter) {
        wardFilter.addEventListener('change', function () {
            const val = this.value;
            document.querySelectorAll('.bed-map-ward').forEach(function (el) {
                el.style.display = (!val || el.dataset.wardId === val) ? '' : 'none';
            });
        });
    }

    // ── Dynamic admission select → show patient ───────────────────────────────
    const admSelect = document.getElementById('admission_id');
    if (admSelect) {
        admSelect.addEventListener('change', function () {
            const opt = admSelect.options[admSelect.selectedIndex];
            const info = document.getElementById('patientInfo');
            if (info && opt.dataset.patient) {
                info.textContent = opt.dataset.patient;
                info.closest('.alert').classList.remove('d-none');
            }
        });
    }
});

// ── Chart helper ─────────────────────────────────────────────────────────────
function renderOccupancyChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    const labels = ['Available', 'Occupied', 'Reserved', 'Cleaning', 'Maintenance', 'Blocked', 'Out of Service'];
    const colors = ['#27ae60','#e74c3c','#f39c12','#2980b9','#8e44ad','#7f8c8d','#2c3e50'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: function(c) { return ' ' + c.label + ': ' + c.raw + ' beds'; }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

function renderWardChart(canvasId, labels, occupied, available) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Occupied', data: occupied, backgroundColor: '#e74c3c', borderRadius: 4 },
                { label: 'Available', data: available, backgroundColor: '#27ae60', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
}

function renderAdmissionTypeChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    const colors = ['#e74c3c', '#2980b9', '#f39c12', '#8e44ad', '#27ae60'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: labels.map(function(_, index) {
                    return colors[index % colors.length];
                }),
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                tooltip: {
                    callbacks: {
                        label: function(c) { return ' ' + c.label + ': ' + c.raw + ' admissions'; }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// ── Bed tile click → open detail modal ───────────────────────────────────────
function openBedDetail(bedId) {
    fetch('/kghbms/beds/detail_ajax.php?id=' + bedId)
        .then(function (r) { return r.text(); })
        .then(function (html) {
            let modal = document.getElementById('bedDetailModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'bedDetailModal';
                modal.className = 'modal fade';
                modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content" id="bedDetailContent"></div></div>';
                document.body.appendChild(modal);
            }
            document.getElementById('bedDetailContent').innerHTML = html;
            new bootstrap.Modal(modal).show();
        });
}
