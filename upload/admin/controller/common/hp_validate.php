<?php
class ControllerCommonHPValidate extends Controller {
    private $v_d;
    
    public function index() {
    
    }
    
    public function storeauth() {        
        $this->load->model('extension/module/system_startup');
        
        $json = array();
        
        $this->language->load('common/hp_validate');

        $this->document->setTitle($this->language->get('text_validation'));

        $data['text_curl']                  = $this->language->get('text_curl');
        $data['text_disabled_curl']         = $this->language->get('text_disabled_curl');

        $data['text_validation']            = $this->language->get('text_validation');
        $data['text_validate_store']        = $this->language->get('text_validate_store');
        $data['text_information_provide']   = $this->language->get('text_information_provide');
        $data['text_validate_store']        = $this->language->get('text_validate_store');
        $data['domain_name']                = $_SERVER['SERVER_NAME'];

        if(isset($this->session->data['hp_ext']) && $this->session->data['hp_ext']) {
            foreach($this->session->data['hp_ext'] as $extension) {
                                              
            if(isset($extension['db_key'])) {
                $domain = $this->rightman($extension['code']);
                $json['data'][] = $this->v_d;
                
                if($this->config->get($extension['group'].'_apitype') == "hpwdapi") {
                    $this->model_extension_module_system_startup->apiusage($extension['group'],$extension['db_key'],$this->v_d['status']);
                
                    if(!$this->v_d['status']) {
                         $json['error']['domain'][]   = sprintf($this->language->get('error_expired_api_usage'),$extension['name']);
                         $json['link'][] = $this->url->link($extension['link'],'user_token=' . $this->session->data['user_token'],true); 
                         $json['button_validate_store']= $this->language->get('button_see_detail');    
                    }
                }     
                
            }  else {
                $domain = $this->rightman($extension['code']);
            }
                                                           
            if($_SERVER['SERVER_NAME'] != $domain) {
                $this->flushdata($extension['group']);   
                $json['error']['domain'][]  = sprintf($this->language->get('error_store_domain'),$extension['name']);
                $json['link'][] = $this->url->link($extension['link'],'user_token=' . $this->session->data['user_token'],true); 
            
                $json['button_validate_store']      = $this->language->get('button_validate_store');                
                } 
            }    
        }
        
    
        $this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
        
    }

    protected function rightman($code) {
        if(file_exists(dirname(getcwd()).'/system/library/cache/'.$code.'_log')) {
            $this->v_d = $this->VD(dirname(getcwd()).'/system/library/cache/'.$code.'_log');  
            
            return $this->v_d['store'];
        }    
    }
    
     private function VD($path) {
        $data = array();
        $source = @fopen($path,'r');    
        $i  = 0;
        if($source) {
        while ($line = fgets($source)) {
            $line = trim($line);
            if($i == 1) {
                $diff = strtotime(date("d-m-Y")) - strtotime($line);
                    if(floor($diff / (24 * 60 * 60 ) > 0)) {
                      $data['status'] = 0; 
                    } else {
                      $data['status'] = 1; 
                    }
                $data['date'] = $line;
                }
            if($i == 2) { 
                $data['store'] = $line;
                }
            $i++;
            }
        return $data;
        }
    }

    public function flushdata($code) {
          $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `code` LIKE '%".$code."%'");
      }
}
