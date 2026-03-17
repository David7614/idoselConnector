<?php
use app\models\UserSearch;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$activeParam  = Yii::$app->request->get('active', '1');
$searchModel  = new UserSearch();
$dataProvider = $searchModel->search(array_merge(Yii::$app->request->queryParams, ['active' => $activeParam]));

$activeUrl = Url::current(['active' => '1']);
$allUrl    = Url::current(['active' => 'all']);
?>

<style>
.admin-index .grid-view table { font-size: 13px; }
.admin-index .grid-view th    { background: #f5f5f5; white-space: nowrap; }
.admin-index .sync-ok   { color: #2e7d32; font-weight: 500; }
.admin-index .sync-warn { color: #b26a00; }
.admin-index .sync-none { color: #aaa; }
.admin-index .badge-active   { display:inline-block; width:10px; height:10px; border-radius:50%; background:#4caf50; margin-right:4px; }
.admin-index .badge-inactive { display:inline-block; width:10px; height:10px; border-radius:50%; background:#ccc; margin-right:4px; }
.fc-badge  { display:inline-flex; align-items:center; gap:3px; padding:2px 6px 2px 5px;
             border-radius:4px; font-size:11px; margin:1px; background:#eee; color:#444;
             text-decoration:none; }
.fc-badge:hover { filter:brightness(.93); text-decoration:none; }
.fc-badge.has  { background:#e8f5e9; color:#2e7d32; }
.fc-badge.none { background:#fafafa; color:#bbb; }
.fc-count  { font-weight:600; }
.fc-refresh { background:none; border:none; padding:0 2px; cursor:pointer; color:#aaa; font-size:13px; line-height:1; }
.fc-refresh:hover { color:#1e88e5; }
.fc-age { font-size:10px; color:#bbb; margin-left:2px; }
</style>

<div class="admin-index" style="margin: 20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <h2 style="margin:0;">Użytkownicy</h2>
        <?= Html::a('Monitor kolejek', Url::to(['admin/queues']), ['class' => 'btn btn-default btn-sm', 'style' => 'margin-right:8px;']) ?>
        <div class="btn-group">
            <?= Html::a('Tylko aktywni', $activeUrl, [
                'class' => 'btn btn-sm ' . ($activeParam === '1' ? 'btn-primary' : 'btn-default'),
            ]) ?>
            <?= Html::a('Wszyscy', $allUrl, [
                'class' => 'btn btn-sm ' . ($activeParam === 'all' ? 'btn-primary' : 'btn-default'),
            ]) ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-bordered table-hover'],
        'columns' => [
            [
                'attribute'      => 'id',
                'headerOptions'  => ['style' => 'width:50px'],
                'contentOptions' => ['style' => 'color:#999; font-size:12px;'],
            ],
            [
                'attribute' => 'username',
                'label'     => 'Domena',
                'value'     => function ($model) {
                    return $model->fronturl
                        ? Html::a(Html::encode($model->username), 'http://' . $model->username, ['target' => '_blank', 'style' => 'color:inherit'])
                        : Html::encode($model->username);
                },
                'format' => 'raw',
            ],
            [
                'attribute'      => 'active',
                'label'          => 'Status',
                'headerOptions'  => ['style' => 'width:80px; text-align:center'],
                'contentOptions' => ['style' => 'text-align:center'],
                'format'         => 'raw',
                'value'          => function ($model) {
                    return $model->active
                        ? '<span class="badge-active"></span><span style="color:#2e7d32">Aktywny</span>'
                        : '<span class="badge-inactive"></span><span style="color:#999">Nieaktywny</span>';
                },
            ],
            [
                'attribute'      => 'shop_type',
                'label'          => 'Typ',
                'headerOptions'  => ['style' => 'width:90px'],
                'contentOptions' => ['style' => 'font-size:12px; color:#666;'],
            ],
            [
                'attribute'      => 'lastFinishedAt',
                'label'          => 'Ostatnia synchronizacja',
                'format'         => 'raw',
                'headerOptions'  => ['style' => 'white-space:nowrap'],
                'value'          => function ($model) {
                    if (!$model->lastFinishedAt) {
                        return '<span class="sync-none">—</span>';
                    }
                    $diff = time() - strtotime($model->lastFinishedAt);
                    if ($diff < 3600)      $ago = round($diff / 60) . ' min temu';
                    elseif ($diff < 86400) $ago = round($diff / 3600) . ' h temu';
                    else                   $ago = round($diff / 86400) . ' dni temu';

                    $cls = $diff < 86400 ? 'sync-ok' : 'sync-warn';
                    return '<span class="' . $cls . '" title="' . htmlspecialchars($model->lastFinishedAt) . '">' . $ago . '</span>';
                },
            ],
            [
                'label'          => 'Feedy',
                'format'         => 'raw',
                'headerOptions'  => ['style' => 'width:180px;'],
                'value'          => function ($model) use ($countsMap) {
                    $types  = ['product' => 'P', 'order' => 'O', 'customer' => 'K', 'category' => 'C'];
                    $titles = ['product' => 'Produkty', 'order' => 'Zamówienia', 'customer' => 'Klienci', 'category' => 'Kategorie'];
                    $xmlFiles = [
                        'product'  => 'products.xml',
                        'order'    => 'orders.xml',
                        'customer' => 'customers.xml',
                        'category' => 'categories.xml',
                    ];
                    $base   = \yii\helpers\Url::home(true) . 'xml/' . $model->uuid . '/';
                    $c      = $countsMap[$model->id] ?? null;
                    $ts     = $c['ts'] ?? null;
                    $stale  = !$ts || (time() - $ts) > 3600;

                    $badges = '';
                    foreach ($types as $type => $lbl) {
                        $count = $c[$type] ?? null;
                        $cls   = $count > 0 ? 'has' : ($count === null ? 'none' : '');
                        $num   = $count === null ? '?' : number_format($count, 0, '.', "\u{00A0}");
                        $url   = $base . $xmlFiles[$type];
                        $badges .= '<a href="' . $url . '" target="_blank" class="fc-badge ' . $cls . '" title="' . $titles[$type] . ' — pobierz XML">'
                            . $lbl . ' <span class="fc-count">' . $num . '</span></a>';
                    }

                    $ageHtml = '';
                    if ($ts && !$stale) {
                        $mins    = round((time() - $ts) / 60);
                        $ageHtml = '<span class="fc-age">' . ($mins < 2 ? 'przed chwilą' : $mins . ' min temu') . '</span>';
                    }

                    return '<div class="fc-wrap" data-user-id="' . $model->id . '" data-uuid="' . $model->uuid . '" data-needs-refresh="' . ($stale ? '1' : '0') . '">'
                        . $badges
                        . ' <button class="fc-refresh" title="Odśwież liczniki">↻</button>'
                        . $ageHtml
                        . '</div>';
                },
            ],
            [
                'label'  => 'Akcje',
                'format' => 'raw',
                'value'  => function ($model) {
                    return implode(' ', [
                        Html::a('Kolejka',    Url::to(['admin/view',      'id' => $model->id]), ['class' => 'btn btn-xs btn-default']),
                        Html::a('Ustawienia', Url::to(['admin/update',    'id' => $model->id]), ['class' => 'btn btn-xs btn-default']),
                        Html::a('Panel',      Url::to(['admin/dashboard', 'id' => $model->id]), ['class' => 'btn btn-xs btn-info']),
                    ]);
                },
            ],
            [
                'label'          => 'Usuń',
                'format'         => 'raw',
                'headerOptions'  => ['style' => 'border-left:2px solid #ddd; width:70px;'],
                'contentOptions' => ['style' => 'border-left:2px solid #ddd;'],
                'value'          => function ($model) {
                    return Html::a('Usuń', Url::to(['admin/delete', 'id' => $model->id]), [
                        'class'        => 'btn btn-xs btn-danger',
                        'data-confirm' => 'Czy na pewno chcesz usunąć użytkownika ' . $model->username . '?',
                        'data-method'  => 'post',
                    ]);
                },
            ],
        ],
    ]) ?>

</div>

<script>
(function () {
    const endpoint  = <?= json_encode(\yii\helpers\Url::to(['admin/refresh-feed-counts'])) ?>;
    const csrfToken = <?= json_encode(Yii::$app->request->csrfToken) ?>;
    const types    = ['product','order','customer','category'];
    const labels   = {product:'P', order:'O', customer:'K', category:'C'};
    const titles   = {product:'Produkty', order:'Zam\u00f3wienia', customer:'Klienci', category:'Kategorie'};
    const xmlFiles = {product:'products.xml', order:'orders.xml', customer:'customers.xml', category:'categories.xml'};
    const baseUrl  = <?= json_encode(\yii\helpers\Url::home(true) . 'xml/') ?>;

    function formatCount(n) {
        if (n === null || n === undefined) return '?';
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
    }

    function renderBadges(counts, uuid) {
        return types.map(t => {
            const n   = counts[t] ?? null;
            const cls = n > 0 ? 'has' : (n === null ? 'none' : '');
            const url = baseUrl + uuid + '/' + xmlFiles[t];
            return `<a href="${url}" target="_blank" class="fc-badge ${cls}" title="${titles[t]} \u2014 pobierz XML">${labels[t]} <span class="fc-count">${formatCount(n)}</span></a>`;
        }).join('');
    }

    function refresh(wrap) {
        const userId = wrap.dataset.userId;
        const uuid   = wrap.dataset.uuid;
        const btn    = wrap.querySelector('.fc-refresh');
        if (btn) { btn.textContent = '…'; btn.disabled = true; }

        fetch(endpoint, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    '_csrf=' + encodeURIComponent(csrfToken) + '&userId=' + encodeURIComponent(userId),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const c    = data.counts;
            const mins = Math.round((Date.now() / 1000 - c.ts) / 60);
            const age  = mins < 2 ? 'przed chwil\u0105' : mins + ' min temu';
            wrap.innerHTML = renderBadges(c, uuid)
                + ' <button class="fc-refresh" title="Od\u015bwie\u017c liczniki">\u21bb</button>'
                + '<span class="fc-age">' + age + '</span>';
            wrap.dataset.needsRefresh = '0';
        })
        .catch(() => { if (btn) { btn.textContent = '\u21bb'; btn.disabled = false; } });
    }

    // Manual refresh on button click
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.fc-refresh');
        if (btn) {
            e.preventDefault();
            refresh(btn.closest('.fc-wrap'));
        }
    });

    // Auto-refresh stale/missing entries on page load — sequentially, 300 ms apart
    const stale = Array.from(document.querySelectorAll('.fc-wrap[data-needs-refresh="1"]'));
    stale.forEach((wrap, i) => setTimeout(() => refresh(wrap), i * 300));
})();
</script>
