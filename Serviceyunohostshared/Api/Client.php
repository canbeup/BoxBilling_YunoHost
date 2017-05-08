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
 * Custom product management
 */
class Client extends \Api_Abstract
{
    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin
     *
     * @param int $order_id - ID of the order
     *
     * @throws Box_Exception
     */
    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \Box_Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];

        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $model = $this->getService()->getServiceYunohostSharedByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }
	
	 /**
     * Change user password
     * @param int $order_id - order id
     * @param string $password - new password
     * @return bool 
     */
    public function change_password($data)
    {
		$required = array(
            'new_password'    => 'New password is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

		// Retrieve order
		if(!isset($data['new_password'])) {
            throw new \Exception('New password is required');
        }
		$service = $this->di['db']->findOne('service_yunohostshared',"order_id=:id", array(':id'=>$data['order_id']));
		$order = $this->di['db']->findOne('client_order',"id=:id", array(':id'=>$data['order_id']));
        if(!$service) {
            throw new \Exception('YunoHost shared order not found');
        }
		
		// Change password
		$this->getService()->change_user_password($order, $service, $data);
        $this->di['logger']->info('Changed YunoHost user password. Order ID #%s', $data['order_id']);
        return true;
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
            'order_id'    => 'Server id is missing',
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
            throw new \Exception('YunoHost shared order not found');
        }
		
		// Retrieve associated server
		$server  = $this->di['db']->findOne('service_yunohostshared_server','id=:id',array(':id'=>$service['server_id']));

		$output = array(
			'hostname' 		=> $server->hostname,
			'username' 		=> $service['username'],
			'apps'			=> json_decode($server->config, 1)['apps']
		);
        return $output;
    }
}
