<?php

namespace FluentSupport\Framework\Foundation;

use FluentSupport\Framework\View\View;
use FluentSupport\Framework\Http\Router;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Response\Response;
use FluentSupport\Framework\Events\Dispatcher;
use FluentSupport\Framework\Database\Orm\Model;
use FluentSupport\Framework\Validator\Validator;
use FluentSupport\Framework\Foundation\RequestGuard;
use FluentSupport\Framework\Pagination\AbstractPaginator;
use FluentSupport\Framework\Database\ConnectionResolver;
use FluentSupport\Framework\Database\Query\WPDBConnection;
use FluentSupport\Framework\Foundation\UnAuthorizedException;

use FluentSupport\Framework\Support\Str;
use FluentSupport\Framework\Support\Helper;
use FluentSupport\Framework\Encryption\Encrypter;
use FluentSupport\Framework\Encryption\MissingAppKeyException;

class ComponentBinder
{
    protected $app = null;

    protected $bindables = [
        'Request',
        'Response',
        'Validator',
        'View',
        'Events',
        'DataBase',
        'Router',
        'Paginator',
        'encrypter'
    ];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function bindComponents()
    {
        foreach ($this->bindables as $value) {
            $method = "bind{$value}";
            $this->{$method}();
        }

        $this->extendBindings($this->app);

        $this->loadGlobalFunctions($this->app);

        $this->registerResolvingEvent($this->app);
    }

    protected function registerResolvingEvent($app)
    {
        $app->resolving(RequestGuard::class, function($request) use ($app) {

            $request->merge($request->beforeValidation());

            $data = $request->validate($app->make('validator'));

            $request->merge($request->afterValidation());
        });
    }

    protected function bindRequest()
    {
        $this->app->singleton(Request::class, function ($app) {
            return new Request($app, $_GET, $_POST, $_FILES);
        });

        $this->app->alias(Request::class, 'request');
    }

    protected function bindResponse()
    {
        $this->app->singleton(Response::class, function($app) {
            return new Response($app);
        });

        $this->app->alias(Response::class, 'response');
    }

    protected function bindValidator()
    {
        $this->app->bind(Validator::class, function($app) {
            return new Validator;
        });

        $this->app->alias(Validator::class, 'validator');
    }

    protected function bindView()
    {
        $this->app->bind(View::class, function($app) {
            return new View($app);
        });

         $this->app->alias(View::class, 'view');
    }

    protected function bindEvents()
    {
        $this->app->singleton(Dispatcher::class, function($app) {
            return new Dispatcher($app);
        });

        $this->app->alias(Dispatcher::class, 'events');
    }

    protected function bindDataBase()
    {
        $this->app->singleton('db', function($app) {
            return new WPDBConnection(
                $GLOBALS['wpdb'], $app->config->get('database')
            );
        });

        Model::setEventDispatcher($this->app['events']);
        
        Model::setConnectionResolver(new ConnectionResolver);
    }

    protected function bindRouter()
    {
        $this->app->singleton('router', function($app) {
            return new Router($app);
        });
    }

    protected function bindPaginator()
    {
        AbstractPaginator::currentPathResolver(function () {
            return $this->app['request']->url();
        });
        
        AbstractPaginator::currentPageResolver(function ($pageName = 'page') {
            $page = $this->app['request']->get($pageName);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return $page;
            }

            return 1;
        });
    }

    protected function bindEncrypter()
    {
        $this->app->singleton(Encrypter::class, function ($app) {
            return new Encrypter($app);
        });

        $this->app->alias(Encrypter::class, 'encrypter');    
    }

    protected function extendBindings($app)
    {
        $bindings = $app['path'] . 'boot/bindings.php';

        if (is_readable($bindings)) {
            require_once $bindings;
        }
    }

    protected function loadGlobalFunctions($app)
    {
        $globals = $app['path'] . 'boot/globals.php';
        
        if (is_readable($globals)) {
            require_once $globals;
        }
    }
}
