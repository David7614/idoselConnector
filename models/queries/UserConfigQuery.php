<?php

namespace app\models\queries;

/**
 * This is the ActiveQuery class for [[\app\models\UserConfig]].
 *
 * @see \app\models\UserConfig
 */
class UserConfigQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return \app\models\UserConfig[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\UserConfig|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
