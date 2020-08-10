<?php

class ModelExtensionModuleHpSocialLogin extends Model {

    public function setGuestCheckout($status)
    {
        $status = (int) $status ? 0 : 1;
        $sql = "UPDATE ". DB_PREFIX ."setting SET value=".$status ." WHERE `key`='config_checkout_guest'";
        $this->db->query($sql);
    }

}

