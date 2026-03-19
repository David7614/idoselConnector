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

$collapseBtn = fn(string $s) =>
    "<button class='qs-collapse-btn' data-section='{$s}' title='Zwiń / Rozwiń'"
    . " style='background:none;border:none;padding:0 6px 0 0;cursor:pointer;color:#aaa;font-size:12px;vertical-align:middle;line-height:1;'>▼</button>";

$refreshBtn = fn(string $s) =>
    "<button class='qs-refresh-btn btn btn-xs btn-link' data-section='{$s}' title='Odśwież sekcję' style='padding:0 2px; vertical-align:middle;'>↻</button>"
    . "<button class='qs-section-toggle btn btn-xs btn-link' data-section='{$s}' title='Auto-refresh tej sekcji' style='padding:0 2px; vertical-align:middle; font-size:12px;'>⏸</button>"
    . "<span class='qs-countdown' data-section='{$s}' style='font-size:11px; color:#aaa; margin-left:2px; vertical-align:middle;'></span>";
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
<div class="section-title">
    <?= $collapseBtn('health') ?><span class="dot dot-ok"></span>Stan systemu
    <?= $refreshBtn('health') ?>
</div>
<div class="qs-section-body" data-section="health">
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

<?php if (!empty($execSummary)): ?>
<div style="margin-top:20px;">
    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:#555;">Wydajność kolejek — podsumowanie</div>
    <table class="q-table">
        <thead><tr>
            <th>Typ</th><th>Faza</th><th>Śr. czas</th><th>Min</th><th>Max</th><th>Śr. stron</th><th>Próbek</th>
        </tr></thead>
        <tbody>
        <?php foreach ($execSummary as $row):
            $avg = round($row['avg_time'], 2);
            $color = $avg > 10 ? '#b71c1c' : ($avg > 3 ? '#e65100' : '#2e7d32');
        ?>
        <tr>
            <td><span class="type-chip"><?= Html::encode($typeLabel[$row['integration_type']] ?? $row['integration_type']) ?></span></td>
            <td style="color:#888; font-size:12px;"><?= Html::encode($row['phase']) ?></td>
            <td style="font-weight:600; color:<?= $color ?>;"><?= $avg ?>s</td>
            <td style="color:#888; font-size:12px;"><?= round($row['min_time'], 2) ?>s</td>
            <td style="color:#888; font-size:12px;"><?= round($row['max_time'], 2) ?>s</td>
            <td style="color:#888; font-size:12px;"><?= round($row['avg_pages'], 1) ?></td>
            <td style="color:#bbb; font-size:11px;"><?= $row['cnt'] ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<?php if (!empty($execLogs)): ?>
<div style="margin-top:16px;">
    <div style="font-size:13px; font-weight:600; margin-bottom:8px; color:#555;">Ostatnie 50 wykonań</div>
    <table class="q-table">
        <thead><tr>
            <th>Kiedy</th><th>Użytkownik</th><th>Typ</th><th>Faza</th><th>Czas</th><th>Iteracja</th>
        </tr></thead>
        <tbody>
        <?php foreach ($execLogs as $log):
            $t = $log->execution_time;
            $color = $t > 10 ? '#b71c1c' : ($t > 3 ? '#e65100' : '#2e7d32');
            $progress = $log->max_page > 0
                ? $log->page . '/' . $log->max_page
                : ($log->page > 0 ? $log->page : '—');
        ?>
        <tr>
            <td style="color:#999; font-size:11px;" title="<?= Html::encode($log->created_at) ?>"><?= $timeDiff($log->created_at) ?></td>
            <td><?= $userName($log->user_id) ?></td>
            <td><span class="type-chip"><?= Html::encode($typeLabel[$log->integration_type] ?? $log->integration_type) ?></span></td>
            <td style="color:#888; font-size:12px;"><?= Html::encode($log->phase) ?></td>
            <td style="font-weight:600; color:<?= $color ?>;"><?= number_format($t, 2) ?>s</td>
            <td style="color:#888; font-size:12px;"><?= $progress ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

</div>
<?php endif ?>

<?php /* ── RUNNING ─────────────────────────────────────────── */ ?>
<?php if ($section === 'running'): ?>
<div class="section-title">
    <?= $collapseBtn('running') ?><span class="dot dot-run"></span>W trakcie wykonywania
    <span class="badge" style="background:#1e88e5;"><?= count($running) ?></span>
    <?= $refreshBtn('running') ?>
</div>
<div class="qs-section-body" data-section="running">
<?php if ($running): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Postęp</th><th>Uruchomione</th><th>Czas trwania</th><th>Akcja</th>
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
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
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
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak aktywnych zadań.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── RECENT HOUR ─────────────────────────────────────── */ ?>
<?php if ($section === 'recent_hour'):
    $hourAgo    = strtotime('-1 hour', strtotime($now));
    $recentHour = array_filter($recentDone, fn($i) => $i->finished_at && strtotime($i->finished_at) >= $hourAgo);
