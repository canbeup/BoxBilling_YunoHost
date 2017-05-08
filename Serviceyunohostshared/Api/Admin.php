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

/**
 * Hosting service management 
 */
class Admin extends \Api_Abstract
{  
    /**
     * Get list of servers
     * 
     * @return array
     */
    public function server_get_list($data)
    {
		$servers = $this->di['db']->find('service_yunohostshared_server');
		//echo '<pre>';print_r($servers);echo '</pre>';
		$servers_grouped = array();
        foreach ($servers as $server) {
			// Try to retrieve YNH server details if the server is active
				// Connect to YNH API
			/*	if ($server->active == 0) {
					$stats = '';
				}
				else {
					if (empty($server->ipv6) AND empty($server->ipv4) AND empty($server->hostname)) {
						$stats = '';
					}
					else {
						$stats = $this->getService()->server_stats($server);
					}
				}*/
		
			$servers_grouped[$server['group']]['group'] = $server->group;
			$servers_grouped[$server['group']]['servers'][$server['id']] = array(
				'id'			=> $server->id,
				'name' 			=> $server->name,
				'group' 		=> $server->group,
				'ipv4' 			=> $server->ipv4,
				'ipv6' 			=> $server->ipv6,
				'hostname' 		=> $server->hostname,
				'used_slots'	=> $server->slots - $this->used_slots($server->id),
				'slots' 		=> $server->slots,
				//'root_user' 	=> $server->root_user,
				//'root_password' => $server->root_password,
				//'admin_password'=> $server->admin_password,
				'active'		=> $server->active,
				//'stats'		    => $stats,
			);
        }
        return $servers_grouped;
    }

	/* Get server details from order id */
	public function server_get_from_order($data)
    {
        $required = array(
            'order_id'    => 'Order id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve order
		if(!isset($data['order_id'])) {
            throw new \Exception('Order id is required');
        }
        $service = $this->di['db']->findOne('service_yunohostshared',
                "order_id=:id", 
                array(':id'=>$data['order_id']));
        if(!$service) {
            //throw new \Exception('YunoHost shared order not found');
			$data = array ('server_id' => $service['server_id']);
			
			return null;
        }

		$data = array ('server_id' => $service['server_id']);
		if(empty($data['server_id'])) {$output='';}
		else {$output = $this->server_get($data);}
		
        return $output;
    }
	
    /**
     * Get server details
     * 
     * @param int $id - server id
     * @return array
     * 
     * @throws \Box_Exception 
     */
    public function server_get($data)
    {
        $required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));

