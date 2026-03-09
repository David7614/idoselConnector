<?php
namespace app\models;

use yii\data\ActiveDataProvider;
use Yii;

class UserSearch extends User
{
    public function rules()
    {
        return [
            [['id', 'active'], 'integer'],
            [['username', 'email', 'shop_type', 'lastFinishedAt'], 'safe'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $subQuery = (new \yii\db\Query())
            ->select('MAX(finished_at)')
            ->from('xml_feed_queue')
            ->where('current_integrate_user = {{%user}}.id')
            ->andWhere(['integrated' => Queue::EXECUTED]);

        $query = User::find()->addSelect(['{{%user}}.*', 'lastFinishedAt' => $subQuery]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 100],
            'sort'       => [
                'defaultOrder' => ['lastFinishedAt' => SORT_DESC],
                'attributes'   => [
                    'id',
                    'username',
                    'active',
                    'shop_type',
                    'lastFinishedAt' => [
                        'asc'     => ['lastFinishedAt' => SORT_ASC],
                        'desc'    => ['lastFinishedAt' => SORT_DESC],
                        'label'   => 'Ostatnia synchronizacja',
                        'default' => SORT_DESC,
                    ],
                ],
            ],
        ]);

        $this->load($params);

        // Filtr "tylko aktywni" — domyślnie włączony gdy brak parametru
        $activeParam = $params['active'] ?? '1';
        if ($activeParam !== 'all') {
            $query->andWhere(['active' => (int)$activeParam]);
        }

        if ($this->username) {
            $query->andWhere(['like', 'username', $this->username]);
        }
        if ($this->shop_type) {
            $query->andWhere(['shop_type' => $this->shop_type]);
        }

        return $dataProvider;
    }
}
