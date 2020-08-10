<?php
class ControllerExtensionModuleEmailVerification extends Controller
{

    public function index() {
        if ($this->customer->isLogged()) {
            $this->loggingOut();
        }

        if (isset($this->request->get['v']) && strlen($this->request->get['v']) != 32) {
            $this->response->redirect($this->url->link('common/home'));
        }

        $this->load->language('extension/module/email_verification');

        $result = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customer_verification WHERE code = '" . $this->db->escape($this->request->get['v']) . "'");

        // clean up
        unset($this->session->data['error']);
        unset($this->session->data['success']);

        if (!$result->num_rows) {
            // check if successfully verified before

            $verify_info = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customer_verified WHERE code = '" . $this->db->escape($this->request->get['v']) . "'");

            if ($verify_info->num_rows) {
                $this->session->data['success'] = $this->language->get('success_verified_before');
            }

            $data['text_message'] = $this->language->get('success_verified_before');

        } else {
            // enable current customer
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET status = '1' WHERE customer_id = '" . (int)$result->row['customer_id'] . "'");

            $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_approval` WHERE customer_id = '" . (int)$result->row['customer_id'] . "' AND `type` = 'customer'");

            $this->db->query("DELETE FROM " . DB_PREFIX . "customer_verification WHERE customer_id = '" . (int)$result->row['customer_id'] . "'");

            // mark as verified customer
            $this->db->query("INSERT INTO " . DB_PREFIX . "customer_verified SET customer_id = '" . (int)$result->row['customer_id'] . "', code='" . $this->request->get['v'] . "'");

            $customer = $this->db->query("SELECT email FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$result->row['customer_id'] . "'");

            $this->session->data['success'] = $this->language->get('success_verified');
            $data['text_message'] = $this->language->get('success_verified');

        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
        );

        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        
        $data['continue'] = $this->url->link('common/home');
        
        $this->response->setOutput($this->load->view('common/success', $data));

    }

    public function resend() {
        if ($this->customer->isLogged() || !isset($this->request->get['email'])) {
            $this->response->redirect($this->url->link('account/login'));
        }

        $this->load->language('mail/register');
        $this->load->language('extension/module/email_verification');

        
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomerByEmail($this->request->get['email']);

        if ($customer_info) {
            list($usec, $sec) = explode(' ', microtime());
            srand((float)$sec + ((float)$usec * 100000));
            $code = md5((int)$customer_info['customer_id'] . ':' . rand());

            $this->db->query("DELETE FROM " . DB_PREFIX . "customer_verification WHERE customer_id = '" . (int)$customer_info['customer_id'] . "'");

            $this->db->query("INSERT INTO " . DB_PREFIX . "customer_verification SET customer_id = '" . (int)$customer_info['customer_id'] . "', code = '" . $code . "'");

            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
            $defaultLanguage = $this->config->get('config_language');
            $language_id = $languages[$defaultLanguage]['language_id'];

            $template = $this->config->get('module_hp_social_login_email_message_text_' . $language_id);

            

            $find = array(
                "{firstname}",
                "{lastname}",
                "{email-link}",
            );

            $replace = array(
                "firstname" => $customer_info['firstname'],
                "lastname" => $customer_info['lastname'],
                "email-link" => $this->url->link('extension/module/email_verification', '', true) . '&v=' . $code,
            );

            $msg = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $template))));
            $data['content'] = html_entity_decode($msg);
            $emailContent = $this->load->view('mail/register_1', $data);


            $data['store'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

            $mail = new Mail($this->config->get('config_mail_engine'));
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($customer_info['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')));
            $mail->setHtml($emailContent);
            $mail->send();

            
            $this->session->data['success'] = $this->language->get('text_resent_verified');
        }
        $data = [];

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
        );

        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        
        $data['text_message'] = $this->language->get('text_resent_verified');
        $data['continue'] = $this->url->link('common/home');

        $this->response->setOutput($this->load->view('common/success', $data));
    }

    private function loggingOut() {
        $this->customer->logout();

        unset($this->session->data['shipping_address']);
        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_address']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['comment']);
        unset($this->session->data['order_id']);
        unset($this->session->data['coupon']);
        unset($this->session->data['reward']);
        unset($this->session->data['voucher']);
        unset($this->session->data['vouchers']);
    }
}
