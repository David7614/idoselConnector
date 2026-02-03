<?php
namespace app\models;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
// use app\models\ListCardsAssigned

use Yii;

class UserSearch extends User{
    public function rules()
    {
        return [
                [['name', 'customerBoard', 'assignedUsersList', 'customer_boards_lists_id', 'status'], 'safe'],
                [['customer_id'], 'safe'],
            ];
    }


    public function search($params){
        $query=User::find();
        // if (!isset($params['sort'])){
        //     $query->orderBy(['priority'=>SORT_DESC, 'last_activity'=>SORT_DESC]);
        // }

        $dataProvider  = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);
        $this->load($params);
        // echo "<pre>";
        // print_r($this->attributes);
        // echo "</pre>";
        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }


        return $dataProvider;
    }

}
