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


namespace Box\Mod\Serviceyunohostshared\Controller;

class Admin implements \Box\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return array(
            'subpages'  =>  array(
                array(
                    'location'  => 'system',
                    'index'     => 140,
                    'label' => 'YunoHost Shared servers',
                    'uri'   => $this->di['url']->adminLink('serviceyunohostshared'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/serviceyunohostshared',          'get_index', null, get_class($this));
        $app->get('/serviceyunohostshared/server/:id',     'get_server', array('id'=>'[0-9]+'), get_class($this));
		$app->get('/serviceyunohostshared/server-stats/:id',     'get_server_stats', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_serviceyunohostshared_index');
    }

    public function get_server(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $server = $api->serviceyunohostshared_server_get(array('server_id'=>$id));
        return $app->render('mod_serviceyunohostshared_server', array('server'=>$server));
    }
	public function get_server_stats(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $server = $api->serviceyunohostshared_server_get_stats(array('server_id'=>$id));
        return $app->render('mod_serviceyunohostshared_server_stats', array('server'=>$server));
    }
}