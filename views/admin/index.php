<?php
use app\models\Queue;
use app\models\UserSearch;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$searchModel = new UserSearch();
$dataProvider = $searchModel->search(Yii::$app->request->queryParams);
?>

<?=GridView::widget([
	'dataProvider' => $dataProvider,
	'filterModel' => $searchModel,
	'columns' => [
		'id',
		'username',
		[
			'attribute' => 'fronturl',
			'format' => 'raw',
			'value' => function ($model) {
				if ($model->fronturl) {
					return $model->fronturl;
				}
				// if ($model->shop_type=='shoper'){
				//     $shopershop=ShoperShops::findOne()
				//     return $model->fronturl;
				// }

				return '';
			},
		],
		'active',
		[
			'attribute' => 'lastFinishedXML',
			'format' => 'raw',
			'value' => function ($model) {
				$lastSuccess = Queue::find()->where(['current_integrate_user' => $model->id, 'integrated' => 2])->orderBy(['executed_at' => SORT_DESC])->one();
				if (!$lastSuccess) {
					return '-';
				}
				return $lastSuccess->finished_at;
			},
		],
		'shop_type',
		[
			'class' => ActionColumn::className(),
			'template' => '{view} {update} {delete} {other}',
			'buttons' => [
				// 'view' => function ($url, $model) {
				//     return Html::a('<span class="btn btn-primary">Zobacz</span>', $url, [
				//                 'title' => Yii::t('app', 'Zobacz'),
				//     ]);
				// },
				// 'update' => function ($url, $model) {
				//     return Html::a('<span class="btn btn-primary">Aktualizuj</span>', $url, [
				//                 'title' => Yii::t('app', 'Aktualizuj'),
				//     ]);
				// },
				// 'delete' => function ($url, $model) {
				//     return Html::a('<span class="btn btn-primary">Zarchiwizuj</span>', $url, [
				//                 'title' => Yii::t('app', 'Zarchiwizuj'),
				//     ]);
				// },
				'other' => function ($url, $model) {
					// return $model->anticipateAction();
					$url = Url::to(['admin/dashboard', 'id' => $model->id]);
					if ($model->shop_type == 'shoper') {
						$url = Url::to(['admin/dashboard-shoper', 'id' => $model->id]);
					}
					return Html::a('showUserDashboard', $url, [
						'title' => Yii::t('app', 'Pokaż Panel'),
						// 'style' => 'color:red'
					]);
				},
			],
			//    'urlCreator' => function ($action, $model, $key, $index) {
			//     return Url::to(['another-controller-name/'.$action, 'id' => $model->id]);
			// }
			// you may configure additional properties here
		],

		// ...
	],
])?>
