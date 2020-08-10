<?php

class ControllerExtensionModulePhoneVerification extends Controller
{

    public function sendVerificationCode() {
        $status = false;
        if (isset($this->request->get['phone'])) {

            $phone = $this->request->get['phone'];
            $code  = $this->getCode($phone);

            $message = $this->createMsg($code);

            $status = $this->sendSms($phone, $message);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($status));
    }

    public function checkVerificationCode() {
        $status = false;

        if (isset($this->request->get['phone']) && isset($this->request->get['code'])) {

            $phone = $this->request->get['phone'];
            $code = $this->request->get['code'];
            $actual_code = $this->getCode($phone);

            if ($code == $actual_code) {
                $status = true;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($status));
    }

    private function generateCode() {
        $count = 4;
        $result = "";

        for ($i = 1; $i <= $count; $i++) {
            $result .= rand(0, 9);
        }

        return $result;
    }

    private function getCode($phone) {
        $time = $this->config->get('module_hp_social_login_sms_expiry') * 60;
        $cache = new \Cache('file', $time);
        $cacheSuffix = "hpasl";

        if ($cache->get($cacheSuffix . $phone)) {
            return $cache->get($cacheSuffix . $phone);
        } else {
            $code = $this->generateCode();
            $cache->set($cacheSuffix . $phone, $code);

            return $code;
        }
    }

    private function createMsg($code) {
        $default_format = 'Demi keamanan akun Anda, mohon tidak memberikan kode verifikasi kepada siapapun. kode verifikasi berlaku 15 menit: {code}';
        $setting_format = $this->config->get('module_hp_social_login_message_' . $this->config->get('config_language_id'));
        if (is_null($setting_format)) {
            $template = $default_format;
        } else {
            $template = $setting_format;
        }

        $find = array(
            '{code}',
            '{time}',
        );
        $replace = array(
            'code' => $code,
            'time' => $this->config->get('module_hp_social_login_sms_expiry'),
        );

        $msg = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $template))));

        return $msg;
    }

    private function sendSms($phone, $message) {
        $gateway = $this->config->get('module_hp_social_login_sms_gateway');

        switch ($gateway) {
            case 'zenviva':
                return $this->zenvivaSMS($phone, $message);
                break;
            case 'wavecell':
                return $this->wavecellSMS($phone, $message);
                break;

            default:
                return false;
                break;
        }
    }

    public function wavecellSMS($phone, $message) {
        $curl = curl_init();
        $phone = preg_replace('/^(^\+62\s?|^0)/m', "+62", $phone);

        // $passkey = urlencode("zenzivaB1sm1ll@hi");
        // $userkey = "23x78u";
        $passkey = urlencode($this->config->get('module_hp_social_login_sms_passkey'));
        $userkey = $this->config->get('module_hp_social_login_sms_userkey');


        $postRequest = array(
            "source" => "abcde",
            "destination" => $phone,
            "text" => $message,
            "encoding" => "AUTO",
        );
        $headers = array();
        $headers[] = 'Authorization: Bearer ' . $passkey;
        $headers[] = 'Content-Type: application/json';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.wavecell.com/sms/v1/" . $userkey . "/single",
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "AUTO",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => 1,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postRequest),
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        $err = curl_error($curl);
        curl_close($curl);

        if ($response->status->code == "QUEUED") {
            return true;
        } else {
            return false;
        }
    } 

    public function zenvivaSMS($phone, $message) {
        $curl = curl_init();

        // $passkey = urlencode("zenzivaB1sm1ll@hi");
        // $userkey = "23x78u";
        $passkey = urlencode($this->config->get('module_hp_social_login_sms_passkey'));
        $userkey = $this->config->get('module_hp_social_login_sms_userkey');
        $message = urlencode($message);

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://reguler.zenziva.net/apps/smsapi.php?userkey=" . $userkey . "&passkey=" . $passkey . "&nohp=" . $phone . "&pesan=" . $message,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "",
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        } else {
            return true;
        }

        // $XMLdata = new SimpleXMLElement($response);
        // $status = $XMLdata->message[0]->text;
        // echo $status;

    }
}
