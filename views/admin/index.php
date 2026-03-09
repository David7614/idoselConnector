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
