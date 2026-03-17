<?php

namespace app\models;

use app\modules\xml_generator\src\XmlFeed;
use League\OAuth2\Client\Token\AccessToken;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "xml_feed_queue".
 *
 * @property int $id
 * @property int $integrated
 * @property string $next_integration_date
 * @property string $integration_type
 * @property int $current_integrate_user
 * @property int $page
 * @property int $max_page
 */
class Queue extends \yii\db\ActiveRecord
{
    private $_user;

    const PENDING  = 0;
    const RUNNING  = 1;
    const EXECUTED = 2;
    const MISSED   = 5;
    const DISABLED = 98;
    const ERROR    = 99;


    const QUEUE_FOR_DAYS=3;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'xml_feed_queue';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'integrated', 'current_integrate_user', 'page', 'max_page'], 'integer'],
            [['integration_type', 'next_integration_date'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
        ];
    }

    /**
     * @param $user
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    public function setType($type)
    {
        $this->integration_type = $type;
    }

    public function checkQueueConstraints(){
        if (!$this->getCurrentUser()->active){
            echo "non active";
            return false;
        }
        $disabled=DisabledFeeds::find()->where(['user_id'=>$this->getCurrentUser()->id, 'integration_type'=>$this->integration_type])->one();
        if ($disabled){
            echo "disabled feed";
            return false;
        }
        return true;
    }

    public function copyLastQueueSettings(){
        $queue_item = self::find()->where(['integration_type' => $this->integration_type, 'current_integrate_user'=>$this->current_integrate_user, 'integrated' => self::EXECUTED])->orderBy(['next_integration_date' => SORT_DESC])->one();
        if ($queue_item){
            $parameters=$queue_item->additionalParameters;
            $newParams=[];
            foreach ($parameters as $param=>$arr){
                // print_r($arr);
                $newParams[$param.'_prev']=$arr;
            }
            $this->additionalParameters=$newParams;
            $page=$queue_item->max_page-10;
            if ($page<0){
                $page=0;
            }
            $this->page=$page;
            $this->save();
        }
            // die("STOP");
    }

    public function getCurrentUser()
    {
        $user_id = $this->current_integrate_user;

        return User::find()->where(['id' => $user_id])->one();
    }

    public function setMaxPages($pages)
    {
        if($this->max_page !== 0 && $this->integration_type == XmlFeed::ORDER) {
            $this->max_page = $pages;
            return $this->save();
        }

        if($this->max_page !== 0 && $this->max_page > $pages) {
            return true;
        }

        $this->max_page = $pages;
        return $this->save();
    }

    public function increasePage()
    {
        $this->page = (int) $this->page + 1;
        return $this->save();
    }

    public function setCountErrors($count){
        $parameters=$this->additionalParameters;
        $parameters['tokenerrors']=$count;
        if ($count == 0) {
            unset($parameters['error_msg']);
        }
        $this->additionalParameters=$parameters;
        $this->save();
    }
    public function raiseCountErrors(){
        $count=$this->getCountErrors();
        $count++;
        $this->setCountErrors($count);
    }
    public function getCountErrors(){
        $parameters=$this->additionalParameters;
        if (isset($parameters['tokenerrors'])){
            return $parameters['tokenerrors'];
        }
        return 0;
    }

    public function setErrorStatus($msg='')
    {
        $parameters=$this->additionalParameters;
        $parameters['error_msg']=$msg;
        $this->additionalParameters=$parameters;
        $this->integrated = self::ERROR;
        return $this->save();
    }

    public function setDisabledStatus($msg='')
    {
        $parameters=$this->additionalParameters;
        $parameters['error_msg']=$msg;
        $this->additionalParameters=$parameters;
        $this->integrated = self::DISABLED;
        return $this->save();
    }

    public function setExecutedStatus()
    {
        switch ($this->integration_type) {
            case XmlFeed::ORDER:
                IntegrationData::setIsNew('ORDER', false, $this->getCurrentUser()->id);
                break;
            case XmlFeed::CUSTOMER:
                IntegrationData::setIsNew('CUSTOMER', false, $this->getCurrentUser()->id);
                break;
            default:
                break;
        }
        $this->setDoneStatus();
    }

    public function setDoneStatus(){
        $this->finished_at=date('Y-m-d H:i:s');
        $this->integrated = self::EXECUTED;
        return $this->save();
    }


    public function setRunningStatus()
    {
        $this->executed_at=date('Y-m-d H:i:s');
        $this->integrated = self::RUNNING;
        return $this->save();
    }

    public function setPendingStatus()
    {
        $this->integrated = self::PENDING;
        return $this->save();
    }

    /**
     * @param $type
     */
    public static function resetIntegration($type)
    {
        $items = self::find()->where(['integration_type' => $type])->all();

        foreach($items as $item) {
            $item->integrated = self::PENDING;
            $item->next_integration_date = self::getCurrentDateTime();
            $item->page = 0;
            $item->max_page = 0;
            $item->save();
        }
    }

    public static function resetLongRunning(){
        $maxDelay=date('Y-m-d H:i:s',strtotime(date('Y-m-d H:i:s')."- 1 hour"));
        // echo $maxDelay.PHP_EOL;
        $items = self::find()->where(['integrated' => self::RUNNING])->andWhere(['<','executed_at',  $maxDelay])->all();
        foreach ($items as $item) {
            // echo $item->id;
            $item->integrated = self::PENDING;

        //     switch($item->integration_type) {
        //         case XmlFeed::PRODUCT:
        //         case XmlFeed::CUSTOMER:
        //         case XmlFeed::TAGS:
        //             $item->max_page = 0;
        //             $item->page = 0;
        //             break;
        //         default:
        //             $item->page = 0;
        //             break;
        //     }
            
            $item->save();
        }
    }
    public static function resetAllDone()
    {
        $minDelay=date('Y-m-d H:i:s',strtotime(date('Y-m-d H:i:s')."- 1 day"));
        echo $minDelay.PHP_EOL;
        $items = self::find()->where(['integrated' => self::EXECUTED])->andWhere(['<','next_integration_date',  $minDelay])->all();
        // $items = self::find()->where(['integrated' => self::EXECUTED])->all();
        foreach ($items as $item) {
            echo $item->id;
            $item->delete();
        }
    }
    
    public static function resetAllException()
    {
        $items = self::find()->where(['integrated' => self::ERROR])->all();
        foreach ($items as $item) {
            // $item->next_integration_date = self::getCurrentDateTime();
            $item->integrated = self::PENDING;
            $item->save();
        }
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function getIsCompleted(string $type)
    {
        $items = self::find()->where(['integration_type' => $type])->all();
        $completed = true;

        foreach($items as $item) {
            if($item->integrated !== self::EXECUTED) {
                $completed = false;
            }
        }

        return $completed;
    }

    /**
     * @param string $type
     */
    public static function deleteFutureQueuesForUser(int $userId, string $type): void
    {
        self::deleteAll([
            'and',
            ['current_integrate_user' => $userId],
            ['integration_type'       => $type],
            ['integrated'             => self::PENDING],
            ['>', 'next_integration_date', date('Y-m-d H:i:s')],
        ]);
    }

    public static function prepareQueue(string $type)
    {

        // self::QUEUE_FOR_DAYS;
        $user_list = User::find()->where(['active'=>1])->all();
        //$date=date('Y-m-d');
	$date = date('Y-m-d H:i:s', strtotime('today 23:00'));
        $maxDate=date('Y-m-d H:i:s', strtotime(date('Y-m-d')." + ".self::QUEUE_FOR_DAYS." days "));
        echo "date ".$date.PHP_EOL;
        echo "maxdate ".$maxDate.PHP_EOL;
        foreach($user_list as $user) {
            echo "user ".$user->id.PHP_EOL;

            if ($type === \app\modules\xml_generator\src\XmlFeed::PRODUCT
                && $user->config->get('product_feed_disable') == 1) {
                echo "product feed disabled — skipping user " . $user->id . PHP_EOL;
                continue;
            }

            $lastScheduled=$user->getUserDataValue('last_sheduled_'.$type);
            if (!$lastScheduled){
                $lastScheduled=$date;
            }
            echo "last Scheduled: ".$lastScheduled.PHP_EOL;
            if ($lastScheduled<$maxDate){
                for ($day=1; $day<=self::QUEUE_FOR_DAYS; $day++){
                    $sheduleDate=date('Y-m-d H:i:s', strtotime($date . " + ".$day. " days"));
                    echo " scheduledate ".$sheduleDate.PHP_EOL;
                    if ($sheduleDate<=$lastScheduled){
                        echo "skip".PHP_EOL;
                        continue;
                    }
                    echo " **** new schedule entry ".PHP_EOL;
                    $queue = new self();
                    $queue->current_integrate_user = $user->id;
                    $queue->integration_type = $type;
                    $queue->integrated = self::PENDING;
                    $queue->next_integration_date = $sheduleDate;
                    $queue->page = 0;
                    $queue->max_page = 0;
                    $queue->save();
                    $sheduleDate2=date('Y-m-d H:i:s', strtotime($sheduleDate . " + 10 minutes"));
                    $queue = new self();
                    $queue->current_integrate_user = $user->id;
                    $queue->integration_type = $type;
                    $queue->integrated = self::PENDING;
                    $queue->next_integration_date = $sheduleDate2;
                    $queue->page = 0;
                    $queue->max_page = 0;
                    $queue->setAdditionalParameters(['objects_done' => 1]);
                    $queue->save();

                }
                $user->setUserDataValue('last_sheduled_'.$type, $sheduleDate);
            }
        }
    }

    /**
     * @param string $type
     *
     * @return Queue|array|null
     */
    public static function findLastForTypeAndShop(string $type, string $shop_type){
        $userList=ArrayHelper::map(User::find()->where(['active'=>1, 'shop_type'=>$shop_type])->all(), 'id', 'id');
        return self::findLastForType($type, $userList);
    }
    public static function findPararelForType(string $type, $offset=2)
    {
        $dateLimit=date('Y-m-d H:i:s');
        // $dateLimit=date('Y-m-d H:i:s', strtotime($dateLimit . " + 1 day")); // for testing purposes only
        echo "FIND PARAREL TYPE FOR QUEUE ".PHP_EOL;
        echo "OFFSET ".$offset.PHP_EOL;
        $queue_items = self::find()->where(['integration_type' => $type, 'integrated' => self::PENDING])
                ->orderBy(['next_integration_date' => SORT_ASC])
                ->andWhere(['<', 'next_integration_date', $dateLimit])->limit($offset+1);
        // $sql = $queue_items->createCommand()->getRawSql();
        // echo $sql;
        // die("!!");
        $items=$queue_items->all();

        foreach ($items as $itm) // sprawdzenie czy martwiaka nie ma po drodze
        {
            if ($itm->getCurrentUser()->active==0){
                echo "non active user".PHP_EOL;
                $itm->delete();
                return null;
            }
        }

        if (count($items) < $offset){
            echo "not enough items to run in parallel".PHP_EOL;
            return null;
        }
        // var_dump($items);

        $queue = isset($items[$offset])?$items[$offset]:null;

        if (!$queue){
            echo "no queue items found".PHP_EOL;
            return null;
        }

        if ($params=$queue->getAdditionalParameters())
        {
            if (isset($params['objects_done']) && $params['objects_done'] > 0) {
                echo "objects done so no pararel".PHP_EOL;
                return null;
            }
        }

        return $queue;
    }
    public static function findLastForType(string $type, $userList=null)
    {
        echo "FIND LAST TYPE FOR QUEUE ".PHP_EOL;
        $queue_items = self::find()->where(['integration_type' => $type, 'integrated' => self::RUNNING])
            ->andWhere(['<', 'next_integration_date', date('Y-m-d H:i:s')]);
        if ($userList){
            $queue_items->andWhere(['current_integrate_user'=>$userList]);
        }
        $queue_items->orderBy(['next_integration_date' => SORT_ASC]);
        $queue_items=$queue_items->all();

        if ($queue_items) {
            echo "jobs running ".PHP_EOL;
            $preventUsers = [];


            var_dump($queue_items);
            foreach ($queue_items as $itm) {
                $preventUsers[] = $itm->current_integrate_user;
            }

            // var_dump($preventUsers);

            $queue_item = self::find()->where(['integration_type' => $type, 'integrated' => self::PENDING])
                ->andWhere(['<', 'next_integration_date', date('Y-m-d H:i:s')]);
            if ($userList){
                $queue_item->andWhere(['current_integrate_user'=>$userList]);
            }
            $queue_item->andWhere(['not in', 'current_integrate_user', $preventUsers])
                ->orderBy(['next_integration_date' => SORT_ASC]);
            $queue_item=$queue_item->one();

            // var_dump($queue_item);    

            if ($queue_item == null) {
                $queue_item = $queue_items[0];
            }
        } else {
            echo "nothing running yet ".PHP_EOL;
            $queue_item = self::find()->where(['integration_type' => $type, 'integrated' => self::PENDING])
                ->orderBy(['next_integration_date' => SORT_ASC])
                ->andWhere(['<', 'next_integration_date', date('Y-m-d H:i:s')]);
            if ($userList){
                $queue_item->andWhere(['current_integrate_user'=>$userList]);
            }
            echo $queue_item->createCommand()->getRawSql();
            $queue_item=$queue_item->one();
            if (!$queue_item){
                return $queue_item;
            }
        }
        // var_dump($queue_item);
        return $queue_item;
    }


    /**
     * @return int
     */
    public function getWhenFinished(): int
    {
        return $this->max_page - $this->page;
    }

    /**
     * @return queries\QueueQuery
     */
    public static function find(): queries\QueueQuery
    {
        return new \app\models\queries\QueueQuery(get_called_class());
    }

    /**
     * @param int $user_id
     * @param string $type
     *
     * @return Queue
     */
    protected static function createNewQueueItem(int $user_id = 0, $type = ''): Queue
    {
        $queue = new self();
        $queue->current_integrate_user = $user_id;
        $queue->integration_type = $type;
        $queue->integrated = self::PENDING;
        $queue->next_integration_date = self::getCurrentDateTime();
        $queue->page = 0;
        $queue->max_page = 0;
        $queue->save();

        return $queue;
    }

    /**
     * @return int
     */
    private static function getCurrentDateTime(): string
    {
        $current_date = new \DateTime('NOW');
        return $current_date->format('Y-m-d H:i:s');
    }

    public function getAdditionalParameters(){
        $result = unserialize((string)$this->parameters);
        return is_array($result) ? $result : [];
    }
    public function setAdditionalParameters($params){
        $this->parameters=serialize($params);
    }


    /**
     * Check if provided email is disallowed for feed generation
     *
     * @param $email
     * @return bool
     */
    public static function isDisallowedEmail($email) {
        $disallowedDomains = ["@allegromail", "@members.ebay", "@marketplace", "@zalando", "@pp-orders.zalan.do", "@mail.erli.pl"];
        $isDisallowedEmail = false;

        if ($email) {
            foreach ($disallowedDomains as $domain) {
                if (strpos($email, $domain) !== FALSE) {
                    $isDisallowedEmail = true;
                    break;
                }
            }
        }

        return $isDisallowedEmail;
    }
}
