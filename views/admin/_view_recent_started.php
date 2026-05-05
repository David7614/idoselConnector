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
    'phonesubscribers' => 'SMS sub.',
    'tags'             => 'Tagi',
    'countries'        => 'Kraje',
];

$statusLabel = [
    Queue::PENDING  => ['Oczekuje',  '#888'],
    Queue::RUNNING  => ['W trakcie', '#1e88e5'],
    Queue::EXECUTED => ['Ukończone', '#43a047'],
    Queue::MISSED   => ['Pominięte', '#888'],
    Queue::DISABLED => ['Wyłączone', '#fb8c00'],
    Queue::ERROR    => ['Błąd',      '#e53935'],
];

$now = date('Y-m-d H:i:s');

$timeDiff = function (?string $date) use ($now): string {
    if (!$date) return '—';
    $secs = strtotime($now) - strtotime($date);
    if ($secs < 0)     return 'za ' . abs(round($secs / 60)) . ' min';
    if ($secs < 3600)  return round($secs / 60) . ' min temu';
    if ($secs < 86400) return round($secs / 3600) . ' h temu';
    return round($secs / 86400) . ' dni temu';
};
?>

<?php if ($items): ?>
<table class="table table-bordered" style="font-size:12px; margin:0;">
    <thead style="background:#f5f5f5;">
        <tr>
            <th style="color:#bbb; font-weight:400; width:50px;">#</th>
            <th>Typ</th>
            <th>Status</th>
            <th>Faza</th>
            <th>Uruchomione</th>
            <th>Zakończone</th>
            <th>Postęp</th>
            <th style="width:80px;">Akcja</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item):
        [$slLabel, $slColor] = $statusLabel[$item->integrated] ?? [(string)$item->integrated, '#888'];
        $params      = $item->additionalParameters;
        $objectsDone = !empty($params['objects_done']);
        $progress    = $item->max_page > 0
            ? round($item->page / $item->max_page * 100) . '% (' . $item->page . '/' . $item->max_page . ')'
            : ($item->page > 0 ? 'str. ' . $item->page : '—');
        $isRunning   = $item->integrated === Queue::RUNNING;
        $rowBg = match(true) {
            $item->integrated === Queue::ERROR   => 'background:#fff5f5;',
            $item->integrated === Queue::RUNNING => 'background:#f0f7ff;',
            default => '',
        };
    ?>
    <tr style="<?= $rowBg ?>">
        <td style="color:#bbb; font-size:11px;"><?= $item->id ?></td>
        <td><span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;background:#eee;color:#444;"><?= Html::encode($typeLabel[$item->integration_type] ?? $item->integration_type) ?></span></td>
        <td><span style="color:<?= $slColor ?>; font-weight:500;"><?= $slLabel ?></span></td>
        <td>
            <?php if ($objectsDone): ?>
                <span style="color:#1e88e5; font-size:11px;" title="objects_done=true">XML</span>
            <?php else: ?>
                <span style="color:#888; font-size:11px;">objects</span>
            <?php endif ?>
        </td>
        <td title="<?= Html::encode($item->executed_at) ?>"><?= $timeDiff($item->executed_at) ?></td>
        <td title="<?= Html::encode($item->finished_at ?? '') ?>"><?= $timeDiff($item->finished_at) ?></td>
        <td style="font-size:11px;"><?= Html::encode($progress) ?></td>
        <td>
            <?php if ($isRunning): ?>
                <?= Html::a('↺ Restartuj', Url::to(['admin/restart-queue-output', 'queueId' => $item->id]), [
                    'class' => 'btn btn-xs btn-warning', 'target' => '_blank',
                ]) ?>
            <?php else: ?>
                <?= Html::a('▶', Url::to(['admin/run-queue-output', 'queueId' => $item->id]), [
                    'class' => 'btn btn-xs btn-success', 'target' => '_blank', 'title' => 'Uruchom',
                ]) ?>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#aaa; font-size:13px; margin:6px 0;">Brak uruchomionych zadań.</p>
<?php endif ?>
