<?php
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

$now = new DateTime();

$statusLabel = function (int $status, ?string $nextDate) use ($now): array {
    if ($status === Queue::PENDING) {
        $scheduled = $nextDate ? new DateTime($nextDate) : null;
        if ($scheduled && $scheduled < $now) {
            return ['Zaległe', 'warning', 'overdue'];
        }
        return ['Zaplanowane', 'default', 'pending'];
    }
    return match ($status) {
        Queue::RUNNING  => ['W trakcie', 'primary', 'running'],
        Queue::EXECUTED => ['Wykonane',  'success',  'executed'],
        Queue::MISSED   => ['Pominięte', 'default',  'missed'],
        Queue::ERROR    => ['Błąd',      'danger',   'error'],
        default         => [(string)$status, 'default', (string)$status],
    };
};

$typeLabel = [
    'product'          => 'Produkty',
    'order'            => 'Zamówienia',
    'customer'         => 'Klienci',
    'category'         => 'Kategorie',
    'subscriber'       => 'Subskrybenci',
    'phone_subscriber' => 'SMS subskrybenci',
    'tag'              => 'Tagi',
];

// Podsumowanie po wszystkich rekordach (bez filtrów)
$summary     = [];
$lastSuccess = []; // ostatnie udane finished_at per typ

foreach ($allItems as $item) {
    $t = $item->integration_type;
    if (!isset($summary[$t])) {
        $summary[$t]     = ['pending' => 0, 'overdue' => 0, 'running' => 0, 'executed' => 0, 'error' => 0, 'missed' => 0];
        $lastSuccess[$t] = null;
    }
    if ($item->integrated === Queue::PENDING) {
        $scheduled = $item->next_integration_date ? new DateTime($item->next_integration_date) : null;
        $summary[$t][$scheduled && $scheduled < $now ? 'overdue' : 'pending']++;
    } elseif ($item->integrated === Queue::RUNNING) {
        $summary[$t]['running']++;
    } elseif ($item->integrated === Queue::EXECUTED) {
        $summary[$t]['executed']++;
        if ($item->finished_at && ($lastSuccess[$t] === null || $item->finished_at > $lastSuccess[$t])) {
            $lastSuccess[$t] = $item->finished_at;
        }
    } elseif ($item->integrated === Queue::ERROR) {
        $summary[$t]['error']++;
    } elseif ($item->integrated === Queue::MISSED) {
        $summary[$t]['missed']++;
    }
}

$filterUrl = fn(array $params) => Url::to(array_merge(['admin/view', 'id' => $user->id], $params));

$isActive = fn(string $type, string $status = '') =>
    $filterType === $type && ($status === '' || $filterStatus === $status);

$isTypeActive = fn(string $type) => $filterType === $type && !$filterStatus;
?>

