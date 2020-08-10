<?php

class ControllerExtensionModuleHpSocialLogin extends Controller
{
    public $error = [];

    public function index($arg = [])
    {
        $onlyButton = isset($arg['onlybutton']) ? $arg['onlybutton'] : true;

        return $this->template($onlyButton);
    }

    public function login() {
        $this->load->model('account/customer');
        $this->load->language('account/login');

        $json['status'] = false;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateLogin()) {
            $json['status'] = true;
            unset($this->session->data['gcapcha']);
            // Unset guest
            unset($this->session->data['guest']);

            // Default Shipping Address
            $this->load->model('account/address');

            if ($this->config->get('config_tax_customer') == 'payment') {
                $this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
            }

            if ($this->config->get('config_tax_customer') == 'shipping') {
                $this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
            }

            // Wishlist
            if (isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])) {
                $this->load->model('account/wishlist');

                foreach ($this->session->data['wishlist'] as $key => $product_id) {
                    $this->model_account_wishlist->addWishlist($product_id);

                    unset($this->session->data['wishlist'][$key]);
                }
            }

            // Added strpos check to pass McAfee PCI compliance test (http://forum.opencart.com/viewtopic.php?f=10&t=12043&p=151494#p151295)

            if (isset($this->request->post['redirect']) && $this->request->post['redirect'] != $this->url->link('account/logout', '', true) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)) {
                $json['redirect'] = str_replace('&amp;', '&', $this->request->post['redirect']);
            } else {
                $json['redirect'] = $this->url->link('account/account', '', true);
            }
        }
        

        if (!empty($this->error)) {
            $json['error'] = $this->error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function validateLogin()
    {
        // HPPV
        if ($this->request->post['email'] == '' || $this->request->post['password'] == '') {
            $this->error['warning'] = $this->language->get('error_login');
            return false;
        }
        $isPhone = false;
        $re = '/^(([^<>()\[\]\\\\.,;:\s@"]+(\.[^<>()\[\]\\\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/m';
        if (preg_match($re, $this->request->post['email']) == 0 && !($this->request->post['email'] == '')) {
            $this->load->model('extension/module/phone_verification');

            $email = $this->model_extension_module_phone_verification->getEmailByPhone($this->request->post['email']);
            if ($email) {
                $isPhone = false;
                $this->request->post['email'] = $email;
            } else {
                $isPhone = true;
            }

        }
        // if ($this->config->get('module_hp_social_login_captcha_status')) {
        //     if (($this->session->data['captcha']['code'] != $this->request->post['captcha'])) {
        //         $this->error['warning'] = "Captcha Salah";
        //     }
        // }

        if ($this->config->get('module_hp_social_login_captcha_status')) {
            if (empty($this->session->data['gcapcha'])) {
                $this->load->language('extension/captcha/google');
    
                if (!isset($this->request->post['g-recaptcha-response'])) {
                    $this->error['warning'] = $this->language->get('error_captcha');
                }
    
                $recaptcha = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($this->config->get('captcha_google_secret')) . '&response=' . $this->request->post['g-recaptcha-response'] . '&remoteip=' . $this->request->server['REMOTE_ADDR']);
    
                $recaptcha = json_decode($recaptcha, true);
    
                if ($recaptcha['success']) {
                    $this->session->data['gcapcha']	= true;
                } else {
                    $this->error['warning'] = $this->language->get('error_captcha');
                }
            }
        }

        $login_info = $this->model_account_customer->getLoginAttempts($this->request->post['email']);

        if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
            $this->error['warning'] = $this->language->get('error_attempts');
        }

        if (!$isPhone) {
            $customer_info = $this->model_account_customer->getCustomerByEmail($this->request->post['email']);

            if ($customer_info && !$customer_info['status']) {
                if ($this->config->get('module_hp_social_login_email_status')) {
                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();
                    $defaultLanguage = $this->config->get('config_language');
                    $language_id = $languages[$defaultLanguage]['language_id'];

                    $template = $this->config->get('module_hp_social_login_email_verification_text_' . $language_id);

                    $find = array(
                        "{email}",
                        "{resend}",
                    );

                    $replace = array(
                        "email" => $this->request->post['email'],
                        "resend" => $this->url->link('extension/module/email_verification/resend', 'email=' . $customer_info['email'], true),
                    );

                    $msg = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $template))));

                    $this->error['warning'] = html_entity_decode($msg);

                } else {
                    $this->error['warning'] = $this->language->get('error_approved');
                }

            }
        }

        if (!$this->error) {

            if ($isPhone) {
                $logged = $this->customer->loginByPhone($this->request->post['email'], $this->request->post['password']);
            } else {
                $logged = $this->customer->login($this->request->post['email'], $this->request->post['password']);
            }

            if (!$logged) {

                $this->error['warning'] = $this->language->get('error_login');

                $this->model_account_customer->addLoginAttempt($this->request->post['email']);
            } else {
                $this->model_account_customer->deleteLoginAttempts($this->request->post['email']);
            }
        }

        return !$this->error;

    }

    public function template($onlyButton)
    {
        if ($this->config->get('module_hp_social_login_status')) {
            $this->load->language('extension/module/hp_social_login');
            $this->document->addScript('catalog/view/javascript/jquery.validate.min.js');

            $data['social_login'] = true;
            $data['facebook_login'] = $this->config->get('module_hp_social_login_facebook_status');
            $data['google_login'] = $this->config->get('module_hp_social_login_google_status');
            $data['sms_login'] = $this->config->get('module_hp_social_login_sms_status');

            if ($data['facebook_login']) {
                $facebook_app_id = $this->config->get("module_hp_social_login_facebook_app_id");
                $redirect_url = $this->url->link('account/login', '', true);
                $state = rand(0, 199999);
                $scopes = ['email'];
                $data['facebook'] = 'https://www.facebook.com/v5.0/dialog/oauth?client_id=' . $facebook_app_id . '&redirect_uri=' . urlencode($redirect_url) . '&state=' . urlencode(json_encode($state)) . '&response_type=token&scope=' . implode(",", $scopes);
                $data['facebook_handler'] = $this->url->link('extension/module/hp_social_login/facebook', '', true);
            }

            if ($data['google_login']) {
                $param = array(
                    "client_id" => $this->config->get("module_hp_social_login_google_client"),
                    "redirect_uri" => $this->url->link("extension/module/hp_social_login/google", "", true),
                    "scope" => "profile email",
                    "response_type" => "code",
                );
                $data['google'] = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($param);
            }

            // $this->load->language('account/login');
            // $data['entry_phone_email'] = $this->language->get('entry_email');
            // $this->load->language('account/register');

            if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
            } else {
                $data['error_warning'] = '';
            }

            if (isset($this->error['firstname'])) {
                $data['error_firstname'] = $this->error['firstname'];
            } else {
                $data['error_firstname'] = '';
            }

            if (isset($this->error['lastname'])) {
                $data['error_lastname'] = $this->error['lastname'];
            } else {
                $data['error_lastname'] = '';
            }

            if (isset($this->error['email'])) {
                $data['error_email'] = $this->error['email'];
            } else {
                $data['error_email'] = '';
            }

            if (isset($this->error['telephone'])) {
                $data['error_telephone'] = $this->error['telephone'];
            } else {
                $data['error_telephone'] = '';
            }

            if (isset($this->error['password'])) {
                $data['error_password'] = $this->error['password'];
            } else {
                $data['error_password'] = '';
            }

            if (isset($this->error['confirm'])) {
                $data['error_confirm'] = $this->error['confirm'];
            } else {
                $data['error_confirm'] = '';
            }

            if (isset($this->request->post['redirect']) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)) {
                $data['redirect'] = $this->request->post['redirect'];
            } elseif (isset($this->session->data['redirect'])) {
                $data['redirect'] = $this->session->data['redirect'];

                unset($this->session->data['redirect']);
            } else {
                $data['redirect'] = '';
            }
           

            $data['forgotten'] = sprintf($this->language->get('text_forgot'), $this->url->link('account/forgotten', '', true));
            $data['captcha_status'] = $this->config->get('module_hp_social_login_captcha_status');
            $data['google_sitekey'] = $this->config->get('captcha_google_key');

            $data['action'] = $this->url->link('extension/module/hp_social_login/login', '', true);
            $data['register'] = $this->url->link('extension/module/hp_social_login/registerEmail', '', true);
            $data['registerSMS'] = $this->url->link('extension/module/hp_social_login/registerSMS', '', true);

            if(!$onlyButton){
                return $this->load->view('extension/module/hp_social_login', $data);
            }else{
                return $this->load->view('extension/module/hp_social_login_button', $data);
            }

        }

        return null;

    }

    public function registerEmail()
    {
        $this->load->model('account/customer');
        $this->load->language('account/register');

        $json['status'] = false;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateRegisterEmail()) {
            unset($this->session->data['gcapcha']);
            $json['status'] = true;
            $customer_id = @$this->model_account_customer->addCustomer($this->request->post);

            // Clear any previous login attempts for unregistered accounts.
            $this->model_account_customer->deleteLoginAttempts($this->request->post['email']);

            if ($this->config->get('module_hp_social_login_email_status')) {
                $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET status = '0' WHERE customer_id = '" . (int) $customer_id . "'");
                $this->db->query("INSERT INTO `" . DB_PREFIX . "customer_approval` SET customer_id = '" . (int) $customer_id . "', type = 'customer', date_added = NOW()");
            } else {
                $this->customer->login($this->request->post['email'], $this->request->post['password']);
            }

            $this->session->data['customer_email'] = $this->request->post['email'];

            unset($this->session->data['guest']);

            if ($this->config->get('module_hp_social_login_redirect')) {
                $json['redirect'] = $this->url->link('common/home', '', true);
            }

            $json['redirect'] = $this->url->link('account/success');
        }

        if (!empty($this->error)) {
            $json['error'] = $this->error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function registerSMS()
    {
        $this->load->model('account/customer');

        $json['status'] = false;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateRegisterSMS()) {
            $json['status'] = true;
            unset($this->session->data['gcapcha']);
            $customer = array(
                "firstname" => "",
                "lastname" => "",
                "email" => "",
                "telephone" => $this->request->post['telephone'],
                "password" => $this->request->post['password'],
            );

            $this->model_account_customer->addCustomer($customer);

            $this->customer->loginByPhone($customer['telephone'], '', true);

            if ($this->config->get('module_hp_social_login_redirect')) {
                $json['redirect'] = $this->url->link('common/home', '', true);
            }

            $json['redirect'] = $this->url->link('account/success');
        }
        if (!empty($this->error)) {
            $json['error'] = $this->error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function facebook()
    {
        if (!isset($this->request->get['acces_token'])) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customer');
        $this->load->model('extension/module/hp_social_login');

        $code = $this->request->get['acces_token'];

        $profile = $this->model_extension_module_hp_social_login->getFbProfile($code);

        $customer_info = $this->model_account_customer->getCustomerByEmail($profile['email']);

        if ($customer_info) {
            if ($this->validate($customer_info['email'])) {
                $this->completeLogin();

                $redirect_uri = isset($this->request->cookie['hpasl_redirect']) ? $this->request->cookie['hpasl_redirect'] : $this->url->link('account/account', '', true);

                $this->response->redirect($redirect_uri);
            } else {
                $this->session->data['error'] = $this->error['warning'];
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        } else {

            $customer = array(
                "firstname" => $profile['first_name'],
                "lastname" => $profile['last_name'],
                "email" => $profile['email'],
                "telephone" => "",
            );

            $this->model_account_customer->addCustomer($customer);

            if ($this->validate($customer['email'])) {
                $this->completeLogin();

                $redirect_uri = isset($this->request->cookie['hpasl_redirect']) ? $this->request->cookie['hpasl_redirect'] : $this->url->link('account/account', '', true);

                $this->response->redirect($redirect_uri);
            } else {
                $this->session->data['error'] = $this->error['warning'];
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        }

    }

    public function google()
    {
        if (isset($this->request->get['error'])) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customer');
        $this->load->model('extension/module/hp_social_login');

        $code = $this->request->get['code'];

        $profile = $this->model_extension_module_hp_social_login->getGoogleProfile($code);

        if (!$profile) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $customer_info = $this->model_account_customer->getCustomerByEmail($profile['email']);

        if ($customer_info) {
            if ($this->validate($customer_info['email'])) {
                $this->completeLogin();

                $redirect_uri = isset($this->request->cookie['hpasl_redirect']) ? $this->request->cookie['hpasl_redirect'] : $this->url->link('account/account', '', true);

                $this->response->redirect($redirect_uri);
            } else {
                $this->session->data['error'] = $this->error['warning'];
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        } else {

            $customer = array(
                "firstname" => $profile['given_name'],
                "lastname" => $profile['family_name'],
                "email" => $profile['email'],
                "telephone" => "",
            );

            $this->model_account_customer->addCustomer($customer);

            if ($this->validate($customer['email'])) {
                $this->completeLogin();

                $redirect_uri = isset($this->request->cookie['hpasl_redirect']) ? $this->request->cookie['hpasl_redirect'] : $this->url->link('account/account', '', true);

                $this->response->redirect($redirect_uri);
            } else {
                $this->session->data['error'] = $this->error['warning'];
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        }

    }

    protected function completeLogin()
    {
        unset($this->session->data['guest']);

        // Default Shipping Address
        $this->load->model('account/address');

        if ($this->config->get('config_tax_customer') == 'payment') {
            $this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
        }

        if ($this->config->get('config_tax_customer') == 'shipping') {
            $this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
        }

    }

    public function updateProfile()
    {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('common/home', '', true));
        }

        $error = false;

        $this->load->model('account/customer');
        $this->load->model('extension/module/hp_social_login');
        $this->load->language('account/edit');

        $json['status'] = false;

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

            if (isset($this->request->post['email'])) {
                if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->error['warning'] = $this->language->get('error_email');
                }
    
                if (($this->customer->getEmail() != $this->request->post['email']) && $this->model_account_customer->getTotalCustomersByEmail($this->request->post['email'])) {
                    $this->error['warning'] = $this->language->get('error_exists');
                }
            }

            if (isset($this->request->post['telephone'])) {
                if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
                    $this->error['warning'] = $this->language->get('error_telephone');
                } 

                if ($this->model_extension_module_hp_social_login->getTotalCustomersByPhone($this->request->post['telephone'])) {
                    $this->error['warning'] = $this->language->get('error_telephone_exists');
                }
            }       
            
            if (!empty($this->error)) {
                $json['error'] = $this->error;
            } else {
                $json['status'] = true;
                $this->model_extension_module_hp_social_login->editCustomer($this->customer->getId(), $this->request->post);
            }

        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
        
    }

    protected function validate($email)
    {

        $this->load->language('account/login');

        // Check how many login attempts have been made.
        $login_info = $this->model_account_customer->getLoginAttempts($email);

        $this->error = false;

        if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
            $this->error['warning'] = $this->language->get('error_attempts');
        }

        // Check if customer has been approved.
        $customer_info = $this->model_account_customer->getCustomerByEmail($email);

        if ($customer_info && !$customer_info['status']) {
            // enable current customer
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET status = '1' WHERE customer_id = '" . (int) $customer_info['customer_id'] . "'");

            $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_approval` WHERE customer_id = '" . (int) $customer_info['customer_id'] . "' AND `type` = 'customer'");

        }

        if (!$this->error) {
            if (!$this->customer->login($email, '', true)) {
                $this->error['warning'] = $this->language->get('error_login');

                $this->model_account_customer->addLoginAttempt($email);
            } else {
                $this->model_account_customer->deleteLoginAttempts($email);
            }
        }

        return !$this->error;
    }

    protected function validateRegisterEmail()
    {
        if ($this->model_account_customer->getTotalCustomersByEmail($this->request->post['email'])) {
            $this->error['warning'] = $this->language->get('error_exists');
        }
        // if ($this->config->get('module_hp_social_login_captcha_status')) {
        //     if ($this->session->data['captcha']['code'] != $this->request->post['captcha']) {
        //         $this->error['warning'] = "Captcha Salah";
        //     }
        // }

         if ($this->config->get('module_hp_social_login_captcha_status')) {
            $this->load->language('extension/captcha/google');

			if (!isset($this->request->post['g-recaptcha-response'])) {
                $this->error['warning'] = $this->language->get('error_captcha');
			}

			$recaptcha = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($this->config->get('captcha_google_secret')) . '&response=' . $this->request->post['g-recaptcha-response'] . '&remoteip=' . $this->request->server['REMOTE_ADDR']);

			$recaptcha = json_decode($recaptcha, true);

			if ($recaptcha['success']) {
				$this->session->data['gcapcha']	= true;
			} else {
                $this->error['warning'] = $this->language->get('error_captcha');
			}
        }

        return !$this->error;
    }

    protected function validateRegisterSMS()
    {
        $this->load->language('extension/module/hp_social_login');
        $this->load->model('extension/module/hp_social_login');
        $prefix = substr($this->request->post['telephone'], 0, 2);

        if ($prefix = "+6"){
            $no_telephone = substr($this->request->post['telephone'], 3, 12); 
        }else if ($prefix = "62"){
            $no_telephone = substr($this->request->post['telephone'], 2, 12); 
        }else{
            $no_telephone = substr($this->request->post['telephone'], 1, 12); 
        }

        if ($this->model_extension_module_hp_social_login->getTotalCustomersByPhone($no_telephone)) {
            $this->error['warning'] = $this->language->get('error_sms');
        }
        // if ($this->config->get('module_hp_social_login_captcha_status')) {
        //     if ($this->session->data['captcha']['code'] != $this->request->post['captcha']) {
        //         $this->error['warning'] = "Captcha Salah";
        //     }
        // }

        if (empty($this->session->data['gcapcha'])) {
			$this->load->language('extension/captcha/google');

			if (!isset($this->request->post['g-recaptcha-response'])) {
                $this->error['warning'] = $this->language->get('error_captcha');
			}

			$recaptcha = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($this->config->get('captcha_google_secret')) . '&response=' . $this->request->post['g-recaptcha-response'] . '&remoteip=' . $this->request->server['REMOTE_ADDR']);

			$recaptcha = json_decode($recaptcha, true);

			if ($recaptcha['success']) {
				$this->session->data['gcapcha']	= true;
			} else {
                $this->error['warning'] = $this->language->get('error_captcha');
			}
		}


        return !$this->error;
    }
}