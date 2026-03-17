<?php
/**
 * Partial rendered by actionQueuesSections().
 * Receives: $section (string), $typeLabel, $users, $now,
 *           $running, $overdue, $errors, $disabled, $recentDone, $recentStarted
 */
use app\models\Queue;
use yii\helpers\Html;
use yii\helpers\Url;

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

$overdueByUser  = [];
foreach ($overdue  as $i) { $overdueByUser[$i->current_integrate_user][$i->integration_type][] = $i; }
$errorsByUser   = [];
foreach ($errors   as $i) { $errorsByUser[$i->current_integrate_user][$i->integration_type][]  = $i; }
$disabledByUser = [];
foreach ($disabled as $i) { $disabledByUser[$i->current_integrate_user][$i->integration_type][] = $i; }
$runningByUser  = [];
foreach ($running  as $i) { $runningByUser[$i->current_integrate_user][$i->integration_type][]  = $i; }

$refreshBtn = fn(string $s) =>
    "<button class='qs-refresh-btn btn btn-xs btn-link' data-section='{$s}' title='Odśwież sekcję' style='padding:0 4px; vertical-align:middle;'>↻</button>"
    . "<span class='qs-countdown' style='font-size:11px; color:#aaa; margin-left:3px;'></span>";
?>

<?php /* ── HEALTH ──────────────────────────────────────────── */ ?>
<?php if ($section === 'health'): ?>
<?php
$hasErrors   = count($errors)   > 0;
$hasOverdue  = count($overdue)  > 0;
$hasRunning  = count($running)  > 0;
$hasDisabled = count($disabled) > 0;
$healthOk    = !$hasErrors && !$hasOverdue;
?>
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
    <div class="health-card <?= $hasDisabled ? 'hc-warn' : 'hc-neutral' ?>">
        <h3><?= count($disabledByUser) ?></h3>
        <p>Wyłączone feedy</p>
    </div>
    <div class="health-card hc-ok">
        <h3><?= count(array_unique(array_column($recentDone, 'current_integrate_user'))) ?></h3>
        <p>Aktywnych (ostatnie 24h)</p>
    </div>
</div>
<?php endif ?>

<?php /* ── RUNNING ─────────────────────────────────────────── */ ?>
<?php if ($section === 'running' && $running): ?>
<div class="section-title">
    <span class="dot dot-run"></span>W trakcie wykonywania
    <span class="badge" style="background:#1e88e5;"><?= count($running) ?></span>
    <?= $refreshBtn('running') ?>
</div>
<table class="q-table">
    <thead><tr>
        <th>Użytkownik</th><th>Typ</th><th>Postęp</th><th>Uruchomione</th><th>Czas trwania</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($running as $item):
        $progress    = $item->max_page > 0 ? round($item->page / $item->max_page * 100) . '%' : '—';
        $runningSecs = $item->executed_at ? (strtotime($now) - strtotime($item->executed_at)) : 0;
        $isStuck     = $runningSecs > 3600;
        $isStuck15   = $runningSecs > 900;
        $rowBg       = $isStuck ? 'style="background:#fff3e0;"' : ($isStuck15 ? 'style="background:#fffdf0;"' : '');
    ?>
    <tr <?= $rowBg ?>>
        <td><?= $userName($item->current_integrate_user) ?></td>
        <td><span class="type-chip run"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td><?= $progress ?> <?= $item->max_page > 0 ? "<small style='color:#999'>({$item->page}/{$item->max_page})</small>" : '' ?></td>
        <td title="<?= Html::encode($item->executed_at) ?>"><?= $timeDiff($item->executed_at) ?></td>
        <td>
            <?php if ($isStuck): ?>
                <span style="color:#e65100">⚠ ponad godzinę</span>
            <?php elseif ($isStuck15): ?>
                <span style="color:#b26a00">⚠ ponad 15 min</span>
            <?php endif ?>
        </td>
        <td>
            <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user]), ['class' => 'btn btn-xs btn-default']) ?>
            <?php if ($isStuck15): ?>
                <?= Html::a('↺ Restartuj', Url::to(['admin/restart-queue-output', 'queueId' => $item->id]), [
                    'class'  => 'btn btn-xs btn-warning',
                    'target' => '_blank',
                    'title'  => 'Zresetuj status i uruchom ponownie w nowym oknie',
                ]) ?>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?php /* ── RECENT HOUR ─────────────────────────────────────── */ ?>