<style>
.queue-tile         { border:1px solid #ddd; border-radius:6px; padding:12px 16px; min-width:170px; background:#fff; cursor:default; }
.queue-tile.active  { border-color:#337ab7; box-shadow:0 0 0 2px #337ab740; }
.queue-tile .tile-type-link { display:block; font-weight:bold; font-size:14px; margin-bottom:8px; color:#333; text-decoration:none; }
.queue-tile .tile-type-link:hover { color:#337ab7; }
.queue-tile .label  { display:inline-block; margin:2px 0; cursor:pointer; font-size:12px; }
.queue-tile .label:hover { opacity:.8; }
.tile-active-type   { background:#e8f0fe !important; }
.filter-bar         { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px; padding:8px 14px; margin-bottom:16px; font-size:13px; }
th.sortable         { cursor:pointer; white-space:nowrap; }
th.sortable:hover   { background:#e8e8e8; }
th.sorted           { background:#ddeeff; }
</style>

<?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
    <?php foreach ((array)$messages as $msg): ?>
        <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?> alert-dismissible" style="margin:10px 20px 0;">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= Html::encode($msg) ?>
        </div>
    <?php endforeach ?>
<?php endforeach ?>

<div style="margin:20px;">

    <div style="margin-bottom:16px;">
        <?= Html::a('&larr; Lista użytkowników', Url::to(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
        <?= Html::a('Panel', Url::to(['admin/dashboard', 'id' => $user->id]), ['class' => 'btn btn-info btn-sm']) ?>
        <?= Html::a('Ustawienia', Url::to(['admin/update', 'id' => $user->id]), ['class' => 'btn btn-default btn-sm']) ?>
    </div>

    <h2 style="margin-bottom:4px;">Kolejka zadań: <strong><?= Html::encode($user->username) ?></strong></h2>
    <p style="color:#888; margin-bottom:16px;">ID: <?= $user->id ?> &nbsp;|&nbsp; Ostatnie 200 wpisów, posortowane od najnowszych</p>

    <?php if (!$allItems): ?>
        <div class="alert alert-info">Brak wpisów w kolejce dla tego użytkownika.</div>
    <?php else: ?>

    <!-- Kafelki podsumowania -->
    <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <?php foreach ($summary as $type => $counts):
            $tileActive = $filterType === $type;
        ?>
        <div class="queue-tile <?= $tileActive ? 'active tile-active-type' : '' ?>">
            <?= Html::a(
                Html::encode($typeLabel[$type] ?? $type),
                $tileActive && !$filterStatus ? $filterUrl([]) : $filterUrl(['type' => $type]),
                ['class' => 'tile-type-link']
            ) ?>

            <?php
            $badges = [
                'running'  => ['W trakcie', 'primary'],
                'overdue'  => ['Zaległe',   'warning'],
                'error'    => ['Błąd',      'danger'],
                'pending'  => ['Zaplanowane','default'],
                'executed' => ['Wykonane',  'success'],
                'missed'   => ['Pominięte', 'default'],
            ];
            foreach ($badges as $key => [$blabel, $bstyle]):
                if ($counts[$key] <= 0) continue;
                $badgeActive = $tileActive && $filterStatus === $key;
                $href = ($badgeActive)
                    ? $filterUrl(['type' => $type])
                    : $filterUrl(['type' => $type, 'status' => $key]);
            ?>
                <div>
                    <?= Html::a(
                        '<span class="label label-' . $bstyle . '" style="' . ($badgeActive ? 'outline:2px solid #333;' : '') . '">'
                            . $counts[$key] . ' ' . $blabel
                        . '</span>',
                        $href,
                        ['style' => 'text-decoration:none']
                    ) ?>
                </div>
            <?php endforeach ?>
            <div style="margin-top:8px; padding-top:7px; border-top:1px solid #eee; font-size:11px; color:#999;">
                <?php if ($lastSuccess[$type]): ?>
                    <?php
                        $diffSecs = $now->getTimestamp() - (new DateTime($lastSuccess[$type]))->getTimestamp();
                        if ($diffSecs < 3600)       $ago = round($diffSecs / 60) . ' min temu';
                        elseif ($diffSecs < 86400)  $ago = round($diffSecs / 3600) . ' h temu';
                        else                        $ago = round($diffSecs / 86400) . ' dni temu';
                    ?>
                    <span title="<?= htmlspecialchars($lastSuccess[$type]) ?>">
                        ✔ ostatnie: <strong style="color:#4a4;"><?= $ago ?></strong>
                    </span>
                <?php else: ?>
                    <span style="color:#bbb;">brak zakończonych</span>
                <?php endif ?>
            </div>
            <div style="margin-top:8px; padding-top:7px; border-top:1px solid #eee;">
                <form method="post" action="<?= Url::to(['admin/hard-reset-type']) ?>"
                      onsubmit="return confirm('TWARDY RESET: <?= Html::encode(strtoupper($type)) ?>\n\nUsunie dane integracji (daty, flagi) i zresetuje kolejki do strony 0.\nSystem pobierze ponownie WSZYSTKIE dane z API.\n\nCzy na pewno chcesz wykonać reset?')">
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                    <input type="hidden" name="userId" value="<?= $user->id ?>">
                    <input type="hidden" name="type"   value="<?= Html::encode($type) ?>">
                    <button type="submit" class="btn btn-xs btn-danger" title="Wymuś ponowne pobranie wszystkich danych z API">
                        &#9888; Twardy reset
                    </button>
                </form>
                <?php if (!empty($hardResetDates[$type])): ?>
                    <div style="font-size:10px; color:#a00; margin-top:3px;" title="<?= Html::encode($hardResetDates[$type]) ?>">
                        ostatni reset: <?= Html::encode(date('d.m.Y H:i', strtotime($hardResetDates[$type]))) ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
        <?php endforeach ?>
    </div>

    <!-- Ostatnie uruchomienia -->
    <div style="margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; padding-bottom:6px; border-bottom:2px solid #eee;">
            <span style="font-size:15px; font-weight:600;"><span class="dot" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#546e7a;margin-right:5px;vertical-align:middle;"></span>Ostatnie uruchomienia</span>
            <button id="vrs-refresh" class="btn btn-xs btn-link" title="Odśwież" style="padding:0 4px;">↻</button>
            <button id="vrs-toggle" class="btn btn-xs btn-link" title="Auto-refresh" style="padding:0 4px; font-size:12px;">⏸</button>
            <span id="vrs-countdown" style="font-size:11px; color:#aaa;"></span>
            <span id="vrs-spinner" style="display:none; font-size:11px; color:#aaa;">ładowanie…</span>
        </div>
        <div id="vrs-content"></div>
    </div>

    <!-- Pasek aktywnego filtra -->
    <?php if ($filterType || $filterStatus): ?>
    <div class="filter-bar">
        <strong>Aktywny filtr:</strong>
        <?php if ($filterType): ?>
            Typ: <strong><?= Html::encode($typeLabel[$filterType] ?? $filterType) ?></strong>
        <?php endif ?>
        <?php if ($filterStatus): ?>
            &nbsp;/ Status: <strong><?= Html::encode(match($filterStatus) {
                'overdue'  => 'Zaległe',
                'pending'  => 'Zaplanowane',
                'running'  => 'W trakcie',
                'executed' => 'Wykonane',
                'error'    => 'Błąd',
                'missed'   => 'Pominięte',
                default    => $filterStatus,
            }) ?></strong>
        <?php endif ?>
        &nbsp;&nbsp;
        <?= Html::a('Wyczyść filtr', $filterUrl([]), ['class' => 'btn btn-xs btn-default']) ?>
        <span style="color:#888; margin-left:12px;">(<?= count($queueItems) ?> wyników)</span>
    </div>
    <?php else: ?>
    <p style="color:#888; font-size:13px; margin-bottom:8px;">
        Kliknij kafelek lub badge statusu, aby filtrować.
        Łącznie: <?= count($queueItems) ?> wpisów.
    </p>
    <?php endif ?>

    <!-- Tabela -->
    <table class="table table-bordered table-hover" style="font-size:13px;">
        <thead style="background:#f5f5f5;">
            <tr>
                <th>ID</th>
                <th class="sortable <?= (!$filterType) ? '' : '' ?>">
                    Typ
                    <?php if (!$filterType): ?>
                        <span style="color:#aaa; font-size:11px;">▼</span>
                    <?php endif ?>
                </th>
                <th>Status</th>
                <th>Zaplanowane na</th>
                <th>Uruchomione</th>
                <th>Zakończone</th>
                <th>Postęp</th>
                <th>Szczegóły</th>
                <th style="border-left:2px solid #ddd; width:80px;">Uruchom</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($queueItems as $item):
            [$label, $style] = $statusLabel($item->integrated, $item->next_integration_date);
            $isOverdue = ($item->integrated === Queue::PENDING
                && $item->next_integration_date
                && (new DateTime($item->next_integration_date)) < $now);
            $rowStyle = match(true) {
                $item->integrated === Queue::ERROR   => 'background:#fff5f5;',
                $item->integrated === Queue::RUNNING => 'background:#f0f7ff;',
                $isOverdue                           => 'background:#fffbf0;',
                default                              => '',
            };
            $progress = ($item->max_page > 0)
                ? round($item->page / $item->max_page * 100) . '% (' . $item->page . '/' . $item->max_page . ')'
                : ($item->page > 0 ? 'str. ' . $item->page : '—');

            $params = $item->additionalParameters;
            $extras = [];
            if (!empty($params['error_msg']))    $extras[] = '<span style="color:#c00;">Błąd: ' . Html::encode($params['error_msg']) . '</span>';
            if (!empty($params['tokenerrors']))  $extras[] = 'Błędy tokena: ' . (int)$params['tokenerrors'];
            if (!empty($params['objects_done'])) $extras[] = 'Obiekty: ' . (int)$params['objects_done'];

            $isRunning     = $item->integrated === Queue::RUNNING;
            $runningSecsSince = ($isRunning && $item->executed_at)
                ? ($now->getTimestamp() - (new DateTime($item->executed_at))->getTimestamp())
                : 0;
            $isStuckRunning = $runningSecsSince > 900; // 15 min
        ?>
            <tr style="<?= $rowStyle ?>">
                <td><?= $item->id ?></td>
                <td>
                    <?= Html::a(
                        Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type),
                        $filterUrl(['type' => $item->integration_type]),
                        ['style' => 'color:inherit; text-decoration:none;', 'title' => 'Filtruj po tym typie']
                    ) ?>
                </td>
                <td><span class="label label-<?= $style ?>"><?= $label ?></span></td>
                <td><?= Html::encode($item->next_integration_date ?? '—') ?></td>
                <td><?= Html::encode($item->executed_at ?? '—') ?></td>
                <td><?= Html::encode($item->finished_at ?? '—') ?></td>
                <td><?= $progress ?></td>
                <td><?= $extras ? implode('<br>', $extras) : '—' ?></td>
                <td style="border-left:2px solid #ddd; text-align:center;">
                    <?php if ($isRunning && !$isStuckRunning): ?>
                        <span class="label label-primary" title="Zadanie jest aktualnie w trakcie">w trakcie</span>
                    <?php elseif ($isStuckRunning): ?>
                        <?= Html::a('↺ Restartuj', Url::to(['admin/restart-queue-output', 'queueId' => $item->id]), [
                            'class'  => 'btn btn-xs btn-warning',
                            'target' => '_blank',
                            'title'  => 'Zadanie trwa ponad 15 min — zresetuj i uruchom ponownie',
                        ]) ?>
                    <?php else: ?>
                        <?= Html::a('▶ Uruchom', Url::to(['admin/run-queue-output', 'queueId' => $item->id]), [
                            'class'  => 'btn btn-xs btn-success',
                            'target' => '_blank',
                            'title'  => 'Uruchom i pokaż output w nowym oknie',
                        ]) ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if (!$queueItems): ?>
            <tr><td colspan="9" style="text-align:center; color:#888; padding:20px;">Brak wyników dla wybranego filtra.</td></tr>
        <?php endif ?>
        </tbody>
    </table>

    <?php endif ?>
</div>

<script>
(function () {
    const endpoint  = <?= json_encode(Url::to(['admin/view-recent-started', 'userId' => $user->id])) ?>;
    const INTERVAL  = 30;
    const content   = document.getElementById('vrs-content');
    const spinner   = document.getElementById('vrs-spinner');
    const countdown = document.getElementById('vrs-countdown');
    const toggleBtn = document.getElementById('vrs-toggle');
    const refreshBtn = document.getElementById('vrs-refresh');

    let active = true;
    let secondsLeft = INTERVAL;

    function load() {
        spinner.style.display = 'inline';
        countdown.textContent = '';
        fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => { content.innerHTML = data.html || ''; secondsLeft = INTERVAL; })
            .catch(() => {})
            .finally(() => { spinner.style.display = 'none'; });
    }

    function updateBtn() {
        toggleBtn.textContent = active ? '⏸' : '▶';
        toggleBtn.title = active ? 'Zatrzymaj auto-refresh' : 'Wznów auto-refresh';
        toggleBtn.style.color = active ? '#888' : '#1e88e5';
    }

    setInterval(() => {
        if (!active) { countdown.textContent = ''; return; }
        secondsLeft--;
        countdown.textContent = 'za ' + secondsLeft + 's';
        if (secondsLeft <= 0) { secondsLeft = INTERVAL; load(); }
    }, 1000);

    toggleBtn.addEventListener('click', () => {
        active = !active;
        if (active) { secondsLeft = INTERVAL; }
        updateBtn();
    });

    refreshBtn.addEventListener('click', load);

    load();
    updateBtn();
})();
</script>
