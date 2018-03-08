<?php

$object = new AppServer();

$setting = [
    'log_file' => 'swoole_http.log',
    'worker_num' => 4, // 4个工作进程
];
$server = new swoole_http_server("127.0.0.1", 9503);
$server->set($setting);

$server->on('request', array($object, 'onRequest'));
$server->on('close', array($object, 'onClose'));

$server->start();

/**
 * Class AppServer
 * @property \swoole_http_request $request
 * @property \swoole_http_response $response
 * @property \PDO $db
 * @property \lib\Session $session
 */
class AppServer
{
    private $module = [];

    /** @var AppServer */
    private static $instance;

    public static function getInstance()
    {
        return self::$instance;
    }

    public function __construct()
    {
        $baseControllerFile = __DIR__ .'/controller/Base.php';
        require_once "$baseControllerFile";
    }

    /**
     * @param swoole_http_request $request
     * @param swoole_http_response $response
     */
    public function onRequest($request, $response)
    {
        $this->module['request'] = $request;
        $this->module['response'] = $response;
        self::$instance = $this;

        list($controllerName, $methodName) = $this->route($request);
        empty($controllerName) && $controllerName = 'index';
        empty($methodName) && $methodName = 'index';

        try {
            $controllerClass = "\\controller\\" . ucfirst($controllerName);
            $controllerFile = __DIR__ . "/controller/" . ucfirst($controllerName) . ".php";
            if (!class_exists($controllerClass, false)) {
                if (!is_file($controllerFile)) {
                    throw new Exception('控制器不存在');
                }
                require_once "$controllerFile";
            }

            $controller = new $controllerClass($this);
            if (!method_exists($controller, $methodName)) {
                throw new Exception('控制器方法不存在');
            }

            ob_start();
            $return = $controller->$methodName();
            $return .= ob_get_contents();
            ob_end_clean();
            $this->session->end();
            $response->end($return);
        } catch (Exception $e) {
            $response->status(500);
            $response->end($e->getMessage());
        }
    }

    private function route($request)
    {
        $pathInfo = explode('/', $request->server['path_info']);
        return [$pathInfo[1], $pathInfo[2]];
    }

    public function onClose($server, $fd, $reactorId)
    {

    }

    public function __get($name)
    {
        if (!in_array($name, array('request', 'response', 'db', 'session'))) {
            return null;
        }
        if (empty($this->module[$name])) {
            $moduleClass = "\\lib\\" . ucfirst($name);
            $moduleFile = __DIR__ . '/lib/' . ucfirst($name) . ".php";
            if (is_file($moduleFile)) {
                require_once "$moduleFile";
                $object = new $moduleClass;
                $this->module[$name] = $object;
            }
        }
        return $this->module[$name];
    }
}
