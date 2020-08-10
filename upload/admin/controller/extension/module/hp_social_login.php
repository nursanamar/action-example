<?php

class ControllerExtensionModuleHpSocialLogin extends Controller {
    private $error = array();
    private $v_d = '';
    private $version = '1.6.5';

    public function index()
    {
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

    public function storeAuth()
    {
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

    protected function rightman()
    {
        if (file_exists(dirname(getcwd()) . '/system/library/cache/hpasl_log')) {
            $this->v_d = $this->VS(dirname(getcwd()) . '/system/library/cache/hpasl_log');
            if ($this->v_d != $_SERVER['SERVER_NAME']) {
                if ($this->internetAccess()) {
                    $data = $this->get_remote_data('https://api.hpwebdesign.id/hpasl.txt');
                    if (strpos($data, $_SERVER['SERVER_NAME']) !== false) {
                        $eligible = $this->VD(dirname(getcwd()) . '/system/library/cache/hpasl_log');
                        $this->hpasl(1, $eligible['date']);
                        $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true));
                    }
                } else {
                    $this->error['warning'] = $this->language->get('error_no_internet_access');
                }
            }
        } else {
            if ($this->internetAccess()) {
                $data = $this->get_remote_data('https://api.hpwebdesign.id/hpasl.txt');
                if (strpos($data, $_SERVER['SERVER_NAME']) !== false) {
                    $this->hpasl(1);
                    $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true));
                }
            } else {
                $this->error['warning'] = $this->language->get('error_no_internet_access');
            }
        }
    }

    protected function hpasl($ref = 0, $date = null)
    {
        $pf = dirname(getcwd()) . '/system/library/cache/hpasl_log';
        if (!file_exists($pf)) {
            fopen($pf, 'w');
        }
        $fh = fopen($pf, 'r');

        if (!fgets($fh) || $ref = 1) {
            $fh = fopen($pf, "wb");
            if (!$fh) {
                chmod($pf, 644);
            }
            fwrite($fh, "// HPWD -> Dilarang mengedit isi file ini untuk tujuan cracking validasi atau tindakan terlarang lainnya" . PHP_EOL);
            $date = $date ? $date : date("d-m-Y", strtotime(date("d-m-Y") . ' + 1 year'));
            fwrite($fh, $date . PHP_EOL);
            fwrite($fh, $_SERVER['SERVER_NAME'] . PHP_EOL);
        }

        fclose($fh);
    }

    private function VD($path)
    {
        $data = array();
        $source = @fopen($path, 'r');
        $i = 0;
        if ($source) {
            while ($line = fgets($source)) {
                $line = trim($line);
                if ($i == 1) {
                    $diff = strtotime(date("d-m-Y")) - strtotime($line);
                    if (floor($diff / (24 * 60 * 60) > 0)) {
                        $data['status'] = 0;
                    } else {
                        $data['status'] = 1;
                    }
                    $data['date'] = $line;
                }
                $i++;
            }
            return $data;
        }
    }

    private function VS($path)
    {
        $source = @fopen($path, 'r');
        $i = 0;
        if ($source) {
            while ($line = fgets($source)) {
                $line = trim($line);
                if ($i == 2) {
                    return $line;
                }
                $i++;
            }
        }
    }

    public function flushdata()
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `code` LIKE '%module_bundle%'");
    }

    public function curlcheck()
    {
        return in_array('curl', get_loaded_extensions()) ? true : false;
    }

    public function get_remote_data($url, $post_paramtrs = false)
    {
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        if ($post_paramtrs) {
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, "var1=bla&" . $post_paramtrs);
        }
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:33.0) Gecko/20100101 Firefox/33.0");
        curl_setopt($c, CURLOPT_COOKIE, 'CookieName1=Value;');
        curl_setopt($c, CURLOPT_MAXREDIRS, 10);
        $follow_allowed = (ini_get('open_basedir') || ini_get('safe_mode')) ? false : true;
        if ($follow_allowed) {
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
        }
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($c, CURLOPT_REFERER, $url);
        curl_setopt($c, CURLOPT_TIMEOUT, 60);
        curl_setopt($c, CURLOPT_AUTOREFERER, true);
        curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');
        $data = curl_exec($c);
        $status = curl_getinfo($c);
        curl_close($c);
        if ($status['http_code'] == 200) {
            return $data;
        } elseif ($status['http_code'] == 301 || $status['http_code'] == 302) {
            if (!$follow_allowed) {
                if (!empty($status['redirect_url'])) {
                    $redirURL = $status['redirect_url'];
                } else {
                    preg_match('/href\=\"(.*?)\"/si', $data, $m);
                    if (!empty($m[1])) {
                        $redirURL = $m[1];
                    }
                }
                if (!empty($redirURL)) {
                    return call_user_func(__FUNCTION__, $redirURL, $post_paramtrs);
                }
            }
        }
        return "ERRORCODE22 with $url!!<br/>Last status codes<b/>:" . json_encode($status) . "<br/><br/>Last data got<br/>:$data";
    }

    private function internetAccess()
    {
//  $connected = @fopen("http://google.com","r");
        //return $connected ? true : false;
        return true;
    }

    public function setting()
    {
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

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/hp_social_login')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function installPage()
    {
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

    public function installTable()
    {
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

    public function cleanDb()
    {
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

    public function uninstallTable()
    {
        $this->cleanDb();
        $this->response->redirect($this->url->link('extension/module/hp_social_login', 'user_token=' . $this->session->data['user_token'], true));

    }

    public function validateTable()
    {
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
        return $error ? false : true;
    }
}
