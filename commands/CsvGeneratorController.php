<?php
namespace app\commands;

use yii\console\Controller;
use app\models\Customers;

class CsvGeneratorController extends Controller
{
    public function actionIndex()
    {
//        $csv_file = 'customers.csv';
        $csv = array_map("str_getcsv", file(__DIR__."/customers.csv"));
        $keys = array_shift($csv);

        foreach($keys as $i=>$key) {
            // if($key == "") {
            //     $keys[$i] = "test$i";
            // }
            $keys[$i]=trim($keys[$i]);
        }

       // var_dump($keys); 
       // die;

        foreach ($csv as $i=>$row) {

            try {
                foreach ($row as $a => $b) {
                    // if ($b == "") {
                    //     $row[$a] = "test";
                    // }
                }

                $csv[$i] = array_combine($keys, $row);
            } catch (\yii\base\ErrorException $e) {
                echo "błąd = ".json_encode($row);
                continue;
            }
        }
        // echo "DSFFD";
        // print_r($csv);
//
        foreach($csv as $row) {
            $customer = new Customers();
            if(isset($row["﻿CUSTOMER_ID"])) {
                $row["CUSTOMER_ID"]=$row["﻿CUSTOMER_ID"];
            }
            // print_r($row);
            if(!isset($row["CUSTOMER_ID"])) continue;

            // echo "uno";

            $customer->customer_id = $row["CUSTOMER_ID"];
            $customer->email = $row["EMAIL"];

            $registration = $row["REGISTRATION"];
            if($registration == "0000-00-00 00:00" || $registration == null) {
                $registration = '2000-01-01 00:00:00';
            }
            $customer->registration = $registration;

            $customer->first_name = $row["FIRST_NAME"];
            $customer->lastname = $row["LAST_NAME"];
            $customer->zip_code = $row["ZIP_CODE"];
            $customer->phone = $row["PHONE"];

            // if(preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$row["Newsletter E-mail"], $matches)) {
            //     $customer->newsletter_frequency = "every day";
            //     $customer->nlf_time = ($matches !== '0000-00-00 00:00') ? $matches : $registration;
            //     $customer->data_permission = 'full';
            // } else {
                $customer->newsletter_frequency = "never";
                $customer->nlf_time = $registration;
                $customer->data_permission = 'do_not_personalize';
            // }


            // if(preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$row["Newsletter SMS"], $matches)) {
            //     $customer->sms_frequency = $matches;
            // } else {
                $customer->sms_frequency = null;
            // }


            if ($row["PARAMETER: czarna lista tel"]){
                $parameters['czarna lista tel']=$row["PARAMETER: czarna lista tel"];
            }
            if ($row["PARAMETER: czarna lista mail"]){
                $parameters['czarna lista mail']=$row["PARAMETER: czarna lista mail"];
            }
            if ($row["PARAMETER: status telefon"]){
                $parameters['status telefon']=$row["PARAMETER: status telefon"];
            }
            if ($row["Parameter: status mail"]){
                $parameters['status mail']=$row["Parameter: status mail"];
            }
            if ($row["PARAMETER: Miasto"]){
                $parameters['Miasto']=$row["PARAMETER: Miasto"];
            }
            if ($row["PARAMETER: Kraj"]){
                $parameters['Kraj']=$row["PARAMETER: Kraj"];
            }
            if ($row["PARAMETER: Data ostatniego zakupu - Salon"]){
                $parameters['Data ostatniego zakupu - Salon']=$row["PARAMETER: Data ostatniego zakupu - Salon"];
            }
            if ($row["PARAMETER: punkty lojalnosciowe"]){
                $parameters['punkty lojalnosciowe']=$row["PARAMETER: punkty lojalnosciowe"];
            }
            if ($row["PARAMETER: data urodzin"]){
                $parameters['data urodzin']=$row["PARAMETER: data urodzin"];
            }

            // print_r($parameters);
            // die();

            // if($row["Tagi klienta"] !== "Brak tagów") {
            //     $tags = [];
            //     $tags_array = explode(",", $row["Tagi klienta"]);

            //     foreach($tags_array as $tag) {
            //         $tag_ex = explode(":", $tag);

            //         if(isset($tags[0]) && isset($tags[1])) {
            //             $tags[] = [
            //                 'tagName' => $tag_ex[0],
            //                 'tagValue' => $tag_ex[1]
            //             ];
            //         } else {
            //             $tags = [];
            //         }
            //     }
            //     $customer->tags = serialize($tags);
            // } else {
            //     $customer->tags = serialize([]);
            // }
            $customer->server_response = "";
            $customer->error = "";
            $customer->data_hash = md5(serialize($row));
            $customer->last_modification_date = $registration;
            $customer->user_id = 29;
            $customer->page = 0;
            $customer->parameters = json_encode($parameters);
            if((Customers::find()->where(['customer_id' => $customer->customer_id, 'user_id' => $customer->user_id])->one()) == null) {
                $customer->save(false);
            }
            // print_r($customer);
            // die ("STOP");
        }

//        var_dump($csv);
    }
}