<?php

class ModelExtensionModuleHpSocialLogin extends Model
{
    public function getTotalCustomersByPhone($phone) {
         $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer WHERE telephone = '" . $this->db->escape($phone) . "'");

        return $query->row['total'];
    }
    
    public function editCustomer($customer_id, $data) {
        if(isset($data['email'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "customer SET email = '" . $this->db->escape($data['email']) . "' WHERE customer_id = '" . (int)$customer_id . "'");
        }      
        if(isset($data['telephone'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "customer SET telephone = '" . $this->db->escape($data['telephone']) . "' WHERE customer_id = '" . (int)$customer_id . "'");
        }    
    }

    public function getFbProfile($access_token)
    {

        $curl = curl_init();

        $fields = ["email", "name", "first_name", "last_name"];

        $params = array(
            'access_token' => $access_token,
            'fields' => implode(',', $fields),
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://graph.facebook.com/me?" . http_build_query($params),
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
            return json_decode($response, true);
        }
    }

    public function getGoogleProfile($code)
    {
        $access_token = $this->getGoogleToken($code);

        if (!$access_token) {
            return false;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.googleapis.com/oauth2/v1/userinfo",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer ".$access_token,
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        } else {
            return json_decode($response,true);
        }

    }

    public function getGoogleToken($code)
    {
        $curl = curl_init();

        $data = array(
            "code" => $code,
            "client_id" => $this->config->get("module_hp_social_login_google_client"),
            "client_secret" => $this->config->get("module_hp_social_login_google_secret"),
            "redirect_uri" => $this->url->link("extension/module/hp_social_login/google", "", true),
            "grant_type" => "authorization_code",
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://oauth2.googleapis.com/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        } else {
            $response = json_decode($response, true);
            if (isset($response['access_token'])) {
                return $response['access_token'];
            } else {
                return false;
            }
        }

    }
}
