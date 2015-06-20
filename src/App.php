<?php namespace Rakit\Framework;

use ArrayAccess;
use Closure;
use Exception;
use InvalidArgumentException;
use Rakit\Framework\Exceptions\HttpErrorException;
use Rakit\Framework\Exceptions\HttpNotFoundException;
use Rakit\Framework\Http\Request;
use Rakit\Framework\Http\Response;
use Rakit\Framework\Router\Route;
use Rakit\Framework\Router\Router;
use Rakit\Framework\View\View;

class App implements ArrayAccess {

    use MacroableTrait;

    const VERSION = '0.0.1';

    protected static $instances = [];

    protected static $default_instance = 'default';

    public $container;

    protected $name;

    protected $booted = false;

    protected $middlewares = array();

    protected $waiting_list_providers = array();

    protected $providers = array();

    protected $exception_handlers = array();

    /**
     * Constructor
     * 
     * @param   string $name
     * @param   array $configs
     * @return  void
     */
    public function __construct($name, array $configs = array())
    {
        $this->name = $name;
        $default_configs = [];
        $configs = array_merge($default_configs, $configs);

        $this->container = new Container;
        $this['app'] = $this;
        $this['config'] = new Configurator($configs);
        $this['router'] = new Router($this); 
        $this['hook'] = new Hook($this);
        $this['request'] = new Request($this);
        $this['response'] = new Response($this);

        static::$instances[$name] = $this;

        if(count(static::$instances) == 1) {
            static::setDefaultInstance($name);
        }

        $this->registerBaseHooks();
        $this->registerDefaultMacros();
        $this->registerBaseProviders();
    }

    /**
     * Register a Service Provider into waiting lists
     *
     * @param   string $class
     */
    public function provide($class)
    {
        $this->providers[$class] = $provider = $this->container->make($class);
        if(false === $provider instanceof Provider) {
            throw new InvalidArgumentException("Provider {$class} must be instance of Rakit\\Framework\\Provider", 1);
        }

        $provider->register();
    }

    /**
     * Register a middleware
     * 
     * @param   string $name
     * @param   mixed $callable
     * @return  void
     */
    public function middleware($name, $callable)
    {
        $this->middlewares[$name] = $callable;
    }

    /**
     * Register GET route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function get($path, $action)
    {
        $args = array_merge(['GET'], func_get_args());
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Register POST route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function post($path, $action)
    {
        $args = array_merge(['POST'], func_get_args());
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Register PUT route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function put($path, $action)
    {
        $args = array_merge(['PUT'], func_get_args());
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Register PATCH route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function patch($path, $action)
    {
        $args = array_merge(['PATCH'], func_get_args());
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Register DELETE route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function delete($path, $action)
    {
        $args = array_merge(['DELETE'], func_get_args());
        return call_user_func_array([$this, 'route'], $args);
    }
    
    /**
     * Register DELETE route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function group($prefix, $action)
    {
        return call_user_func_array([$this->router, 'group'], func_get_args());
    }

    /**
     * Registering a route
     * 
     * @param   string $path
     * @param   mixed $action
     * @return  Rakit\Framework\Routing\Route
     */
    public function route($methods, $path, $action)
    {
        return call_user_func_array([$this->router, 'register'], func_get_args());
    }

    /**
     * Booting app
     * 
     * @return  boolean
     */
    public function boot()
    {
        if($this->booted) return false;

        $providers = $this->providers;
        foreach($providers as $provider) {
            $provider->boot();
        }

        // reset providers, we don't need them anymore
        $this->providers = [];
        
        return $this->booted = true;
    }

    /**
     * Run application
     * 
     * @param   string $path
     * @param   string $method
     * @return  void
     */
    public function run($method = null, $path = null)
    {
        try {
            $this->boot();

            $path = $path ?: $this->request->path();
            $method = $method ?: $this->request->server['REQUEST_METHOD'];
            $matched_route = $this->router->findMatch($path, $method);

            if(!$matched_route) {
                throw new HttpNotFoundException();
            }

            $this->request->defineRoute($matched_route);
            $middlewares = $matched_route->getMiddlewares();
            $action = $matched_route->getAction();
            
            $this->makeActions($middlewares, $action);
            $this->runActions();
            $this->response->send();

            return $this;
        } catch (Exception $e) {
            $exception_class = get_class($e);
            $exception_classes = array_values(class_parents($exception_class));
            array_unshift($exception_classes, $exception_class);

            $handler = null;
            foreach($exception_classes as $xclass) {
                if(array_key_exists($xclass, $this->exception_handlers)) {
                    $handler = $this->exception_handlers[$xclass];
                    break;
                }
            }

            if(!$handler) {
                $handler = function(Exception $e, App $app) {
                    return $app->response->html($e->getMessage());
                };
            }

            $this->hook->apply('error', [$e]);
            $this->container->call($handler, [$e]);
            $this->response->send();

            return $this;
        }
    }

