<?php
class ModelExtensionModuleSystemStartup extends Model {
    
    public function apiusage($app_key, $db_key, $val) {
        $this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '".$val."' WHERE `key` = '".$app_key."_api_usage'");
        
        if ($val) {
            $this->db->query("UPDATE " . DB_PREFIX . "setting SET `code` = 'hpwd', `value` = SUBSTRING(`value`,1,32) WHERE `key` = '".$db_key."'");
        } else {
            $this->db->query("UPDATE " . DB_PREFIX . "setting SET `code` = 'hpwd', `value` = CONCAT(`value`,'".mt_rand(2, 999)."') WHERE `key` = '".$db_key."'");            
        }      
    }
}