?>
<div class="section-title">
    <?= $collapseBtn('recent_hour') ?><span class="dot dot-ok"></span>Wykonane w ostatniej godzinie
    <span class="badge" style="background:#43a047;"><?= count($recentHour) ?></span>
    <?= $refreshBtn('recent_hour') ?>
</div>
<div class="qs-section-body" data-section="recent_hour">
<?php if ($recentHour): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Zakończono</th><th>Postęp</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($recentHour as $item): ?>
    <tr>
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
        <td><?= $userName($item->current_integrate_user) ?></td>
        <td><span class="type-chip ok"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td title="<?= Html::encode($item->finished_at) ?>"><?= $timeDiff($item->finished_at) ?></td>
        <td><?= $item->max_page > 0 ? "<small style='color:#999'>{$item->page}/{$item->max_page} str.</small>" : '—' ?></td>
        <td><?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $item->current_integrate_user]), ['class' => 'btn btn-xs btn-default']) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak ukończonych zadań w ciągu ostatniej godziny.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── RECENT STARTED ──────────────────────────────────── */ ?>
<?php if ($section === 'recent_started'):
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
    <?= $collapseBtn('recent_started') ?><span class="dot dot-run"></span>Uruchomione w ostatnich 20 minutach
    <span class="badge" style="background:#546e7a;"><?= count($recentStarted) ?></span>
    <?= $refreshBtn('recent_started') ?>
</div>
<div class="qs-section-body" data-section="recent_started">
<?php if ($recentStarted): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Status</th><th>Uruchomione</th><th>Postęp</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($recentStarted as $item):
        $sl       = $statusLabel[$item->integrated] ?? ['label' => $item->integrated, 'color' => '#888'];
        $progress = $item->max_page > 0 ? round($item->page / $item->max_page * 100) . '%' : '—';
    ?>
    <tr>
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
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
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak zadań uruchomionych w ciągu ostatnich 20 minut.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── ERRORS ──────────────────────────────────────────── */ ?>
<?php if ($section === 'errors'): ?>
<div class="section-title">
    <?= $collapseBtn('errors') ?><span class="dot dot-err"></span>Błędy
    <span class="badge" style="background:#e53935;"><?= count($errors) ?></span>
    <?= $refreshBtn('errors') ?>
</div>
<div class="qs-section-body" data-section="errors">
<?php if ($errors): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Ostatnia próba</th><th>Komunikat błędu</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($errors as $item):
        $params = $item->additionalParameters;
        $errMsg = $params['error_msg'] ?? '—';
    ?>
    <tr>
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
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
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak błędów.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── DISABLED ─────────────────────────────────────────── */ ?>
<?php if ($section === 'disabled'): ?>
<div class="section-title">
    <?= $collapseBtn('disabled') ?><span class="dot dot-warn"></span>Wyłączone feedy
    <span class="badge" style="background:#fb8c00;"><?= count($disabled) ?></span>
    <small style="color:#999; font-weight:normal; margin-left:8px;">celowo wyłączone — nie są błędami</small>
    <?= $refreshBtn('disabled') ?>
</div>
<div class="qs-section-body" data-section="disabled">
<?php if ($disabled): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Komunikat</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($disabled as $item):
        $params = $item->additionalParameters;
        $msg    = $params['error_msg'] ?? '—';
    ?>
    <tr>
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
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
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak wyłączonych feedów.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── OVERDUE ──────────────────────────────────────────── */ ?>
<?php if ($section === 'overdue'): ?>
<div class="section-title">
    <?= $collapseBtn('overdue') ?><span class="dot dot-warn"></span>Zaległe zadania
    <span class="badge" style="background:#fb8c00;"><?= count($overdue) ?></span>
    <small style="color:#999; font-weight:normal; margin-left:8px;">pending, planowane na przeszłość</small>
    <?= $refreshBtn('overdue') ?>
</div>
<div class="qs-section-body" data-section="overdue">
<?php if ($overdue): ?>
<table class="q-table">
    <thead><tr>
        <th style="color:#bbb;font-weight:400;">#</th><th>Użytkownik</th><th>Typ</th><th>Planowane na</th><th>Opóźnienie</th><th>Akcja</th>
    </tr></thead>
    <tbody>
    <?php foreach ($overdue as $item):
        $delaySecs  = strtotime($now) - strtotime($item->next_integration_date);
        $delayStr   = $delaySecs < 3600 ? round($delaySecs / 60) . ' min' : round($delaySecs / 3600, 1) . ' h';
        $delayClass = $delaySecs > 7200 ? 'color:#b71c1c; font-weight:600' : 'color:#e65100';
    ?>
    <tr>
        <td style="color:#bbb;font-size:11px;"><?= $item->id ?></td>
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
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0 16px;">Brak zaległych zadań.</p>
<?php endif ?>
</div>
<?php endif ?>

<?php /* ── USERS ────────────────────────────────────────────── */ ?>
<?php if ($section === 'users'): ?>
<div class="section-title">
    <?= $collapseBtn('users') ?><span class="dot dot-ok"></span>Stan aktywnych użytkowników
    <?= $refreshBtn('users') ?>
</div>
<div class="qs-section-body" data-section="users">
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
</div>
<?php endif ?>
