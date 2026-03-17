<?php
use yii\helpers\Html;
use yii\helpers\Url;
?>

<style>
.queues-page { margin: 20px; }
.health-bar  { display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
.health-card { flex:1; min-width:160px; border-radius:8px; padding:16px 20px; color:#fff; }
.health-card h3 { margin:0 0 4px; font-size:28px; font-weight:700; }
.health-card p  { margin:0; font-size:13px; opacity:.85; }
.hc-ok      { background: linear-gradient(135deg,#2e7d32,#43a047); }
.hc-warn    { background: linear-gradient(135deg,#e65100,#fb8c00); }
.hc-err     { background: linear-gradient(135deg,#b71c1c,#e53935); }
.hc-info    { background: linear-gradient(135deg,#1565c0,#1e88e5); }
.hc-neutral { background: linear-gradient(135deg,#546e7a,#78909c); }

.section-title { font-size:16px; font-weight:600; margin:24px 0 10px;
                 padding-bottom:6px; border-bottom:2px solid #eee; }
.section-title .badge { font-size:13px; margin-left:8px; vertical-align:middle; }

table.q-table { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:8px; }
table.q-table th { background:#f5f5f5; padding:7px 10px; text-align:left;
                   border-bottom:2px solid #ddd; white-space:nowrap; }
table.q-table td { padding:6px 10px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
table.q-table tr:hover td { background:#fafafa; }

.dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:5px; }
.dot-run  { background:#1e88e5; }
.dot-err  { background:#e53935; }
.dot-warn { background:#fb8c00; }
.dot-ok   { background:#43a047; }

.user-table    { width:100%; border-collapse:collapse; font-size:13px; }
.user-table th { background:#f5f5f5; padding:7px 10px; border-bottom:2px solid #ddd; text-align:left; }
.user-table td { padding:6px 10px; border-bottom:1px solid #f0f0f0; }
.user-table tr.problem-row td { background:#fff8f8; }
.type-chip { display:inline-block; padding:1px 7px; border-radius:10px; font-size:11px;
             margin:1px; background:#eee; color:#444; }
.type-chip.ok   { background:#e8f5e9; color:#2e7d32; }
.type-chip.err  { background:#ffebee; color:#b71c1c; }
.type-chip.warn { background:#fff3e0; color:#e65100; }
.type-chip.run  { background:#e3f2fd; color:#1565c0; }

.qs-section { min-height: 4px; }
.qs-spinner { display:inline-block; width:14px; height:14px; border:2px solid #ddd;
              border-top-color:#888; border-radius:50%; animation:qs-spin .6s linear infinite;
              vertical-align:middle; margin-right:6px; }
@keyframes qs-spin { to { transform:rotate(360deg); } }
</style>

<div class="queues-page">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <h2 style="margin:0;">Monitor kolejek</h2>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#999;">
                Odświeżono: <span id="qs-last-updated">—</span>
                <span id="qs-spinner" class="qs-spinner" style="display:none;"></span>
            </span>
            <?= Html::a('&larr; Lista użytkowników', Url::to(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
            <button id="qs-refresh-all"   class="btn btn-default btn-sm">↻ Odśwież wszystko</button>
            <button id="qs-auto-toggle"  class="btn btn-default btn-sm">⏸ Pauza</button>
            <?= Html::a('⚙ Zaplanuj kolejki', Url::to(['admin/prepare-queue-output']), [
                'class'  => 'btn btn-primary btn-sm',
                'target' => '_blank',
                'title'  => 'Uruchom Queue::prepareQueue dla wszystkich typów',
            ]) ?>
        </div>
    </div>

    <div id="qs-health"         class="qs-section"></div>
    <div id="qs-running"        class="qs-section"></div>
    <div id="qs-recent_hour"    class="qs-section"></div>
    <div id="qs-recent_started" class="qs-section"></div>
    <div id="qs-errors"         class="qs-section"></div>
    <div id="qs-disabled"       class="qs-section"></div>
    <div id="qs-overdue"        class="qs-section"></div>
    <div id="qs-users"          class="qs-section"></div>

</div>

<script>
(function () {
    const endpoint    = <?= json_encode(Url::to(['admin/queues-sections'])) ?>;
    const allSections = ['health','running','recent_hour','recent_started','errors','disabled','overdue','users'];
    const INTERVAL    = 30; // seconds

    const spinner     = document.getElementById('qs-spinner');
    const lastUpdated = document.getElementById('qs-last-updated');
    const toggleBtn   = document.getElementById('qs-auto-toggle');

    let autoActive    = true;
    let secondsLeft   = INTERVAL;
    let autoTimer     = null;
    let countdownTimer = null;

    // ── countdown spans ───────────────────────────────────────────
    function updateCountdowns() {
        document.querySelectorAll('.qs-countdown').forEach(el => {
            if (autoActive) {
                el.textContent = 'za ' + secondsLeft + 's';
                el.style.display = '';
            } else {
                el.textContent = '';
                el.style.display = 'none';
            }
        });
    }

    function resetCountdown() {
        secondsLeft = INTERVAL;
        updateCountdowns();
    }

    function startCountdownTick() {
        clearInterval(countdownTimer);
        countdownTimer = setInterval(() => {
            if (secondsLeft > 0) secondsLeft--;
            updateCountdowns();
        }, 1000);
    }

    // ── auto-refresh ──────────────────────────────────────────────
    function startAutoRefresh() {
        clearInterval(autoTimer);
        autoTimer = setInterval(() => loadSections(null), INTERVAL * 1000);
        autoActive = true;
        toggleBtn.textContent = '⏸ Pauza';
        resetCountdown();
        startCountdownTick();
    }

    function stopAutoRefresh() {
        clearInterval(autoTimer);
        clearInterval(countdownTimer);
        autoActive = false;
        toggleBtn.textContent = '▶ Auto-refresh';
        updateCountdowns();
    }

    // ── data loading ──────────────────────────────────────────────
    function showSpinner() { spinner.style.display = 'inline-block'; }
    function hideSpinner() { spinner.style.display = 'none'; }

    function loadSections(sections) {
        const param = (sections || allSections).join(',');
        showSpinner();

        fetch(endpoint + '?sections=' + encodeURIComponent(param), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.sections) {
                Object.entries(data.sections).forEach(([name, html]) => {
                    const el = document.getElementById('qs-' + name);
                    if (el) el.innerHTML = html;
                });
            }
            lastUpdated.textContent = new Date().toLocaleTimeString('pl-PL');
            // Reset countdown after every successful load (manual or auto)
            if (autoActive) resetCountdown();
        })
        .catch(err => console.error('Błąd ładowania sekcji:', err))
        .finally(hideSpinner);
    }

    // ── events ────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        // Per-section refresh
        const btn = e.target.closest('.qs-refresh-btn[data-section]');
        if (btn) {
            e.preventDefault();
            loadSections([btn.dataset.section]);
            return;
        }
        // Refresh all
        if (e.target.closest('#qs-refresh-all')) {
            e.preventDefault();
            loadSections(null);
            return;
        }
        // Start/stop toggle
        if (e.target.closest('#qs-auto-toggle')) {
            e.preventDefault();
            autoActive ? stopAutoRefresh() : startAutoRefresh();
        }
    });

    // ── init ──────────────────────────────────────────────────────
    loadSections(null);
    startAutoRefresh();
})();
</script>
