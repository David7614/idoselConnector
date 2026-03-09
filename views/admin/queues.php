<?php
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

$typeLabel = [
    'product'          => 'Produkty',
    'order'            => 'Zamówienia',
    'customer'         => 'Klienci',
    'category'         => 'Kategorie',
    'subscribers'      => 'Subskrybenci',
    'phone_subscriber' => 'SMS sub.',
    'phonesubscribers' => 'SMS sub.',
    'tag'              => 'Tagi',
    'countries'        => 'Kraje',
];

$userName = fn($userId) => isset($users[$userId])
    ? Html::a(Html::encode($users[$userId]->username), Url::to(['admin/view', 'id' => $userId]), ['title' => 'Kolejka użytkownika'])
    : "<span style='color:#aaa'>#{$userId}</span>";

$timeDiff = function (?string $date) use ($now): string {
    if (!$date) return '—';
    $secs = strtotime($now) - strtotime($date);
    if ($secs < 0)      return 'za ' . abs(round($secs / 60)) . ' min';
    if ($secs < 3600)   return round($secs / 60) . ' min temu';
    if ($secs < 86400)  return round($secs / 3600) . ' h temu';
    return round($secs / 86400) . ' dni temu';
};

// --- Stan zdrowia systemu ---
$hasErrors   = count($errors) > 0;
$hasOverdue  = count($overdue) > 0;
$hasRunning  = count($running) > 0;
$healthOk    = !$hasErrors && !$hasOverdue;

// Deduplikacja zaległych per user+type (liczymy unikalne pary)
$overdueByUser = [];
foreach ($overdue as $item) {
    $overdueByUser[$item->current_integrate_user][$item->integration_type][] = $item;
}
$errorsByUser = [];
foreach ($errors as $item) {
    $errorsByUser[$item->current_integrate_user][$item->integration_type][] = $item;
}
$runningByUser = [];
foreach ($running as $item) {
    $runningByUser[$item->current_integrate_user][$item->integration_type][] = $item;
}

// Ostatnie wykonanie per user+type (z recentDone)
$lastDoneMap = [];
foreach ($recentDone as $item) {
    $key = $item->current_integrate_user . '_' . $item->integration_type;
    if (!isset($lastDoneMap[$key])) {
        $lastDoneMap[$key] = $item->finished_at;
    }
}
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
</style>

