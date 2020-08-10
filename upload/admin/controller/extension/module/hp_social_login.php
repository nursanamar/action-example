<?php

class ControllerExtensionModuleHpSocialLogin extends Controller {
    private $error = array();
   
    private $version = '1.6.5';

    public function index() {
        $this->language->load('extension/module/hp_social_login');

        $this->rightman();

        if ($_SERVER['SERVER_NAME'] != $this->v_d) {
            $this->storeAuth();
        } else {

            if ($this->validateTable()) {
                $this->setting();
            } else {
                $this->installPage();
            }
        }
    }

    public function storeAuth() {
        $data['curl_status'] = $this->curlcheck();

        $this->flushdata();

        if (isset($this->error['warning'])) {
            $data['no_internet_access'] = $this->error['warning'];
        } else {
            $data['no_internet_access'] = '';
        }

        $this->document->setTitle($this->language->get('text_validation'));

        $data['version'] = $this->version;
        $data['text_curl'] = $this->language->get('text_curl');
        $data['text_disabled_curl'] = $this->language->get('text_disabled_curl');

        $data['text_validation'] = $this->language->get('text_validation');
        $data['text_validate_store'] = $this->language->get('text_validate_store');
        $data['text_information_provide'] = $this->language->get('text_information_provide');
        $data['domain_name'] = $this->language->get('text_validate_store');
        $data['domain_name'] = $_SERVER['SERVER_NAME'];

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false,
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title2'),
            'href' => $this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false,
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/validation', $data));
    }

   
    public function flushdata() {
        $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `code` LIKE '%module_bundle%'");
    }

    public function curlcheck() {
        return in_array('curl', get_loaded_extensions()) ? true : false;
    }

    public function setting() {
        $this->load->language('extension/module/hp_social_login');

        $data['heading_title'] = $this->language->get('heading_title2');

        $this->document->setTitle($this->language->get('heading_title2'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        $this->load->model('extension/module/hp_social_login');

        $this->document->addScript('view/javascript/bootstrap/js/bootstrap-toggle.min.js');
        $this->document->addStyle('view/javascript/bootstrap/css/bootstrap-toggle.min.css');

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['version'] = $this->version;
        $inputs = array(
            array(
                "name" => "status",
                "default" => 0,
            ),
            array(
                "name" => "customer_group",
                "default" => 1,
            ),
            array(
                "name" => "redirect",
                "default" => 0,
            ),

            array(
                "name" => "facebook_status",
                "default" => 0,
            ),
            array(
                "name" => "facebook_app_id",
                "default" => "",
            ),
            array(
                "name" => "facebook_app_secret",
                "default" => "",
            ),
            array(
                "name" => "google_status",
                "default" => 0,
            ),
            array(
                "name" => "google_client",
                "default" => "",
            ),
            array(
                "name" => "google_secret",
                "default" => "",
            ),
            array(
                "name" => "sms_status",
                "default" => 0,
            ),
            array(
                "name" => "sms_expiry",
                "default" => 5,
            ),
            array(
                "name" => "sms_passkey",
                "default" => "",
            ),
            array(
                "name" => "sms_userkey",
                "default" => "",
            ),
            array(
                "name" => "sms_gateway",
                "default" => "zenviva",
            ),
            array(
                "name" => "email_status",
                "default" => 0,
            ),
             array(
                "name" => "captcha_status",
                "default" => 0,
            ),
        );

        foreach ($data['languages'] as $language) {
            $inputs[] = array(
                "name" => "message_" . $language['language_id'],
                "default" => "Demi keamanan akun Anda, mohon tidak memberikan kode verifikasi kepada siapapun termasuk tim Ikioke. kode verifikasi berlaku {time} menit: {code}",
            );

            $inputs[] = array(
                "name" => "email_resend_text_" . $language['language_id'],
                "default" => $this->language->get('placeholder_resend_text'),
            );
            $inputs[] = array(
                "name" => "email_verification_text_" . $language['language_id'],
                "default" => $this->language->get('placeholder_email_text'),
            );

            $inputs[] = array(
                "name" => "email_message_text_" . $language['language_id'],
                "default" => $this->language->get('placeholder_message_text'),
            );
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $code = "module_hp_social_login";
            $setting = [];

            foreach ($inputs as $input) {
                $setting[$code . "_" . $input['name']] = isset($this->request->post[$input['name']]) ? $this->request->post[$input['name']] : $input['default'];
            }

            $this->model_setting_setting->editSetting($code, $setting);

            $this->model_extension_module_hp_social_login->setGuestCheckout($this->request->post['guest_checkout']);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title2'),
            'href' => $this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        foreach ($inputs as $input) {
            $key = "module_hp_social_login_" . $input['name'];

            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else if ($this->config->get($key)) {
                $data[$key] = $this->config->get($key);
            } else {
                $data[$key] = $input['default'];
            }
        }

        $data['guest_checkout'] = !$this->config->get('config_checkout_guest');

        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/hp_social_login', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/hp_social_login')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function installPage() {
        //Load language
        $this->load->language('extension/module/hp_social_login');

        $this->document->setTitle($this->language->get('error_database'));

        $data['install_database'] = $this->url->link('extension/module/hp_social_login/installTable', 'user_token=' . $this->session->data['user_token'], true);

        $data['text_install_message'] = $this->language->get('text_install_message');

        $data['text_upgrade'] = $this->language->get('text_upgrade');

        $data['error_database'] = $this->language->get('error_database');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false,
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/hpwd_notification', $data));

    }

    public function installTable() {
        $this->cleanDb();
        $sqls[] = "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "customer_verification (
                        customer_id int(11) NOT NULL,
                        code varchar(32) NOT NULL,
                        UNIQUE(`customer_id`)
                    );";
        $sqls[] = "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "customer_verified (
                        customer_id int(11) NOT NULL,
                        code varchar(32) NOT NULL,
                        UNIQUE(`customer_id`)
                    );";
        $sqls[] = "ALTER TABLE `" . DB_PREFIX . "customer` ADD `email_old` VARCHAR(96) NOT NULL AFTER `email` ";

        foreach ($sqls as $sql) {
            $this->db->query($sql);
        }

        $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true));

    }

    public function cleanDb() {
        $sqls[] = "DROP TABLE IF EXISTS " . DB_PREFIX . "customer_verification";
        $sqls[] = "DROP TABLE IF EXISTS " . DB_PREFIX . "customer_verified";

        $chk = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "customer` WHERE `Field` = 'email_old'");

        if ($chk->num_rows) {
            $sqls[] = "ALTER TABLE `" . DB_PREFIX . "customer` DROP `email_old`";
        }

        foreach ($sqls as $sql) {
            $this->db->query($sql);
        }
    }

    public function uninstallTable() {
        $this->cleanDb();
        $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true));

    }

    public function validateTable() {
        $queries[] = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "customer_verification'");
        $queries[] = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "customer_verified'");

        $error = 0;

        foreach ($queries as $query) {
            $error += ($query->num_rows) ? 0 : 1;
        }

        $chk = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . "customer WHERE `Field` = 'email_old'");

        if (!$chk->num_rows) {
            $error += 1;
        }
        return ($error) ? false : true;
    }
}
