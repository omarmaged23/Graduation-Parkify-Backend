<?php

namespace App\Http\Controllers\Traits;

use Twilio\Rest\Client;

trait NotificationTrait
{
    private function sendSms($message,$phone){
        $account_sid = getenv("TWILIO_SID");
        $auth_token = getenv("TWILIO_TOKEN");
        $twilio_number = getenv("TWILIO_FROM");
        $client = new Client($account_sid, $auth_token);
        $client->messages->create('+2'.$phone, [
            'from' => $twilio_number,
            'body' => $message
        ]);
    }

}