<div class="queues-page">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <h2 style="margin:0;">Monitor kolejek</h2>
        <div>
            <?= Html::a('&larr; Lista użytkowników', Url::to(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
            <?= Html::a('↻ Odśwież', Url::current(), ['class' => 'btn btn-default btn-sm']) ?>
        </div>
    </div>

    <!-- Pasek zdrowia -->
    <div class="health-bar">
        <div class="health-card <?= $healthOk ? 'hc-ok' : ($hasErrors ? 'hc-err' : 'hc-warn') ?>">
            <h3><?= $healthOk ? '✔' : '✖' ?></h3>
            <p><?= $healthOk ? 'System działa poprawnie' : ($hasErrors ? 'Wykryto błędy' : 'Zaległe zadania') ?></p>
        </div>
        <div class="health-card <?= $hasRunning ? 'hc-info' : 'hc-neutral' ?>">
            <h3><?= count($running) ?></h3>
            <p>W trakcie</p>
        </div>
        <div class="health-card <?= $hasOverdue ? 'hc-warn' : 'hc-ok' ?>">
            <h3><?= count($overdueByUser) ?></h3>
            <p>Użytkowników z zaległościami</p>
        </div>
        <div class="health-card <?= $hasErrors ? 'hc-err' : 'hc-ok' ?>">
            <h3><?= count($errorsByUser) ?></h3>
            <p>Użytkowników z błędami</p>
        </div>
        <div class="health-card hc-ok">
            <h3><?= count(array_unique(array_column($recentDone, 'current_integrate_user'))) ?></h3>
            <p>Aktywnych (ostatnie 24h)</p>
        </div>
    </div>

    <!-- W trakcie -->
    <?php if ($running): ?>
    <div class="section-title">
        <span class="dot dot-run"></span>W trakcie wykonywania
        <span class="badge" style="background:#1e88e5;"><?= count($running) ?></span>
    </div>
    <table class="q-table">
        <thead><tr>
            <th>Użytkownik</th><th>Typ</th><th>Postęp</th><th>Uruchomione</th><th>Czas trwania</th><th>Akcja</th>
        </tr></thead>
        <tbody>
        <?php foreach ($running as $item):
            $progress  = $item->max_page > 0 ? round($item->page / $item->max_page * 100) . '%' : '—';
            $duration  = $item->executed_at ? $timeDiff($item->executed_at) : '—';
            $isStuck   = $item->executed_at && (strtotime($now) - strtotime($item->executed_at)) > 3600;
        ?>
        <tr <?= $isStuck ? 'style="background:#fff3e0;"' : '' ?>>
            <td><?= $userName($item->current_integrate_user) ?></td>
            <td><span class="type-chip run"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
            <td><?= $progress ?> <?= $item->max_page > 0 ? "<small style='color:#999'>({$item->page}/{$item->max_page})</small>" : '' ?></td>
            <td title="<?= Html::encode($item->executed_at) ?>"><?= $timeDiff($item->executed_at) ?></td>
            <td><?= $isStuck ? '<span style="color:#e65100">⚠ ponad godzinę</span>' : '' ?></td>
            <td><?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user]), ['class' => 'btn btn-xs btn-default']) ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <!-- Błędy -->
    <?php if ($errors): ?>
    <div class="section-title">
        <span class="dot dot-err"></span>Błędy
        <span class="badge" style="background:#e53935;"><?= count($errors) ?></span>
    </div>
    <table class="q-table">
        <thead><tr>
            <th>Użytkownik</th><th>Typ</th><th>Ostatnia próba</th><th>Komunikat błędu</th><th>Akcja</th>
        </tr></thead>
        <tbody>
        <?php foreach ($errors as $item):
            $params = $item->additionalParameters;
            $errMsg = $params['error_msg'] ?? '—';
        ?>
        <tr>
            <td><?= $userName($item->current_integrate_user) ?></td>
            <td><span class="type-chip err"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
            <td title="<?= Html::encode($item->finished_at) ?>"><?= $timeDiff($item->finished_at) ?></td>
            <td style="color:#c62828; max-width:360px;"><?= Html::encode($errMsg) ?></td>
            <td>
                <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user, 'status' => 'error']), ['class' => 'btn btn-xs btn-danger']) ?>
                <?= Html::a('▶', Url::to(['admin/run-queue-output', 'queueId' => $item->id]), ['class' => 'btn btn-xs btn-success', 'target' => '_blank', 'title' => 'Uruchom ponownie']) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <!-- Zaległe -->
    <?php if ($overdue): ?>
    <div class="section-title">
        <span class="dot dot-warn"></span>Zaległe zadania
        <span class="badge" style="background:#fb8c00;"><?= count($overdue) ?></span>
        <small style="color:#999; font-weight:normal; margin-left:8px;">pending, planowane na przeszłość</small>
    </div>
    <table class="q-table">
        <thead><tr>
            <th>Użytkownik</th><th>Typ</th><th>Planowane na</th><th>Opóźnienie</th><th>Akcja</th>
        </tr></thead>
        <tbody>
        <?php foreach ($overdue as $item):
            $delaySecs = strtotime($now) - strtotime($item->next_integration_date);
            $delayStr  = $delaySecs < 3600
                ? round($delaySecs / 60) . ' min'
                : round($delaySecs / 3600, 1) . ' h';
            $delayClass = $delaySecs > 7200 ? 'color:#b71c1c; font-weight:600' : 'color:#e65100';
        ?>
        <tr>
            <td><?= $userName($item->current_integrate_user) ?></td>
            <td><span class="type-chip warn"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
            <td title="<?= Html::encode($item->next_integration_date) ?>"><?= Html::encode($item->next_integration_date) ?></td>
            <td style="<?= $delayClass ?>">+<?= $delayStr ?></td>
            <td>
                <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user, 'status' => 'overdue']), ['class' => 'btn btn-xs btn-warning']) ?>
                <?= Html::a('▶', Url::to(['admin/run-queue-output', 'queueId' => $item->id]), ['class' => 'btn btn-xs btn-success', 'target' => '_blank', 'title' => 'Uruchom teraz']) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <!-- Stan aktywnych użytkowników -->
    <div class="section-title">
        <span class="dot dot-ok"></span>Stan aktywnych użytkowników
    </div>
    <table class="user-table">
        <thead><tr>
            <th>Użytkownik</th>
            <th>Ostatnie wykonanie (24h)</th>
            <th>W trakcie</th>
            <th>Błędy</th>
            <th>Zaległe</th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($users as $uid => $u):
            $hasErr  = isset($errorsByUser[$uid]);
            $hasOvd  = isset($overdueByUser[$uid]);
            $hasRun  = isset($runningByUser[$uid]);
            $isProblem = $hasErr || $hasOvd;

            // Typy wykonane w 24h dla tego użytkownika
            $doneTypes = [];
            foreach ($recentDone as $item) {
                if ($item->current_integrate_user == $uid) {
                    $doneTypes[$item->integration_type] = true;
                }
            }
        ?>
        <tr class="<?= $isProblem ? 'problem-row' : '' ?>">
            <td>
                <?= Html::a(Html::encode($u->username), Url::to(['admin/view', 'id' => $uid])) ?>
            </td>
            <td>
                <?php if ($doneTypes): ?>
                    <?php foreach (array_keys($doneTypes) as $t): ?>
                        <span class="type-chip ok"><?= Html::encode($typeLabel[$t] ?? $t) ?></span>
                    <?php endforeach ?>
                <?php else: ?>
                    <span style="color:#bbb;">brak aktywności</span>
                <?php endif ?>
            </td>
            <td>
                <?php if ($hasRun): ?>
                    <?php foreach (array_keys($runningByUser[$uid]) as $t): ?>
                        <span class="type-chip run"><?= Html::encode($typeLabel[$t] ?? $t) ?></span>
                    <?php endforeach ?>
                <?php else: ?>—<?php endif ?>
            </td>
            <td>
                <?php if ($hasErr): ?>
                    <?php foreach (array_keys($errorsByUser[$uid]) as $t): ?>
                        <span class="type-chip err"><?= Html::encode($typeLabel[$t] ?? $t) ?></span>
                    <?php endforeach ?>
                <?php else: ?><span style="color:#4caf50">✔</span><?php endif ?>
            </td>
            <td>
                <?php if ($hasOvd): ?>
                    <?php foreach (array_keys($overdueByUser[$uid]) as $t): ?>
                        <span class="type-chip warn"><?= Html::encode($typeLabel[$t] ?? $t) ?></span>
                    <?php endforeach ?>
                <?php else: ?><span style="color:#4caf50">✔</span><?php endif ?>
            </td>
            <td>
                <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $uid]), ['class' => 'btn btn-xs btn-default']) ?>
                <?= Html::a('Panel',   Url::to(['admin/dashboard', 'id' => $uid]), ['class' => 'btn btn-xs btn-info']) ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>

</div>