<?php if ($section === 'recent_hour'):
    $hourAgo    = strtotime('-1 hour', strtotime($now));
    $recentHour = array_filter($recentDone, fn($i) => $i->finished_at && strtotime($i->finished_at) >= $hourAgo);
?>
<?php if ($recentHour): ?>
<div class="section-title">
    <span class="dot dot-ok"></span>Wykonane w ostatniej godzinie
    <span class="badge" style="background:#43a047;"><?= count($recentHour) ?></span>
    <?= $refreshBtn('recent_hour') ?>
</div>
<table class="q-table">
    <thead><tr>
        <th>Użytkownik</th><th>Typ</th><th>Zakończono</th><th>Postęp</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($recentHour as $item): ?>
    <tr>
        <td><?= $userName($item->current_integrate_user) ?></td>
        <td><span class="type-chip ok"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td title="<?= Html::encode($item->finished_at) ?>"><?= $timeDiff($item->finished_at) ?></td>
        <td><?= $item->max_page > 0 ? "<small style='color:#999'>{$item->page}/{$item->max_page} str.</small>" : '—' ?></td>
        <td><?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user]), ['class' => 'btn btn-xs btn-default']) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
<?php endif ?>

<?php /* ── RECENT STARTED ──────────────────────────────────── */ ?>
<?php if ($section === 'recent_started' && $recentStarted):
$statusLabel = [
    Queue::PENDING  => ['label' => 'Oczekuje',  'color' => '#888'],
    Queue::RUNNING  => ['label' => 'W trakcie', 'color' => '#1e88e5'],
    Queue::EXECUTED => ['label' => 'Ukończone', 'color' => '#43a047'],
    Queue::MISSED   => ['label' => 'Pominięte', 'color' => '#888'],
    Queue::DISABLED => ['label' => 'Wyłączone', 'color' => '#fb8c00'],
    Queue::ERROR    => ['label' => 'Błąd',      'color' => '#e53935'],
];
?>
<div class="section-title">
    <span class="dot dot-run"></span>Uruchomione w ostatnich 20 minutach
    <span class="badge" style="background:#546e7a;"><?= count($recentStarted) ?></span>
    <?= $refreshBtn('recent_started') ?>
</div>
<table class="q-table">
    <thead><tr>
        <th>Użytkownik</th><th>Typ</th><th>Status</th><th>Uruchomione</th><th>Postęp</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($recentStarted as $item):
        $sl       = $statusLabel[$item->integrated] ?? ['label' => $item->integrated, 'color' => '#888'];
        $progress = $item->max_page > 0 ? round($item->page / $item->max_page * 100) . '%' : '—';
    ?>
    <tr>
        <td><?= $userName($item->current_integrate_user) ?></td>
        <td><span class="type-chip"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td><span style="color:<?= $sl['color'] ?>; font-weight:500;"><?= $sl['label'] ?></span></td>
        <td title="<?= Html::encode($item->executed_at) ?>"><?= $timeDiff($item->executed_at) ?></td>
        <td><?= $progress ?> <?= $item->max_page > 0 ? "<small style='color:#999'>({$item->page}/{$item->max_page})</small>" : '' ?></td>
        <td><?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user]), ['class' => 'btn btn-xs btn-default']) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?php /* ── ERRORS ──────────────────────────────────────────── */ ?>
