<?php
namespace app\models;

use yii\base\Model;

class RegisterForm extends Model
{
    public $username;
    public $password;
    public $email;
    public $user;
    public $shop_type;
    public $active;

    public function rules()
    {
        return [
            [['username', 'password', 'email'], 'required', 'message' => 'Pole {attribute} nie może być puste'],
            [['active'], 'integer'],
            [['username', 'email'], 'unique', 'targetAttribute' => ['username', 'email']],
            [['shop_type'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'username' => 'Adres domeny technicznej',
            'email' => 'Email',
            'password' => 'Hasło',
            'shop_type' => 'Typ sklepu',
        ];
    }


    /**
     * Action after model load, save user into database
     *
     * @return bool
     */
    public function register()
    {
        $this->user = new User();

        try {
        $this->user = $this->user->register($this->username, $this->email, $this->password, $this->shop_type);
        $this->user->active=$this->active;
        $this->user->save();
        } catch(\Exception $e) {
            throw $e;
        }
        
        return $this->user;
    }

    public function login()
    {

        return \Yii::$app->user->login($this->user, 0);
    }
}