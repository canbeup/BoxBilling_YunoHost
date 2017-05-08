<?php
namespace Box\Mod\Serviceyunohostshared;
require_once 'ynh_api.class.php';

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function validateCustomForm(array &$data, array $product)
    {
        if ($product['form_id']) {

            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form               = $formbuilderService->getForm($product['form_id']);
            foreach ($form['fields'] as $field) {
                if ($field['required'] == 1) {
                    $field_name = $field['name'];
                    if ((!isset($data[$field_name]) || empty($data[$field_name]))) {
                        throw new \Box_Exception("You must fill in all required fields. " . $field['label'] . " is missing", null, 9684);
                    }
                }

                if ($field['readonly'] == 1) {
                    $field_name = $field['name'];
                    if ($data[$field_name] != $field['default_value']) {
                        throw new \Box_Exception("Field " . $field['label'] . " is read only. You can not change its value", null, 5468);
                    }
                }
            }
        }

    }

	 /**
     * Method to install module. In most cases you will provide your own
     * database table or tables to store extension related data.
     *
     * If your extension is not very complicated then extension_meta
     * database table might be enough.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function install()
    {
        // execute sql script if needed
        $sql = "
        CREATE TABLE IF NOT EXISTS `service_yunohostshared_server` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `group` varchar(255) DEFAULT NULL,
            `ipv4` varchar(255) DEFAULT NULL,
            `ipv6` varchar(255) DEFAULT NULL,
            `hostname` varchar(255) DEFAULT NULL,
			`slots` bigint(20) DEFAULT NULL,
			`root_user` varchar(255) DEFAULT NULL,
            `root_password` varchar(255) DEFAULT NULL,
            `admin_password` varchar(255) DEFAULT NULL,
            `config` text,
			`active` bigint(20) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        $this->di['db']->exec($sql);
		
		$sql = "
        CREATE TABLE IF NOT EXISTS `service_yunohostshared` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
			`client_id` bigint(20) DEFAULT NULL,
			`order_id` bigint(20) DEFAULT NULL,
			`server_id` bigint(20) DEFAULT NULL,
            `username` varchar(255) DEFAULT NULL,
            `mailbox_quota` varchar(255) DEFAULT NULL,
            `config` text,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
			KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        $this->di['db']->exec($sql);

        //throw new \Box_Exception("Throw exception to terminate module installation process with a message", array(), 123);
        return true;
    }

    /**
     * Method to uninstall module.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function uninstall()
    {
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_yunohostshared`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_yunohostshared_server`");
        //throw new \Box_Exception("Throw exception to terminate module uninstallation process with a message", array(), 124);
        return true;
    }
	
    /**
	* Create Order
     * @param \Model_ClientOrder $order
     * @return void
     */
    public function create($order)
    {
		$config = json_decode($order->config, 1);
		
		// Verify that username is free
		if(!$this->check_username($config['username'])) {
			throw new \Box_Exception('Username is not available.');
		}

		// Retrieves password, check that it is 10 characters a-zA-Z0-9 max - TBD
		/*if(mb_strlen($config['password']) < 4) {
			throw new \Box_Exception('Your password needs to be at least 4 characters long.');
		}
		//$password = array('password' => $this->di['crypt']->encrypt($config['password']));*/
		
        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $model                	= $this->di['db']->dispense('service_yunohostshared');
        $model->client_id     	= $order->client_id;
		$model->order_id     	= $order->id;
		$model->username 		= $config['username'];
		$model->mailbox_quota	= '1M';
        $model->created_at    	= date('Y-m-d H:i:s');
        $model->updated_at    	= date('Y-m-d H:i:s');
        $this->di['db']->store($model);

		// Encrypts the password and removes the password retype from the order config - TBD
		/*
		$config['password'] = $this->di['crypt']->encrypt($config['password']);
		$config['password_retype'] = '';
		$order->config		= json_encode($config);	// Stores the encrypted password and empty password retype
		$order->updated_at    	= date('Y-m-d H:i:s');
		$this->di['db']->store($order);*/

        return $model;
    }

    /**
	 * Activate Order
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function activate($order, $model)
    {
        if (!is_object($model)) {
            throw new \Exception('Could not activate order. Service was not created');
        }
		$config = json_decode($order->config, 1);
        $client  = $this->di['db']->load('client', $order->client_id);
        $product = $this->di['db']->load('product', $order->product_id);
		$service = $this->di['db']->dispense('service_yunohostshared');
        if (!$product) {
            throw new \Exception('Could not activate order because ordered product does not exists');
        }
		
		// Allocate to an appropriate server id
		$serverid = $this->find_empty($product);
		
		// Retrieve server info
		$server = $this->di['db']->load('service_yunohostshared_server', $serverid);
		
		// Connect to YNH API
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		
		// Create YNH user 
		$password = bin2hex(openssl_random_pseudo_bytes(4));
		//$password = json_decode($order->config, 1)['password'];

		if ($ynh->login()) {
			$arguments = array(
				'username' => $model->username,
				//'password' => $this->di['crypt']->decrypt($config['password']),
				'password' => $password,
				'firstname' => $client->first_name,
				'lastname' => $client->last_name,
				'mail' => $model->username.'@'.$server->hostname,
				'mailbox_quota' => $model->mailbox_quota
			);
			$user_add = $ynh->post("/users", $arguments);
			if (!$user_add && !is_bool($user_add)) {
				throw new \Exception('Could not activate order', null, 7457);
			}
		}
		else {
			throw new \Exception('Could not activate order because login to YunoHost failed', null, 7457);
		}

		$model->server_id 		= $serverid;
		$model->updated_at    	= date('Y-m-d H:i:s');
		$this->di['db']->store($model);

		// Deletes the encrypted password - TBD
		/*$config['password'] = '';
		$config['password_retype'] = '';
		$order->config		= json_encode($config);	// Stores the encrypted password and empty password retype
		$order->updated_at    	= date('Y-m-d H:i:s');
		$this->di['db']->store($order);		*/

		//$orderService->updateOrder($order, array('id' => $order->id, 'config' => array('password' => '', 'password_retype' => '')));

		return array(
			'hostname'		=> 	'https://'.$server->hostname,
            'username'  	=>  $model->username,
			'password'  	=>  $password
        );
    }

    /**
     * Suspend YunoHost account - put a random user password
     * @param $order
     * @return boolean
     */
    public function suspend($order, $model)
    {
		$data['new_password'] = $this->di['tools']->generatePassword(10, 4);
		$this->change_user_password($order, $model, $data);
		
		// change mailbox password
		
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
	 * Unsuspend YunoHost account - do nothing (the user has to reset the password)
     * @param $order
     * @return boolean
     */
    public function unsuspend($order, $model)
    {
        // Activate, ask to change back?
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
	 * Cancel YunoHost account = suspend
     * @param $order
     * @return boolean
     */
    public function cancel($order, $model)
    {
        return $this->suspend($order, $model);
    }

    /**
	 * Uncancel YunoHost account = unsuspend
     * @param $order
     * @return boolean
     */
    public function uncancel($order, $model)
    {
        return $this->unsuspend($order, $model);
    }

    /**
	 * Delete YunoHost account
     * @param $order
     * @return boolean
     */
    public function delete($order, $model)
    {
        if (is_object($model)) {
			
			// Retrieve associated server
			$server = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$model->server_id));
            
			if(!empty($server)){
				// Connect to YNH API
				$serveraccess = $this->find_access($server);
				$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
			
				// Remove YNH user
				if ($ynh->login()) {
					if($ynh->delete("/users/".$model->username."?purge")) {
						// Trash order
						$this->di['db']->trash($model);
					}
					else {
						throw new \Exception('Could not delete user');
					}
				}
				else {
					throw new \Exception('Could not delete user '.$model->username.' because login to YunoHost failed');
				}
			}
			else {
				$this->di['db']->trash($model);
			}
		}
    }
	
    public function getConfig($model)
    {
        return $this->di['tools']->decodeJ($model->config);
    }

    public function toApiArray($model)
    {
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$model->server_id));
			
        return array(
            'id'              => $model->id,
            'client_id'       => $model->client_id,
            'server_id'       => $model->server_id,
            'username'        => $model->username,
            'mailbox_quota'   => $model->mailbox_quota,
			'server' 		  => $server,
        );
    }
	
	/*
		Change user password with $data['new-password']
	*/
	public function change_user_password($order, $model, $data)
	{
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$model->server_id));
		
		// Connect to YNH API
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		
        // Change YNH password
		if ($ynh->login()) {
			$arguments['change_password'] = $data['new_password'];
			if($ynh->put("/users/".$model->username, $arguments)) {
				$model->updated_at = date('Y-m-d H:i:s');
				$this->di['db']->store($model);
				return true;
			}
			else {
				throw new \Exception('Could not change user\'s password', null, 7457);
			}
		}
		else {
			throw new \Exception('Could not change user\'s password because login to YunoHost failed', null, 7457);
		}

	}
	
	/*
		Change admin password with $data['new-password']
	*/
	public function change_admin_password($server, $data)
	{
		
		// Connect to YNH API
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		
        // Change YNH admin password
		if ($ynh->login()) {
			$arguments['new_password'] = $data['new_password'];
			if($ynh->put("/adminpw", $arguments)) {
			
				$server->admin_password = $data['new_password'];
				$server->updated_at = date('Y-m-d H:i:s');
				$this->di['db']->store($server);
				return true;
			}
			else {
				throw new \Exception('Could not change admin password');
			}
		}
		else {
			throw new \Exception('Could not change admin\s password because login to YunoHost failed', null, 7457);
		}
	}
	
	/*
		Check that username is unique among all orders
	*/
	public function check_username($username)
	{
        $check_username = $this->di['db']->findOne('service_yunohostshared', 'username = :username ', array(':username' => $username));
		if (empty($check_username)) {return true;}
		else {return false;}
	}
	
	/*
		Test connection
	*/
	public function test_connection($server)
	{
        // Test if login
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		if ($ynh->login()) {
			return true;
		}
		else {
			return false;
		}
	}
	
	/*
		Server statistics
	*/
	public function server_stats($server)
	{
        // Test if login
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		if ($ynh->login()) {
			$system = $ynh->get("/monitor/system");
			$disk = $ynh->get("/monitor/disk");
			$network = $ynh->get("/monitor/network");
			
			$stats = array(
				'uptime'	=> $system['uptime'],
				'cpu15'	    => $system['cpu']['load']['min15'],
				'memory'	=> $system['memory']['ram']['percent'],
				'disk_used'	    => round(array_values($disk)[0]['filesystem']['used']/1000000000, 1),
				'disk_total'	=> round(array_values($disk)[0]['filesystem']['size']/1000000000, 1),
			);
			
			return $stats;
		}
		else {
			return false;
		}
	}
	
	/*
		List of apps installed
	*/
	public function apps_installed($server)
	{
        // Test if login
		$serveraccess = $this->find_access($server);
		$ynh = new YNH_API($serveraccess, $this->di['crypt']->decrypt($server->admin_password));
		if ($ynh->login()) {
			$apps = $ynh->get("/appsmap");
			return $apps;
		}
		else {
			return false;
		}
	}
	
	/*
		Find empty slots
	*/
	public function find_empty($product)
    {
		$config = json_decode($product->config, 1);
		$group = $config['group'];
		$filling = $config['filling'];
		
		// Retrieve list of active server from this group
		// Retrieve the number of slots used per server
		if ($filling == 'least') {$condition = "ORDER BY ratio ASC";}
		else if ($filling == 'full') {$condition = "ORDER BY ratio DESC";}
		else {$condition = "";}
		
		// Retrieve only non-full active servers sorted by filling ratio (DESC for filling the least filled, ASC for filling servers up) - COALESC transforms null cells into zeros for calculations.
		$sql = "SELECT `server`.id, `server`.group, `server`.active, `server`.slots, COALESCE(`service`.used,0) as used, `server`.slots - COALESCE(`service`.used,0) as free, COALESCE(`service`.used,0) / `server`.slots as ratio
				FROM `service_yunohostshared_server` as `server`
				LEFT JOIN (
					SELECT COUNT(*) AS used, `service`.server_id
					FROM `service_yunohostshared` as `service`
					LEFT JOIN `client_order` ON `client_order`.service_id=`service`.id AND `client_order`.status = 'active'
				) as `service` ON `service`.server_id=`server`.id
				WHERE `server`.slots <> COALESCE(`service`.used,0) AND `server`.active=1 AND `server`.group='".$group."'
				".$condition." LIMIT 1";
				
        $appropriate_server = $this->di['db']->getAll($sql);
		if (!empty($appropriate_server[0]['id'])) {
			return $appropriate_server[0]['id'];
		}
		else {
			throw new \Exception('No server found');
			return false;
		}
	}
	
	/*
		Find access to server (hostname, ipv4, ipv6)
	*/
	public function find_access($server)
    {
		if (!empty($server->ipv6)) {return $server->ipv6;}
		else if (!empty($server->ipv4)) {return $server->ipv4;}
		else if (!empty($server->hostname)) {return $server->hostname;}
		else {
			throw new \Exception('No IPv&, IPv4 or Hostname found for server '.$server->id);
		}
	}

	/*
		Show stock
	*/
	public function get_stock($server)
    {
		$sql = "SELECT SUM(`server`.slots - COALESCE(`service`.used,0)) as free
				FROM `service_yunohostshared_server` as `server`
				LEFT JOIN (
					SELECT COUNT(*) AS used, `service`.server_id
					FROM `service_yunohostshared` as `service`
					LEFT JOIN `client_order` ON `client_order`.service_id=`service`.id AND `client_order`.status = 'active'
				) as `service` ON `service`.server_id=`server`.id
				WHERE `server`.active=1 AND `server`.group='".$group."'
				".$condition." LIMIT 1";
				
        $appropriate_server = $this->di['db']->getAll($sql);
	}
	
/*

    public function updateConfig($orderId, $config)
    {
        if (!is_array($config)){
            throw new \Box_Exception('Config must be an array');
        }

        $model             = $this->getServiceYunohostSharedByOrderId($orderId);
        $model->config     = json_encode($config);
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Custom service updated #%s', $model->id);
    }

    public function getServiceYunohostSharedByOrderId($orderId)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if(!$s instanceof \Model_ServiceYunohostShared) {
            throw new \Box_Exception('Order is not activated');
        }
        return $s;
    }

    private function _getOrderService(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model        = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \Box_Exception('Order :id has no active service', array(':id' => $order->id));
        }

        return $model;
    }*/
}
