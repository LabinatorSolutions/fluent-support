<?php

namespace FluentSupport\Framework\Foundation;

trait FoundationTrait
{
    public function env()
    {
        return $this->config->get('app.env');
    }

    public function hook($prefix, $hook)
    {
        return $prefix . $hook;
    }

    public function parseRestHandler($handler)
    {
        if ($handler instanceof \Closure) {
            return $handler;
        }

        if (is_array($handler)) {

            if (is_object($handler[0])) {
                return $handler;
            }

            if (is_string($handler[0])) {
                $handler = $handler[0] . '@' . $handler[1];
            }
        }

        return $this->getControllerNamespace($handler) . '\\' . $handler;
    }

    public function parsePolicyHandler($handler)
    {
        if (!$handler) return;

        if (is_string($handler)) {
            $handler = $this->getPolicyNamespace($handler) . '\\' . $handler;

            if ($this->isCallableWithAtSign($handler)) {
                list($class, $method) = explode('@', $handler);
                if (!method_exists($class, $method)) {
                    $method = 'verifyRequest';
                    if (!method_exists($class, $method)) {
                        $method = '__returnTrue';
                    }
                }
                $instance = $this->make($class);
                $handler = [$instance, $method];
            }

        } else if (is_array($handler)) {
            list($class, $method) = $handler;

            if (is_string($class)) {
                $handler = $this->getPolicyNamespace($handler) . '\\' . $class . '::' . $method;
            }
        }

        return $handler;
    }

    public function addAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_action(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    public function addCustomAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->addAction(
            $this->hook($prefix, $action), $handler, $priority, $numOfArgs
        );
    }

    public function doAction()
    {
        return call_user_func_array('do_action', func_get_args());
    }

    public function doCustomAction()
    {
        $args = func_get_args();

        $prefix = $this->config->get('app.hook_prefix');

        $args[0] = $this->hook($prefix, $args[0]);

        return call_user_func_array('do_action', $args);
    }

    public function addFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_filter(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    public function addCustomFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->addFilter(
            $this->hook($prefix, $action), $handler, $priority, $numOfArgs
        );
    }

    public function applyFilters()
    {
        return call_user_func_array('apply_filters', func_get_args());
    }

    public function applyCustomFilters()
    {
        $args = func_get_args();
        $prefix = $this->config->get('app.hook_prefix');
        $args[0] = $this->hook($prefix, $args[0]);

        return call_user_func_array('apply_filters', $args);
    }

    public function addShortcode($action, $handler)
    {
        return add_shortcode(
            $action,
            $this->parseHookHandler($handler)
        );
    }

    public function doShortcode($content, $ignore_html = false)
    {
        return do_shortcode($content, $ignore_html);
    }

    public function parseHookHandler($handler)
    {
        if (is_string($handler)) {
            list($class, $method) = preg_split('/::|@/', $handler);

            $class = $this->makeInstance($class);

            return [$class, $method];

        } else if (is_array($handler)) {
            list($class, $method) = $handler;
            if (is_string($class)) {
                $class = $this->makeInstance($class);
            }
            return [$class, $method];
        }

        return $handler;
    }

    public function hasNamespace($handler)
    {
        if ($handler instanceof \Closure) {
            return false;
        };
        
        $parts = explode('\\', $handler);
        
        return count($parts) > 1;
    }

    public function getControllerNamespace($handler)
    {
        if ($this->hasNamespace($handler)) {
            return '';
        }

        return $this->controllerNamespace;
    }

    public function getPolicyNamespace($handler)
    {
        if ($this->hasNamespace($handler)) {
            return '';
        }

        return $this->policyNamespace;
    }

    public function makeInstance($class)
    {
        if ($this->hasNamespace($class)) {
            $instance = $this->make($class);
        } else {
            $instance = $this->make($this->handlerNamespace . '\\' . $class);
        }

        return $instance;
    }

    public function url($url = '')
    {
        return $this->baseUrl.ltrim($url, '/');
    }

    private function addAjaxAction($action, $handler, $priority, $scope)
    {
        if ($scope == 'admin') {
            return add_action(
                'wp_ajax_'.$action,
                $this->parseHookHandler($handler),
                $priority
            );
        }

        if ($scope == 'public') {
            return add_action(
                'wp_ajax_nopriv_'.$action,
                $this->parseHookHandler($handler),
                $priority
            );
        }
    }

    public function addAjaxActions($action, $handler, $priority = 10)
    {
        $this->addAjaxAction($action, $handler, $priority, 'admin');
        $this->addAjaxAction($action, $handler, $priority, 'public');
    }

    public function addAdminAjaxAction($action, $handler, $priority = 10)
    {
        return $this->addAjaxAction($action, $handler, $priority, 'admin');
    }

    public function addPublicAjaxAction($action, $handler, $priority = 10)
    {
        return $this->addAjaxAction($action, $handler, $priority, 'public');
    }
}
