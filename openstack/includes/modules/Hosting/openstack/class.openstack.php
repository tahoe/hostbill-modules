<?php

/**
 * OpenStack module for HostBill
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Modules
 * @package   OpenStack
 * @author    Jasper Aikema <jasper@aikema.nl>
 * @copyright 2014 - JA
 * @license   http://www.gnu.org/licenses/gpl.txt  GPL 3
 * @link      https://github.com/asusk7m550/hostbill-modules/openstack
 */

// Include the php-opencloud class
require __DIR__ . '/vendor/autoload.php';
use OpenCloud;

/**
 * Definition of a OpenStack module class
 *
 * This class uses the php-opencloud v1.10.0
 *
 * @category  Modules
 * @package   OpenStack
 * @author    Jasper Aikema <jasper@aikema.nl>
 * @copyright 2014 - JA
 * @license   http://www.gnu.org/licenses/gpl.txt  GPL 3
 * @link      https://github.com/asusk7m550/hostbill-modules/openstack
 */
class Openstack extends VPSModule
{
    protected $version = '0.1';
    protected $modname = 'OpenStack';
    protected $lang;

    // http://wiki.hostbillapp.com/index.php?title=Hosting_Modules#.24details
    protected $details = array(
        'option1' => array(
            'name' => 'username',
            'value' => false,
            'type' => 'input',
            'default' => false
        ),
        'option2' => array(
            'name' => 'password',
            'value' => false,
            'type' => 'input',
            'default' => false
        ),
        'option3' =>array (
            'name'=> 'domain',
            'value' => false,
            'type'=> 'input',
            'default'=>false
        )
    );

    /**
     * This variable contain description of module which will be visible
     * in HostBill module-manager
     * @var string
     */
    protected $description = 'OpenStack module for HostBill';

    /**
     * Defines options in Settings -> Product & Services -> Product ->
     * Connect with App section
     * @var array
     */
    protected $options = array(
        'option1' => array (
            'name'    => 'flavor',
            'value'   => false,
            'type'    => 'loadable',
            'default' => 'getPlans'
        )
    );
           
    /**
      * You can choose which fields to display in Settings->Apps section
      * by defining this variable
      * @var array
      */
     protected $serverFields = array( // 
        'hostname'     => false,
        'ip'           => false,
        'maxaccounts'  => false,
        'status_url'   => false,
        'field1'       => true,
        'field2'       => true,
        'username'     => true,
        'password'     => true,
        'hash'         => false,
        'ssl'          => false,
        'nameservers'  => false,
    );

     /**
      * HostBill will replace default labels for server fields
      * with this variable configured
      * @var array
      */
    protected $serverFieldsDescription = array( 
        'field1' => 'Authentication URL',
        'field2' => 'Tenant Name'
    );
    
    /**
     * HostBill avaiable commands
     * @var array
     */
    protected $commands = array(
        'Create', 'Suspend', 'Unsuspend', 'Terminate'
    );

    /**
     * This variable specifies what HostBill engine should load while 
     * loading module. Enter only key->value pairs with true in values.
     * @var array
     */
    protected $info = array(
        'haveadmin'    => true,  // is module accessible from adminarea
        'haveuser'     => false, // is module accessible from client area
        'havelang'     => false, // does module support multilanguage
        'havetpl'      => false, // does module have template
        'havecron'     => false, // does module support cron calls
        'haveapi'      => false, // is module accessible via api
        'needauth'     => false, // does module needs authorisation
        'isobserver'   => false, // is module an observer
        'clients_menu' => false, // listing in adminarea->clients menu
        'support_menu' => false, // listing in adminarea->support menu
        'payment_menu' => false, // listing in adminarea->payments menu
        'orders_menu'  => false, // listing in adminarea->orders menu
        'extras_menu'  => false, // listing in extras menu
        'mainpage'     => false, // listing in admin/client home
        'header_js'    => false, // does module have getHeaderJS function
    );

    /* Functions for the OpenStack class used by HostBill */