    /**
     * Handle specified exception
     */
    public function exception(Closure $fn)
    {
        $dependencies = Container::getCallableDependencies($fn);
        $exception_class = $dependencies[0];

        if(is_subclass_of($exception_class, 'Exception') OR $exception_class == 'Exception') {
            $this->exception_handlers[$exception_class] = $fn;
        } else {
            throw new InvalidArgumentException("Parameter 1 of exception handler must be instanceof Exception or Exception itself", 1);
        }
    }

    /**
     * Stop application
     *
     * @return void
     */
    public function stop()
    {
        $this->hook->apply("app.exit", [$this]);
        exit();
    }

    /**
     * Not Found
     */
    public function notFound()
    {
        throw new HttpNotFoundException();
    }

    /**
     * Abort app
     * 
     * @param   int $status
     * 
     * @return  void
     */
    public function abort($status, $message = null)
    {
        if($status == 404) {
            throw new HttpNotFoundException;
        } else {
            throw new HttpErrorException;
        }
    }

    /**
     * Set default instance name
     *
     * @param   string $name
     */
    public static function setDefaultInstance($name)
    {
        static::$default_instance = $name;
    }

    /**
     * Getting an application instance
     *
     * @param   string $name
     */
    public static function getInstance($name = null)
    {
        if(!$name) $name = static::$default_instance;
        return static::$instances[$name];
    }

    /**
     * Make/build app actions
     * 
     * @param   array $middlewares
     * @param   mixed $controller
     * @return  void
     */
    protected function makeActions(array $middlewares, $controller)
    {
        $app = $this;
        $actions = array_merge($middlewares, [$controller]);
        $index_controller = count($actions)-1;

        foreach($actions as $i => $action) {
            $index = $i+1;
            $type = $i == $index_controller? 'controller' : 'middleware';
            $this->registerAction($index, $action, $type);
        };
    }

    /**
     * Register an action into container
     *
     * @param   int $index
     * @param   callable $action
     * @param   string $type
     * @return  void
     */
    protected function registerAction($index, $action, $type)
    {
        $curr_key = 'app.action.'.($index);
        $next_key = 'app.action.'.($index+1);

        $app = $this;
        
        $app[$curr_key] = $app->container->protect(function() use ($app, $type, $action, $next_key, $curr_key) {
            $next = $app[$next_key];

            // if type of action is controller, default parameters should be route params
            if($type == 'controller') 
            {
                $matched_route = $app->request->route();
                $params = $matched_route->params;

                $callable = $this->resolveController($action, $params);
            } 
            else // parameter middleware should be Request, Response, $next
            {
                $params = [$app->request, $app->response, $next];
                $callable = $this->resolveMiddleware($action, $params);
            }

            $returned = $app->container->call($callable);

            if(is_array($returned)) {
                $app->response->json($returned);
            } elseif(is_string($returned)) {
                $app->response->html($returned);
            }

            return $app->response->body;
        });
    }

    /**
     * Run actions
     * 
     * @return  void
     */
    protected function runActions()
    {
        $action = $this->container['app.action.1'];
        return $action? $action() : null;
    }

    /**
     * Resolving middleware action
     */
    public function resolveMiddleware($middleware_action, array $params = array())
    {
        if(is_string($middleware_action)) {
            $explode_params = explode(':', $middleware_action);
                
            $middleware_name = $explode_params[0];
            if(isset($explode_params[1])) {
                $params = array_merge($params, explode(',', $explode_params[1]));
            }

            // if middleware is registered, get middleware
            if(array_key_exists($middleware_name, $this->middlewares)) {
                // Get middleware. so now, callable should be string Foo@bar, Closure, or function name
                $callable = $this->middlewares[$middleware_name];
            } else {
                // othwewise, middleware_name should be Foo@bar or Foo
                $callable = $middleware_name;
            }

            return $this->resolveCallable($callable, $params);
        } else {
            return $this->resolveCallable($middleware_action, $params);
        }
    }

