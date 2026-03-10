<?php
namespace app\modules\xml_generator\src;

use app\models\Queue;
use app\models\User;

/**
 * Class XmlFeed
 *
 * @property int $id
 * @property int $integrated
 * @property int $to_integrate
 * @property int $next_integration_date
 * @property string $integration_type
 *
 * @package app\modules\xml_generator\src
 */
class XmlFeed implements FeedGenerator
{
    const PRODUCT            = 'product';
    const ORDER              = 'order';
    const CUSTOMER           = 'customer';
    const CATEGORY           = 'category';
    const TAGS               = 'tags';
    const SUBSCRIBERS_IMPORT = 'subscribersimport';

    /**
     * @var string
     */
    private $_type;

    /**
     * @var User
     */
    protected $_user;

    /**
     * @var string
     */
    protected $_path;

    /**
     * @var string
     */
    protected $_token;

    /**
     * @var Queue
     */
    protected $_queue;

    /**
     * @param null $what
     * @return int
     *
     * @throws \Exception
     */
    public function generate($what = null): int
    {
        $feed_object = null;

        switch ($this->_type) {
            case self::PRODUCT:
                $feed_object = new ProductFeed();
                break;
            case self::CATEGORY:
                $feed_object = new CategoryFeed();
                break;
            case self::ORDER:
                $feed_object = new OrderFeed();
                break;
            case self::CUSTOMER:
                $feed_object = new CustomerFeed();
                break;
            case self::TAGS:
                $feed_object = new Tags();
                break;
            case 'subscribers':
                $feed_object = new SubscribersFeed();
                break;
            case 'phonesubscribers':
                $feed_object = new PhonesubscribersFeed();
                break;
            default:
                throw new \Exception('Cannot create feed. Invaild feed type');
        }


        return $feed_object
            ->setType($this->_type)
            ->setUser($this->_queue->getCurrentUser())
            ->setQueue($this->_queue)
            ->generate($what);
    }

    /**
     * Returns writable base path for feed files.
     * On Heroku (read-only filesystem) set FEEDS_PATH=/tmp/feeds env var.
     * Defaults to @runtime/feeds (runtime/ must be writable).
     */
    public static function getFeedsBasePath(): string
    {
        $env = getenv('FEEDS_PATH');
        if ($env !== false && $env !== '') {
            return rtrim($env, '/');
        }
        return \Yii::getAlias('@runtime') . '/feeds';
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        if ($temp) {
            $ext = '.xml.tmp';
        } else {
            $ext = '.xml';
        }

        $base      = self::getFeedsBasePath();
        $dir       = $base . '/' . $this->_type . '/' . $this->_user->uuid;
        $file_path = $dir . '/' . $this->_type . $ext;

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($get_file_path) {
            return $file_path;
        }

        if (! is_file($file_path)) {
            if (($queue = $this->_queue) == null) {
                $queue = Queue::findLastForType($this->_type);
            }

            $minutes = $queue->getWhenFinished();

            $info_xml = new \SimpleXMLElement('<INFO/>');
            $info_xml->addChild('NOTICE', "Feed is generating. Come back in $minutes minutes.");
            return $info_xml->asXML();
        }

        return file_get_contents($file_path);
    }

    /**
     * @return bool
     */
    public function isFinished()
    {
        if ($this->_queue->max_page == 0 && $this->_queue->page == 0) {
            return false;
        }

        return $this->_queue->page >= $this->_queue->max_page;
    }

    /**
     * @param User $user
     *
     * @return $this
     */
    public function setUser(User $user): XmlFeed
    {
        $this->_user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->_queue->getCurrentUser();
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type): XmlFeed
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token): XmlFeed
    {
        $this->_token = $token;

        return $this;
    }

    public function setQueue(Queue $queue): XmlFeed
    {
        $this->_queue = $queue;

        return $this;
    }

    /**
     * @param $date
     *
     * @return string
     * @throws \Exception
     */
    public function getCorrectSambaDate($date): string
    {
        $datetime = new \DateTime($date);
//        return $datetime->format('Y-m-d H:i:s.')
        return $datetime->format(DATE_RFC3339_EXTENDED);
    }
    public function getCorrectDbDate($date): string
    {
        return date('Y-m-d H:i:s', strtotime($date));
    }
}
