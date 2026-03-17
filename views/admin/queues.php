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

/* ── sticky section nav ── */
#qs-sidenav {
    position: fixed;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    z-index: 900;
    background: rgba(44,62,80,.92);
    border-radius: 8px 0 0 8px;
    padding: 6px 0;
    box-shadow: -2px 0 12px rgba(0,0,0,.18);
    min-width: 34px;
    transition: min-width .2s;
}
#qs-sidenav:hover { min-width: 160px; }
#qs-sidenav a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px 5px 10px;
    color: rgba(255,255,255,.6);
    text-decoration: none;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    transition: color .15s, background .15s;
    border-left: 3px solid transparent;
}
#qs-sidenav a:hover   { color: #fff; background: rgba(255,255,255,.08); }
#qs-sidenav a.active  { color: #fff; border-left-color: #3498db; background: rgba(52,152,219,.15); }
#qs-sidenav a .sn-dot { flex-shrink: 0; width: 8px; height: 8px; border-radius: 50%; }
#qs-sidenav a .sn-lbl { opacity: 0; transition: opacity .2s; font-weight: 500; }
#qs-sidenav:hover a .sn-lbl { opacity: 1; }
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

    <nav id="qs-sidenav" aria-label="Sekcje"></nav>

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
    const endpoint      = <?= json_encode(Url::to(['admin/queues-sections'])) ?>;
    const saveEndpoint  = <?= json_encode(Url::to(['admin/save-queues-autorefresh'])) ?>;
    const csrfToken     = <?= json_encode(Yii::$app->request->csrfToken) ?>;
    const allSections   = ['health','running','recent_hour','recent_started','errors','disabled','overdue','users'];
    const INTERVAL      = 30;
    const savedStates   = <?= json_encode($initialStates) ?>;   // null or {section: bool, ...}

    const spinner         = document.getElementById('qs-spinner');
    const lastUpdated     = document.getElementById('qs-last-updated');
    const globalToggleBtn = document.getElementById('qs-auto-toggle');

    // Per-section state — init from DB-saved value, default true
    const states = {};
    allSections.forEach(s => {
        const active = savedStates && (s in savedStates) ? savedStates[s] : true;
        states[s] = { active, secondsLeft: INTERVAL };
    });

    // ── persist ───────────────────────────────────────────────────
    let saveTimer = null;
    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const payload = {};
            allSections.forEach(s => { payload[s] = states[s].active; });
            fetch(saveEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=' + encodeURIComponent(csrfToken)
                    + '&states=' + encodeURIComponent(JSON.stringify(payload)),
            }).catch(() => {});
        }, 600);
    }

    // ── DOM updaters ──────────────────────────────────────────────
    function updateSection(s) {
        const st = states[s];

        const cdEl = document.querySelector('.qs-countdown[data-section="' + s + '"]');
        if (cdEl) {
            if (st.active) {
                cdEl.textContent = 'za ' + st.secondsLeft + 's';
                cdEl.style.display = '';
            } else {
                cdEl.textContent = '';
                cdEl.style.display = 'none';
            }
        }

        const tbEl = document.querySelector('.qs-section-toggle[data-section="' + s + '"]');
        if (tbEl) {
            tbEl.textContent = st.active ? '⏸' : '▶';
            tbEl.title       = st.active ? 'Zatrzymaj auto-refresh tej sekcji' : 'Wznów auto-refresh tej sekcji';
            tbEl.style.color = st.active ? '#888' : '#1e88e5';
        }
    }

    function updateGlobalBtn() {
        const anyActive = allSections.some(s => states[s].active);
        globalToggleBtn.textContent = anyActive ? '⏸ Pauza' : '▶ Auto-refresh';
    }

    function updateAll() {
        allSections.forEach(updateSection);
        updateGlobalBtn();
    }

    // ── single global ticker ──────────────────────────────────────
    setInterval(() => {
        allSections.forEach(s => {
            const st = states[s];
            if (!st.active) return;
            st.secondsLeft--;
            if (st.secondsLeft <= 0) {
                st.secondsLeft = INTERVAL;
                loadSections([s]);   // fires load, resets secondsLeft inside
            }
        });
        updateAll();
    }, 1000);

    // ── data loading ──────────────────────────────────────────────
    function showSpinner() { spinner.style.display = 'inline-block'; }
    function hideSpinner() { spinner.style.display = 'none'; }

    function loadSections(sections) {
        const names = sections || allSections;
        const param = names.join(',');
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
                    // Reset this section's countdown after successful load
                    if (states[name]) states[name].secondsLeft = INTERVAL;
                });
            }
            lastUpdated.textContent = new Date().toLocaleTimeString('pl-PL');
            updateAll();
        })
        .catch(err => console.error('Błąd ładowania sekcji:', err))
        .finally(hideSpinner);
    }

    // ── events ────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        // Per-section manual refresh
        const refreshBtn = e.target.closest('.qs-refresh-btn[data-section]');
        if (refreshBtn) {
            e.preventDefault();
            loadSections([refreshBtn.dataset.section]);
            return;
        }

        // Per-section auto-refresh toggle
        const sectionToggle = e.target.closest('.qs-section-toggle[data-section]');
        if (sectionToggle) {
            e.preventDefault();
            const s = sectionToggle.dataset.section;
            states[s].active = !states[s].active;
            if (states[s].active) states[s].secondsLeft = INTERVAL;
            updateSection(s);
            updateGlobalBtn();
            scheduleSave();
            return;
        }

        // Refresh all (manual)
        if (e.target.closest('#qs-refresh-all')) {
            e.preventDefault();
            loadSections(null);
            return;
        }

        // Global toggle — flip all to same state
        if (e.target.closest('#qs-auto-toggle')) {
            e.preventDefault();
            const anyActive = allSections.some(s => states[s].active);
            allSections.forEach(s => {
                states[s].active = !anyActive;
                if (!anyActive) states[s].secondsLeft = INTERVAL;
            });
            updateAll();
            scheduleSave();
        }
    });

    // ── side nav ──────────────────────────────────────────────────
    const sectionMeta = {
        health:         { label: 'Stan systemu',       dot: '#43a047' },
        running:        { label: 'W trakcie',          dot: '#1e88e5' },
        recent_hour:    { label: 'Ostatnia godzina',   dot: '#43a047' },
        recent_started: { label: 'Ostatnie 20 min',    dot: '#546e7a' },
        errors:         { label: 'Błędy',              dot: '#e53935' },
        disabled:       { label: 'Wyłączone feedy',    dot: '#fb8c00' },
        overdue:        { label: 'Zaległe',            dot: '#fb8c00' },
        users:          { label: 'Użytkownicy',        dot: '#78909c' },
    };

    const sidenav = document.getElementById('qs-sidenav');

    // Build nav links
    allSections.forEach(s => {
        const m   = sectionMeta[s];
        const a   = document.createElement('a');
        a.href    = '#qs-' + s;
        a.dataset.section = s;
        a.innerHTML =
            `<span class="sn-dot" style="background:${m.dot}"></span>`
            + `<span class="sn-lbl">${m.label}</span>`;
        a.addEventListener('click', e => {
            e.preventDefault();
            document.getElementById('qs-' + s)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        sidenav.appendChild(a);
    });

    // Scrollspy via IntersectionObserver
    const navLinks = {};
    sidenav.querySelectorAll('a[data-section]').forEach(a => { navLinks[a.dataset.section] = a; });

    const visibleSections = new Set();
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => {
            const s = e.target.id.replace('qs-', '');
            if (e.isIntersecting) visibleSections.add(s);
            else                  visibleSections.delete(s);
        });
        // Highlight topmost visible section
        const active = allSections.find(s => visibleSections.has(s));
        Object.entries(navLinks).forEach(([s, a]) => a.classList.toggle('active', s === active));
    }, { threshold: 0.1 });

    allSections.forEach(s => {
        const el = document.getElementById('qs-' + s);
        if (el) observer.observe(el);
    });

    // ── init ──────────────────────────────────────────────────────
    loadSections(null);
    updateAll();
})();
</script>
