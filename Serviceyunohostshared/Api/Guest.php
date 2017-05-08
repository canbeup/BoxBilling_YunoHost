<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Serviceyunohostshared\Api;

class Guest extends \Api_Abstract
{

	

	/*
		Check that username is available and password is sufficient
	*/
	public function check_creation($data)
    {
        $required = array(
            'username' => 'Username is empty',
			//'password' => 'password is empty',
			//'password_retype' => 'password has to be entered twice',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
		
		// Check username format
		if(!preg_match('/^[a-z0-9]{1,10}$/',$data['username'])) {
			throw new \Box_Exception('Your username needs a maximum of 10 lowercase letters and numbers.');
		}

		// Check username availability
        if(!$this->getService()->check_username($data['username'])) {
			throw new \Box_Exception('Username not available.');
		}

		/*// Check that the two passwords are the same
		if($data['password'] != $data['password_retype']) {
			throw new \Box_Exception('Your passwords do not match.');
		}

		// Check that the chosen password is at least 4 characters long
		if(mb_strlen($data['password']) < 4) {
			throw new \Box_Exception('Your password needs to be at least 4 characters long.');
		}*/
		
		return true;
    }

	/*
		Check the stock
	*/
	public function check_stock($data)
    {
		$required = array(
            'group' => 'Group is empty',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);


		$sql = "SELECT SUM(`server`.slots - COALESCE(`service`.used,0)) as free
				FROM `service_yunohostshared_server` as `server`
				LEFT JOIN (
					SELECT COUNT(*) AS used, `service`.server_id
					FROM `service_yunohostshared` as `service`
					LEFT JOIN `client_order` ON `client_order`.service_id=`service`.id AND `client_order`.status = 'active'
				) as `service` ON `service`.server_id=`server`.id
				WHERE `server`.active=1 AND `server`.group='".$data['group']."'";		
        $stock = $this->di['db']->getAll($sql);
		return $stock[0]['free'];
    }
}
