<?php

namespace YiiNodeSocket;

use Yii;

require_once 'frames/IFrameFactory.php';
require_once 'frames/FrameFactory.php';

class NodeSocket extends yii\base\Component {

    /**
     * Node js server host to bind http and socket server
     * Valid values is:
     *   - valid ip address
     *   - domain name
     *
     * Domain name must be withoud http or https
     * Example:
     *
     * 'host' => 'test.com'
     * // or
     * 'host' => '84.25.159.52'
     *
     * @var string
     */
    public $host = '0.0.0.0';

    /**
     * If your session var name is SID or other change this value to it
     *
     * @var string
     */
    public $sessionVarName = 'PHPSESSID';

    /**
     * @var int by default is once month
     */
    public $cookieLifeTime = 2592000;

    /**
     * Port in integer type only
     *
     * @var int
     */
    public $port = 3001;

    /**
     * Can be string, every domain|ip separated by a comma
     * or array
     *
     * @var string|array
     */
    public $origin;

    /**
     * List of allowed servers
     *
     * Who can send server frames
     *
     * If is string, ip addresses should be separated by a comma
     *
     * @var string|array
     */
    public $allowedServerAddresses;

    /**
     * Default is runtime/socket-transport.server.log
     *
     * @var string
     */
    public $socketLogFile;

    /**
     * If set to false, any client can connect to websocket server
     *
     * @var bool
     */
    public $checkClientOrigin = true;

    /**
     * @var string
     */
    public $pidFile = 'socket-transport.pid';

    /**
     * @var int timeout for handshaking in miliseconds
     */
    public $handshakeTimeout = 2000;

    /**
     * @var array
     */
    public $dbConfiguration = array('driver' => 'dummy');

    /**
     * @var string
     */
    protected $_assetUrl;

    /**
     * @var \YiiNodeSocket\Frames\FrameFactory
     */
    protected $_frameFactory;

    /**
     * @var \YiiNodeSocket\Components\Db
     */
    protected $_db;

    /**
     * @var \ElephantIO\Client
     */
    protected $_client;

    public function init() {
        parent::init();

//		spl_autoload_unregister(array('YiiBase','autoload'));
        require_once 'Autoload.php';
        \YiiNodeSocket\Autoload::register(__DIR__);
//		spl_autoload_register(array('YiiBase','autoload'));
        if (function_exists('__autoload')) {
            // Be polite and ensure that userland autoload gets retained
            spl_autoload_register('__autoload');
        }
        $this->_frameFactory = new \YiiNodeSocket\Frames\FrameFactory($this);
        $this->_db = new \YiiNodeSocket\Components\Db($this);
        foreach ($this->dbConfiguration as $k => $v) {
            $this->_db->$k = $v;
        }
    }

    /**
     * @return \YiiNodeSocket\Frames\FrameFactory
     */
    public function getFrameFactory() {
        return $this->_frameFactory;
    }

    /**
     * @return \YiiNodeSocket\Components\Db
     */
    public function getDb() {
        return $this->_db;
    }

    /**
     * @return \YiiNodeSocket\Components\Db
     */
    public function getClient() {
        return $this->_client;
    }

    /**
     * @return bool
     */
    public function registerClientScripts() {
        if ($this->_assetUrl) {
            return true;
        }
        $this->_assetUrl = \Yii::$app->assetManager->publish('@nodeWeb');
        if ($this->_assetUrl) {
            \Yii::$app->getView()->registerJsFile(sprintf("http://%s:%d%s", $this->host, $this->port, '/socket.io/socket.io.js'));
            \Yii::$app->getView()->registerJsFile(array_pop($this->_assetUrl) . '/client.js');
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function registerAssets($view) {
        if ($this->_assetUrl) {
            return true;
        }
        $socketIO = \sprintf("http://%s:%d%p", $this->host, $this->port, '/socket.io/socket.io.js'
        );

        $view->getAssetManager()->bundles['NodeAssets'] = new \YiiNodeSocket\Assets\NodeAssets();
        $view->getAssetManager()->bundles['NodeAssets']->js[] = $socketIO;
        $view->getAssetManager()->bundles['NodeAssets']->publish($view->getAssetManager());
        $nodeClientAssets = $view->registerAssetBundle('NodeAssets');
        $this->_assetUrl = $nodeClientAssets->sourcePath;
        if ($this->_assetUrl) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getOrigin() {
        // $origin = $this->host . ':*';

        $origin = '';
        if ($this->origin) {
            $o = array();
            if (is_string($this->origin)) {
                $o = explode(',', $this->origin);
            }
            $o = array_map('trim', $o);
            if (in_array($origin, $o)) {
                unset($o[array_search($origin, $o)]);
            }
            if (!empty($o)) {
                $origin .= ' ' . implode(' ', $o);
            }
        }
        if (!$origin) {
            $origin = $this->host . ':*';
        }
        return $origin;
    }

    /**
     * @return array
     */
    public function getAllowedServersAddresses() {
        $allow = array();
        $serverIp = gethostbyname($this->host);
        $allow[] = $serverIp;
        if ($this->allowedServerAddresses && !empty($this->allowedServerAddresses)) {
            if (is_string($this->allowedServerAddresses)) {
                $allow = array_merge($allow, explode(',', $this->allowedServerAddresses));
            } else if (is_array($this->allowedServerAddresses)) {
                $allow = array_merge($allow, $this->allowedServerAddresses);
            }
        }
        return array_unique($allow);
    }

}
