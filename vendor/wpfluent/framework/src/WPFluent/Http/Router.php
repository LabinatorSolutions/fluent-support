<?php

namespace FluentSupport\Framework\Http;

class Router
{
    /**
     * Application Instance
     * @var \FluentSupport\Framework\Foundation\Application
     */
    protected $app = null;
    
    /**
     * Prefix for the route
     * @var array
     */
    protected $prefix = [];
    
    /**
     * Controller/Handler namespace
     * @var array
     */
    protected $namespace = [];

    /**
     * Registered routes collection
     * @var array
     */
    protected $routes = [];
    
    /**
     * Route policy handler to pass to the route
     * @var string|null
     */
    protected $policyHandler = null;

    /**
     * Route middleware to pass to the route
     * @var array
     */
    protected $middleware = [];

    /**
     * Keep the track of number of group calls
     * @var integer
     */
    protected $groupStack = 0;

    /**
     * Construct the routet instance
     * @param \FluentSupport\Framework\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Create a route group
     * @param  array $attributes
     * @param  \Closure|null $callback
     * @return null
     */
    public function group($attributes = [], \Closure $callback = null)
    {
        $this->maybeIncreaseGroupStack();

        if ($attributes instanceof \Closure) {
            $callback = $attributes;
            $attributes = [];
        }

        if (isset($attributes['prefix'])) {
            $this->prefix($attributes['prefix']);
        }

        if (isset($attributes['policy'])) {
            $this->withPolicy($attributes['policy']);
        }

        if (isset($attributes['middleware'])) {
            $this->middleware($attributes['middleware']);
        }

        if (isset($attributes['namespace'])) {
            $this->namespace($attributes['namespace']);
        }

        $this->executeGroupCallback($callback);
    }

    /**
     * Update the group stack count
     * 
     * @return null
     */
    protected function maybeIncreaseGroupStack()
    {
        $slices = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $lastSlice = end($slices);

        if (basename($lastSlice['file']) != 'routes.php') {
            $this->groupStack += 1;
        }
    }

    /**
     * Set the route prefix
     * 
     * @param  string $prefix
     * @return self
     */
    public function prefix($prefix)
    {
        $this->prefix[] = $prefix;

        return $this;
    }

    /**
     * Set the route middleware
     * @param  array|string $middleware
     * @return self
     */
    public function middleware(...$middleware)
    {
        if (is_array($middleware[0])) {
            $middleware = reset($middleware);
        }

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Set the route policy
     * 
     * @param  string $prefix
     * @return self
     */
    public function withPolicy($handler)
    {
        $this->policyHandler = $handler;

        return $this;
    }

    /**
     * Set the namespace for the action/controller
     * 
     * @param  string $prefix
     * @return self
     */
    public function namespace($ns)
    {
        $this->namespace[] = $ns;

        return $this;
    }

    /**
     * Execute the route group callback
     * 
     * @param  Closure $callback
     * @return null
     */
    protected function executeGroupCallback($callback)
    {
        $callback($this);
        $this->groupStack -= 1;
        array_pop($this->prefix);
        array_pop($this->namespace);
        array_pop($this->middleware);
        
        if ($this->groupStack == 0) {
            $this->policyHandler = null;
        }
    }

    /**
     * Declare a GET route endpoint
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function get($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'GET'
        );

        return $route;
    }

    /**
     * Declare a POST route endpoint
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function post($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'POST'
        );

        return $route;
    }

    /**
     * Declare a PUT route endpoint
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function put($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'PUT'
        );

        return $route;
    }

    /**
     * Declare a PATCH route endpoint
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function patch($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'PATCH'
        );

        return $route;
    }

    /**
     * Declare a DELETE route endpoint
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function delete($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'DELETE'
        );

        return $route;
    }

    /**
     * Declare a route endpoint that matches any HTTP Verb/Method
     * @param  string $uri
     * @param  string|Closure $handler
     * @return \FluentSupport\Framework\Http\Route
     */
    public function any($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, \WP_REST_Server::ALLMETHODS
        );

        return $route;
    }

    /**
     * Create a new route instance
     * @param  string $uri
     * @param  string|Closure $handler
     * @param  string $method HTTP Method
     * @return \FluentSupport\Framework\Http\Route
     */
    protected function newRoute($uri, $handler, $method)
    {
        $route = Route::create(
            $this->app,
            $this->getRestNamespace(),
            $this->buildUriWithPrefix($uri),
            $handler,
            $method
        );

        if ($this->policyHandler) {
            $route->withPolicy($this->policyHandler);
        }

        if ($this->middleware) {
            $route->middleware($this->middleware);
        }

        if ($this->namespace) {
            $route->withNamespace($this->namespace);
        }

        return $route;
    }

    /**
     * Resolve the rest namespace for the plugin
     * 
     * @return string
     */
    protected function getRestNamespace()
    {
        $version = $this->app->config->get('app.rest_version');

        $namespace = trim($this->app->config->get('app.rest_namespace'), '/');

        return "{$namespace}/{$version}";
    }

    /**
     * Build the URI with the prefix
     * 
     * @param  string $uri
     * @return string The URI
     */
    protected function buildUriWithPrefix($uri)
    {
        $uri = trim($uri, '/');

        $prefix = array_map(function($prefix) {
            return trim($prefix, '/');
        }, $this->prefix);

        $prefix = implode('/', $prefix);

        return trim($prefix, '/') . '/' . trim($uri, '/');
    }

    /**
     * Register all the routse in WordPress Rest Engine
     * 
     * @return null
     */
    public function registerRoutes()
    {
        foreach ($this->routes as $route) $route->register();
    }

    /**
     * Get all ther registered routes
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
