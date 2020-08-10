<?php

class ModelExtensionModulePhoneVerification extends Model
{
    public function getEmailByPhone($phone) {
        $sql = "SELECT `email` FROM " . DB_PREFIX . "customer WHERE telephone= '".$phone."'";
        $query = $this->db->query($sql);

        if (!isset($query->row['email'])) {
            return false;
        }

        return $query->row['email'];
    }
}
