<?php

namespace FluentSupport\Framework\Foundation;

use FluentSupport\Framework\Container\Contracts\BindingResolutionException;

/**
 * @method static \FluentSupport\Framework\Database\DatabaseManager db()
 * @method static \FluentSupport\Framework\View\View view()
 * @method static \FluentSupport\Framework\Events\Dispatcher events()
 * @method static \FluentSupport\Framework\Foundation\Config config()
 * @method static \FluentSupport\Framework\Http\Request\Request request()
 * @method static \FluentSupport\Framework\Http\Response\Response response()
 * @method static \FluentSupport\Framework\Encryption\Encrypter encrypter()
 * @method static \FluentSupport\Framework\Validator\Validator validator()
 */
class App
{
    /**
     * Application instance
     * 
     * @var \FluentSupport\Framework\Foundation\Application
     */
    protected static $instance = null;

    /**
     * Set the application instance
     * 
     * @param \FluentSupport\Framework\Foundation\Application $app
     */
    public static function setInstance($app)
    {
        static::$instance = $app;
    }

    /**
     * Get the application instance
     * 
     * @param  string $module The binding/key name for a component.
     * @param  array $parameters constructor dependencies if any.
     * @return \FluentSupport\Framework\Foundation\Application|mixed
     */
    public static function getInstance($module = null, $parameters = [])
    {
        if ($module) {
            return static::$instance->make($module, $parameters);
        }

        return static::$instance;
    }

    /**
     * Retrive a component from the container
     * 
     * @param  string $module The binding/key name for a component.
     * @param  array $parameters constructor dependencies if any.
     * @return \FluentSupport\Framework\Foundation\Application|mixed
     */
    public static function make($module = null, $parameters = [])
    {
        return static::getInstance($module, $parameters);
    }

    /**
     * Handle dynamic method calls
     * 
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        if ($method === 'support') {
            $param = array_splice($params, 0, 1);
            return static::getInstance(
                'Support.' . implode('.', $param), ...$params
            );
        }

        if (method_exists(static::$instance, $method)) {
            return static::$instance->{$method}(...$params);
        }

        return static::getInstance($method, ...$params);
    }
}
