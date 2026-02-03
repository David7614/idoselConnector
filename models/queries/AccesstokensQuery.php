<?php

namespace app\models\queries;

/**
 * This is the ActiveQuery class for [[\app\models\UserConfig]].
 *
 * @see \app\models\Accesstokens
 */
class AccesstokensQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return \app\models\Accesstokens[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\Accesstokens|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
