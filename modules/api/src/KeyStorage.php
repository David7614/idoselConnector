<?php
namespace app\modules\api\src;

class KeyStorage implements \app\modules\IAI\Application\PublicKeyStorageInterface
{
    const KEY_PATH = '/public.key';
    private $_username;


    public function __construct($username)
    {
        $this->_username = $username;
    }

    public function store($key)
    {
        file_put_contents($this->getKeyLocation(), $key);
    }

    public function retrieve()
    {
        $keyLocation = $this->getKeyLocation();

        if (file_exists($keyLocation)) {
            $contents = file_get_contents($keyLocation);
            if (!empty($contents)) {
                return $contents;
            }
        }

        return null;
    }

    protected function getKeyLocation()
    {
        $username = strtolower($this->_username);
        $username = htmlspecialchars($username);
        $username = trim($username);
        $username = str_replace(['ą', 'ę', 'ź', 'ć', 'ś', 'ż', 'ł', 'ó', 'ń'], ['a', 'e', 'z', 'c', 's', 'z', 'l', 'o', 'n'], $username);

        if(!is_dir(__DIR__ . DIRECTORY_SEPARATOR . $username)) {
            mkdir(__DIR__ . DIRECTORY_SEPARATOR . $username, 0766);
        }

        return __DIR__  . DIRECTORY_SEPARATOR . $username . self::KEY_PATH;
    }
}