    /**
     * testConnection
     *
     * Test if a connection can be made by the authenticate function
     *
     * @return int true/false
     *
     * @access public
     * @static
     */
    public function testConnection()
    {
        try {
            $this->authenticate();
            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * connect
     *
     * HostBill will call this method before calling any other function 
     * from your module. It will pass remote app details that module 
     * should connect with.
     *
     * @param array $app_details Server details configured in Settings->Apps
     * 
     * @return int true
     *
     * @access public
     * @static
     */
    public function connect($app_details)
    {
        $this->connection['username']   = $app_details['username'];
        $this->connection['password']   = $app_details['password'];
        $this->connection['authUrl']    = $app_details['field1'];
        $this->connection['tenantName'] = $app_details['field2'];        
        
        return true;
    }

    /**
     * create
     *
     * HostBill calls create() function when account should be provisioned (created).
     * 
     * @return int true/false
     *
     * @access public
     * @static
     */    
    public function create()
    {
        // 1. Instantiate a OpenStack client
        $this->authenticate();

        try {
            // 2. Create Compute service
            $computeService = $this->client->computeService('nova', 'RegionOne');

            // 3. Get empty server
            $server = $computeService->server();

            // 4. Select an OS image
            $images = $computeService->imageList();
            foreach ($images as $image) {
                if (strpos($image->name, 'Ubuntu') !== false) {
                    $ubuntuImage = $image;
                    break;
                }
            }

            // 5. Select a flavor
            $flavor = $computeService->flavor($this->options['option1']['value']);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        // 6. Create
        try {
            $response = $server->create(
                array(
                    'name'     => $this->details['option3']['value'],
                    'image'    => $ubuntuImage,
                    'flavor'   => $flavor
                )
            );
        } catch (Exception $e) {
            $this->addError($e->getMessage());
        }

        try {
            // Retreive the password and ID
            $body = json_decode($response->getBody(true));
            $this->details['option2']['value'] = $body->server->adminPass;
            $this->veid = $body->server->id;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * suspend
     *
     * HostBill calls suspend() function when account should be suspended.
     * 
     * @return int true/false
     *
     * @access public
     * @static
     */
    public function suspend()
    {
        // 1. Instantiate a OpenStack client
        $this->authenticate();

        try {
            // 2. Create Compute service
            $computeService = $this->client->computeService('nova', 'RegionOne');

            // 3. Get a existing server
            $server = $computeService->server($this->veid);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        // 6. Delete
        try {
            $response = $server->suspend();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        try {
            // Retreive the response
            $body = json_decode($response->getBody(true));
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Unsuspend
     *
     * HostBill calls unsuspend() function when account should be suspended.
     * 
     * @return int true
     *
     * @access public
     * @static
     */
    public function unsuspend()
    {
        // 1. Instantiate a OpenStack client
        $this->authenticate();

        try {
            // 2. Create Compute service
            $computeService = $this->client->computeService('nova', 'RegionOne');

            // 3. Get a existing server
            $server = $computeService->server($this->veid);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        // 6. Delete
        try {
            $response = $server->resume();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        try {
            // Retreive the response
            $body = json_decode($response->getBody(true));
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * terminate
     *
     * HostBill calls terminate() function when account should be terminated.
     * 
     * @return int true/false
     *
     * @access public
     * @static
     */
    public function terminate()
    {
        // 1. Instantiate a OpenStack client
        $this->authenticate();

        try {
            // 2. Create Compute service
            $computeService = $this->client->computeService('nova', 'RegionOne');

            // 3. Get a existing server
            $server = $computeService->server($this->veid);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        // 6. Delete
        try {
            $response = $server->delete();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        try {
            // Retreive the response
            $body = json_decode($response->getBody(true));
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * getPlans
     *
     * Get the flavors from OpenStack
     *
     * @return array
     *
     * @access public
     * @static
     */
    public function getPlans()
    {
        $this->authenticate();

        try {
            // 2. Create Compute service
            $computeService = $this->client->computeService('nova', 'RegionOne');

            // 3. Get the flavors
            $flavors = $computeService->flavorList();

            // 4. Put the flavors into a array
            foreach ($flavors as $flavor) {
                $vcpus = $flavor->vcpus;
                $disk = $flavor->disk;
                $ram = $flavor->ram/1024;

                $return[] = array(
                    $flavor->id,
                    "$flavor->name (".$ram." GB / ".$vcpus." CPU / ".$disk. " GB)");
            }

            // 5. Return the array
            return $return;
        } catch (Exception $e) {
            $this->addError('Unable to get plans from the server.');
            return false;
        }
    }
    
    /* End of functions for the OpenStack class used by HostBill */

    /* Functions for the OpenStack class internally used */

    /**
     * initiate
     *
     * Create a connection to OpenStack
     *
     * @return int true/false
     *
     * @access protected
     * @static
     */
    protected function initiate()
    {
        // Initiate a OpenStack client
        try {
            $this->client = new OpenCloud\OpenStack(
                $this->connection['authUrl'], array(
                    'username'   => $this->connection['username'],
                    'password'   => $this->connection['password'],
                    'tenantName' => $this->connection['tenantName']
                )
            );
            return true;
        } catch (Exception $e) {
            $this->addError('Unable to initiate the connection.');
            return false;
        }
    }

    /**
     * Authenticate
     *
     * Authenticate to the OpenStack API
     *
     * @return int true/false
     *
     * @access protected
     * @static
     */
    protected function authenticate()
    {
        // Initiate a OpenStack client
        $this->initiate();

        try {
            // Authenticate to OpenStack
            $this->client->authenticate();
            return true;
        } catch (Exception $e) {
            $this->addError('Unable to authenticate to OpenStack.');
            return false;
        }
    }
    /* End of functions for the OpenStack class internally used */
}
