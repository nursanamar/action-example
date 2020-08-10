<?php
class ModelExtensionModuleHpEmailVerif extends Model {

    public function verificationInfo($customer_id)
    {
        $sql = "SELECT ".DB_PREFIX."customer_verification.customer_id,".DB_PREFIX."customer_verified.code FROM ".DB_PREFIX."customer_verification LEFT JOIN ".DB_PREFIX."customer_verified ON ".DB_PREFIX."customer_verification.customer_id=".DB_PREFIX."customer_verified.customer_id WHERE ".DB_PREFIX."customer_verification.customer_id=".$customer_id;
        $query = $this->db->query($sql);

        $result = $query->row;
        if($result){
            return $result;
        }else{
            return false;
        }
    }

}