		$output = array(
			'id'			=> $server->id,
			'name' 			=> $server->name,
			'group' 		=> $server->group,
			'ipv4' 			=> $server->ipv4,
			'ipv6' 			=> $server->ipv6,
			'hostname' 		=> $server->hostname,
			'used_slots'	=> $server->slots - $this->used_slots($server->id),
			'slots' 		=> $server->slots,
			'root_user' 	=> $server->root_user,
			'root_password' => $this->di['crypt']->decrypt($server->root_password),
			'admin_password'=> $this->di['crypt']->decrypt($server->admin_password),
			'active'		=> $server->active,
			'config'		=> $server->config,
			'apps'			=> json_decode($server->config, 1)['apps']
		);
        return $output;
    }
	
	public function server_get_stats($data)
    {
        $required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));

		if (empty($server->ipv6) AND empty($server->ipv4) AND empty($server->hostname)) {
			$stats = '';
		}
		else {
			$stats = $this->getService()->server_stats($server);
		}
		
		$output = array(
			'id'			=> $server->id,
			'name' 			=> $server->name,
			'group' 		=> $server->group,
			'ipv4' 			=> $server->ipv4,
			'ipv6' 			=> $server->ipv6,
			'hostname' 		=> $server->hostname,
			'used_slots'	=> $server->slots - $this->used_slots($server->id),
			'slots' 		=> $server->slots,
			'root_user' 	=> $server->root_user,
			'root_password' => $this->di['crypt']->decrypt($server->root_password),
			'admin_password'=> $this->di['crypt']->decrypt($server->admin_password),
			'active'		=> $server->active,
			'stats'		    => $stats,
		);
        return $output;
    }

	    
    /**
     * Change hosting account password.
     * 
     * @param int $order_id - Hosting account order id
     * @param string $password - New account password
     * @param string $password_confirm - Must be same value as password field
     * 
     * @return boolean 
     */
    public function change_password($data)
    {
		$required = array(
            'server_id'    	=> 'Server id is missing',
			'password'	=> 'New password is missing',
			'password_confirm'	=> 'New password confirmation is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));
		if(!$server) {
            throw new \Exception('YunoHost shared server not found');
        }
		
		if($data['password'] != $data['password_confirm']) {
            throw new \Exception('Passwords do not match');
        }
		
		// Change YunoHost admin password
		$change_password = array(
			'server_id'    	=> $data['server_id'],
			'new_password'  => $data['password'],
		);
		$this->getService()->change_admin_password($server, $data);

		$server->admin_password	= $this->di['crypt']->encrypt($data['admin_password']);
        $server->updated_at    	= date('Y-m-d H:i:s');
        $this->di['db']->store($server);

        $this->di['logger']->info('Changed YunoHost admin password. Server ID #%s', $server->id);
        return true;
		
        /*list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->changeAccountPassword($order, $s, $data);*/
		
		// Change admin password
    }
    
    /**
     * Synchronize account with server values.
     * 
     * @param int $order_id - Hosting account order id
     * 
     * @return boolean 
     */
    public function sync($data)
    {
        /*list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->sync($order, $s);*/
		
		// Sync monitoring data
    }
    
    /*
		Update product informations
	*/
    public function product_update($data)
    {
        $required = array(
			'id'			=> 'Product id is missing',
            'group'    		=> 'Server group is missing',
            'filling'   	=> 'Filling method is missing',
			'show_stock'    => 'Stock display is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated product
		$product  = $this->di['db']->findOne('product','id=:id',array(':id'=>$data['id']));
		
		$config = array(
			'group'			=> $data['group'],
			'filling'		=> $data['filling'],
			'show_stock'	=> $data['show_stock']
		);
		
		$product->config     	= json_encode($config);
        $product->updated_at    = date('Y-m-d H:i:s');
        $this->di['db']->store($product);
		
		$this->di['logger']->info('Update YunoHost Shared product %s', $product->id);
        return true;
    }
	
	/**
     * Create new hosting server 
     * 
     * @param string $name - server name
     * @param string $ip - server ip
     * @optional string $hostname - server hostname
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional bool $active - flag to enable/disable server
     * 
     * @return int - server id 
     * @throws \Box_Exception 
     */
    public function server_create($data)
    {
        $required = array(
            'name'    => 'Server name is missing',
            'slots'      => 'Slots are missing',
			'root_user'      => 'Root user is missing',
			'root_password'      => 'Root password is missing',
			'admin_password'      => 'Admin password is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		$server 				= $this->di['db']->dispense('service_yunohostshared_server');
        $server->name     		= $data['name'];
		$server->group     		= $data['group'];
		$server->ipv4     		= $data['ipv4'];
		$server->ipv6     		= $data['ipv6'];
		$server->hostname     	= $data['hostname'];
		$server->slots     		= $data['slots'];
		$server->root_user     	= $data['root_user'];
		$server->root_password	= $this->di['crypt']->encrypt($data['root_password']);
		$server->admin_password	= $this->di['crypt']->encrypt($data['admin_password']);
		$server->config     	= $data['config'];
		$server->active     	= $data['active'];
        $server->created_at    	= date('Y-m-d H:i:s');
        $server->updated_at    	= date('Y-m-d H:i:s');
        $this->di['db']->store($server);
		
		$this->di['logger']->info('Create YunoHost Shared server %s', $server->id);
		
		return true;
    }
	
    /**
     * Delete server
     * 
     * @param int $id - server id
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_delete($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $server = $this->di['db']->getExistingModelById('service_yunohostshared_server', $data['id'], 'Server not found');
		$this->di['db']->trash($server);
    }

    /**
     * Update server configuration
     * 
     * @param int $id - server id
     * 
     * @optional string $hostname - server hostname
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional bool $active - flag to enable/disable server
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_update($data)
    {
		$required = array(
            'name'    => 'Server name is missing',
            'slots'      => 'Slots are missing',
			'root_user'      => 'Root user is missing',
			'root_password'      => 'Root password is missing',
			'admin_password'      => 'Admin password is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));
		
        $server->name     		= $data['name'];
		$server->group     		= $data['group'];
		$server->ipv4     		= $data['ipv4'];
		$server->ipv6     		= $data['ipv6'];
		$server->hostname     	= $data['hostname'];
		$server->slots     		= $data['slots'];
		$server->root_user     	= $data['root_user'];
		$server->root_password	= $this->di['crypt']->encrypt($data['root_password']);
		$server->admin_password	= $this->di['crypt']->encrypt($data['admin_password']);
		$server->config     	= $data['config'];
		$server->active     	= $data['active'];
        $server->created_at    	= date('Y-m-d H:i:s');
        $server->updated_at    	= date('Y-m-d H:i:s');
        $this->di['db']->store($server);
		
		$this->di['logger']->info('Update YunoHost Shared server %s', $server->id);
        return true;
	
        /*$required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));
		
		// Update server
		$service = $this->getService();
		return (bool) $service->updateServer($model, $data);*/
    }

    /**
     * Test connection to server
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_test_connection($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_yunohostshared_server', $data['id'], 'Server not found');
        return (bool) $this->getService()->test_connection($server);
    }
	
	/**
     * Refresh the list of installed apps on a server
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_refresh_apps($data)
    {
        $required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_yunohostshared_server', $data['server_id'], 'Server not found');
		$apps_installed = $this->getService()->apps_installed($server);
		$config = json_decode($server->config, 1);
		$config['apps'] = $apps_installed;
		
		$server->config    	= json_encode($config);
        $server->updated_at    	= date('Y-m-d H:i:s');
        $this->di['db']->store($server);
		return true;
    }
	
	/*
		Get list of server groups
     */
    public function server_groups()
    {
        $server = $this->di['db']->dispense('service_yunohostshared_server');
		$sql = "SELECT DISTINCT `group` FROM `service_yunohostshared_server` WHERE `active` = 1";
        $groups = $this->di['db']->getAll($sql);
		return $groups;
    }
	
	/*
		Retrieve empty slots for each server
	*/
	public function used_slots($server_id)
	{
		$sql = "SELECT `service_yunohostshared`.server_id, COUNT(*) AS used
				FROM `client_order` 
				INNER JOIN `service_yunohostshared` ON `client_order`.service_id=`service_yunohostshared`.id 
				WHERE `client_order`.service_type = 'yunohostshared' AND `client_order`.status = 'active' AND `service_yunohostshared`.server_id = ".$server_id."
				GROUP BY `service_yunohostshared`.server_id";
        $active_orders = $this->di['db']->getAll($sql);
		if(empty($active_orders[0]['used'])) {$active_orders[0]['used']=0;}
		return $active_orders[0]['used'];
	}
	
	/*
		Allocate a server to an order
     */
    public function allocate_server($data)
    {
		$required = array(
            'server_id'    => 'Server is missing',
			'order_id'    => 'Order is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated order
		$service = $this->di['db']->findOne('service_yunohostshared',
                "order_id=:id", 
                array(':id'=>$data['order_id']));
		
		$service->server_id 	= $data['server_id'];
		$service->updated_at    	= date('Y-m-d H:i:s');
		$this->di['db']->store($service);
		
		$this->di['logger']->info('Allocated server %s', $service->id);
        return true;
	
        /*$required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));
		
		// Update server
		$service = $this->getService();
		return (bool) $service->updateServer($model, $data);*/
    }

	public function force_delete($data)
    {
		$required = array(
			'order_id'    => 'Order is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated order
		$model = $this->di['db']->findOne('service_yunohostshared',
                "order_id=:id", 
                array(':id'=>$data['order_id']));
		
		// Trash order
		$this->di['db']->trash($model);

		return true;
	
        /*$required = array(
            'server_id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$data['server_id']));
		
		// Update server
		$service = $this->getService();
		return (bool) $service->updateServer($model, $data);*/
    }

    public function _getService($data)
    {
        /*$required = array(
            'order_id'    => 'Order ID name is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderSerivce = $this->di['mod_service']('order');
        $s = $orderSerivce->getOrderService($order);
        if(!$s instanceof \Model_ServiceYunohostShared) {
            throw new \Box_Exception('Order is not activated');
        }
        return array($order, $s);*/
    }
}