<?php if ($section === 'errors' && $errors): ?>
<div class="section-title">
    <span class="dot dot-err"></span>Błędy
    <span class="badge" style="background:#e53935;"><?= count($errors) ?></span>
    <?= $refreshBtn('errors') ?>
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
            <?= Html::a('↺ Reset', Url::to(['admin/reset-queue', 'queueId' => $item->id]), ['class' => 'btn btn-xs btn-warning', 'title' => 'Ustaw na pending — automat odpali ponownie']) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?php /* ── DISABLED ─────────────────────────────────────────── */ ?>
<?php if ($section === 'disabled' && $disabled): ?>
<div class="section-title">
    <span class="dot dot-warn"></span>Wyłączone feedy
    <span class="badge" style="background:#fb8c00;"><?= count($disabled) ?></span>
    <small style="color:#999; font-weight:normal; margin-left:8px;">celowo wyłączone — nie są błędami</small>
    <?= $refreshBtn('disabled') ?>
</div>
<table class="q-table">
    <thead><tr>
        <th>Użytkownik</th><th>Typ</th><th>Komunikat</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($disabled as $item):
        $params = $item->additionalParameters;
        $msg    = $params['error_msg'] ?? '—';
    ?>
    <tr>
        <td><?= $userName($item->current_integrate_user) ?></td>
        <td><span class="type-chip warn"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td style="color:#b26a00;"><?= Html::encode($msg) ?></td>
        <td>
            <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user, 'status' => 'disabled']), ['class' => 'btn btn-xs btn-default']) ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<?php /* ── OVERDUE ──────────────────────────────────────────── */ ?>
<?php if ($section === 'overdue' && $overdue): ?>
<div class="section-title">
    <span class="dot dot-warn"></span>Zaległe zadania
    <span class="badge" style="background:#fb8c00;"><?= count($overdue) ?></span>
    <small style="color:#999; font-weight:normal; margin-left:8px;">pending, planowane na przeszłość</small>
    <?= $refreshBtn('overdue') ?>
</div>
<table class="q-table">
    <thead><tr>
        <th>Użytkownik</th><th>Typ</th><th>Planowane na</th><th>Opóźnienie</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($overdue as $item):
        $delaySecs  = strtotime($now) - strtotime($item->next_integration_date);
        $delayStr   = $delaySecs < 3600 ? round($delaySecs / 60) . ' min' : round($delaySecs / 3600, 1) . ' h';
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

<?php /* ── USERS ────────────────────────────────────────────── */ ?>
<?php if ($section === 'users'): ?>
<div class="section-title">
    <span class="dot dot-ok"></span>Stan aktywnych użytkowników
    <?= $refreshBtn('users') ?>
</div>
<table class="user-table">
    <thead><tr>
        <th>Użytkownik</th>
        <th>Ostatnie wykonanie (24h)</th>
        <th>W trakcie</th>
        <th>Błędy</th>
        <th>Wyłączone</th>
        <th>Zaległe</th>
        <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($users as $uid => $u):
        $hasErr    = isset($errorsByUser[$uid]);
        $hasOvd    = isset($overdueByUser[$uid]);
        $hasRun    = isset($runningByUser[$uid]);
        $hasDis    = isset($disabledByUser[$uid]);
        $isProblem = $hasErr || $hasOvd;

        $doneTypes = [];
        foreach ($recentDone as $item) {
            if ($item->current_integrate_user == $uid) {
                $doneTypes[$item->integration_type] = true;
            }
        }
    ?>
    <tr class="<?= $isProblem ? 'problem-row' : '' ?>">
        <td><?= Html::a(Html::encode($u->username), Url::to(['admin/view', 'id' => $uid])) ?></td>
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
            <?php if ($hasDis): ?>
                <?php foreach (array_keys($disabledByUser[$uid]) as $t): ?>
                    <span class="type-chip warn"><?= Html::encode($typeLabel[$t] ?? $t) ?></span>
                <?php endforeach ?>
            <?php else: ?><span style="color:#bbb">—</span><?php endif ?>
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
<?php endif ?>