    public function resolveController($controller_action, array $params = array())
    {
        return $this->resolveCallable($controller_action, $params);
    }

    /**
     * Register base hooks
     */
    protected function registerBaseHooks()
    {
     
    }

    /**
     * Register base providers
     */
    public function registerBaseProviders()
    {
        $base_providers = [
            'Rakit\Framework\View\ViewServiceProvider',
        ];

        foreach($base_providers as $provider_class) {
            $this->provide($provider_class);
        }
    }

    /**
     * Register default macros
     */
    protected function registerDefaultMacros()
    {
        static::macro('resolveCallable', function($unresolved_callable, array $params = array()) {
            if(is_string($unresolved_callable)) {
                // in case "Foo@bar:baz,qux", baz and qux should be parameters, separate it!
                $explode_params = explode(':', $unresolved_callable);
                
                $unresolved_callable = $explode_params[0];
                if(isset($explode_params[1])) {
                    $params = array_merge($params, explode(',', $explode_params[1]));  
                } 

                // now $unresolved_callable should be "Foo@bar" or "foo",
                // if there is '@' character, transform it to array class callable
                $explode_method = explode('@', $unresolved_callable);
                if(isset($explode_method[1])) {
                    $callable = [$explode_method[0], $explode_method[1]];
                } else {
                    // otherwise, just leave it as string, maybe that was function name
                    $callable = $explode_method[0];
                }
            } else {
                $callable = $unresolved_callable;
            }

            $app = $this;

            // last.. wrap callable in Closure
            return function() use ($app, $callable, $params) {
                return $app->container->call($callable, $params);                    
            };
        });

        static::macro('baseUrl', function($path) {
            $path = '/'.trim($path, '/');
            $base_url = trim($this->config->get('app.base_url', 'http://localhost:8000'), '/');

            return $base_url.$path;
        });

        static::macro('indexUrl', function($path) {
            $path = trim($path, '/');
            $index_file = trim($this->config->get('app.index_file', ''), '/');
            return $this->baseUrl($index_file.'/'.$path);  
        });
        
        static::macro('routeUrl', function($route_name, array $params = array()) {
            if($route_name instanceof Route) {
                $route = $route_name;
            } else {
                $route = $app->router->findRouteByName($route_name);        
                if(! $route) {
                    throw new \Exception("Trying to get url from unregistered route named '{$route_name}'");
                }
            }

            $path = $route->getPath();
            $path = str_replace(['(',')'], '', $path);
            foreach($params as $param => $value) {
                $path = preg_replace('/:'.$param.'\??/', $value, $path);
            }

            $path = preg_replace('/\/?\:[a-zA-Z0-9._-]+/','', $path);

            return $this->indexUrl($path);
        });
        
        static::macro('redirect', function($defined_url) {
            if(preg_match('http(s)?\:\/\/', $defined_url)) {
                $url = $defined_url;
            } elseif($this->router->findRouteByName($defined_url)) {
                $url = $this->routeUrl($defined_url);
            } else {
                $url = $this->indexUrl($defined_url);
            }

            $this->hook->apply('response.redirect', [$url, $defined_url]);

            header("Location: ".$url);
            exit();
        });

        static::macro('dd', function() {
            var_dump(func_get_args());
            exit();
        });
    }

    /**
     * ---------------------------------------------------------------
     * Setter and getter
     * ---------------------------------------------------------------
     */
    public function __set($key, $value)
    {
        $this->container->register($key, $value);
    }

    public function __get($key)
    {
        return $this->container->get($key);
    }

    /**
     * ---------------------------------------------------------------
     * ArrayAccess interface methods
     * ---------------------------------------------------------------
     */
    public function offsetSet($key, $value) {
        return $this->container->register($key, $value);
    }

    public function offsetExists($key) {
        return $this->container->has($key);
    }

    public function offsetUnset($key) {
        return $this->container->remove($key);
    }

    public function offsetGet($key) {
        return $this->container->get($key);
    }

}