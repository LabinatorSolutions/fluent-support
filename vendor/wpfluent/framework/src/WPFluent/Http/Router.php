<?php

namespace FluentSupport\Framework\Http;

class Router
{
    protected $app = null;
    
    protected $prefix = [];
    
    protected $namespace = [];

    protected $routes = [];

    protected $routeGroups = [];
    
    protected $groupStack = [];
    
    protected $policyHandler = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function group($attributes = [], \Closure $callback = null)
    {
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

        if (isset($attributes['namespace'])) {
            $this->namespace($attributes['namespace']);
        }

        return $this->executeGroupCallback($callback);
    }

    public function prefix($prefix)
    {
        $this->prefix[] = $prefix;

        return $this;
    }

    public function withPolicy($handler)
    {
        $this->policyHandler = $handler;

        return $this;
    }

    public function namespace($ns)
    {
        $this->namespace[] = $ns;

        return $this;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    protected function executeGroupCallback($callback)
    {
        $callback($this);
        array_pop($this->prefix);
        array_pop($this->namespace);
    }

    public function get($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'GET'
        );

        return $route;
    }

    public function post($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'POST'
        );

        return $route;
    }

    public function put($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'PUT'
        );

        return $route;
    }

    public function patch($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'PATCH'
        );

        return $route;
    }

    public function delete($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, 'DELETE'
        );

        return $route;
    }

    public function any($uri, $handler)
    {
        $this->routes[] = $route = $this->newRoute(
            $uri, $handler, \WP_REST_Server::ALLMETHODS
        );

        return $route;
    }

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

        if ($this->namespace) {
            $route->withNamespace($this->namespace);
        }

        return $route;
    }

    protected function getRestNamespace()
    {
        $version = $this->app->config->get('app.rest_version');

        $namespace = trim($this->app->config->get('app.rest_namespace'), '/');

        return "{$namespace}/{$version}";
    }

    protected function buildUriWithPrefix($uri)
    {
        $uri = trim($uri, '/');

        $prefix = array_map(function($prefix) {
            return trim($prefix, '/');
        }, $this->prefix);

        $prefix = implode('/', $prefix);

        return trim($prefix, '/') . '/' . trim($uri, '/');
    }

    public function registerRoutes()
    {
        foreach ($this->routes as $route) $route->register();
    }

    public function getRoutes()
    {
        return $this->routes;
    }
}
