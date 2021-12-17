<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Http\Controllers\AuthController;
use FluentSupport\App\Models\Agent;

class Agents
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(Agent $instance)
    {
        $this->instance = $instance;
    }

    /**
     * getAgents method will return a all available customers
     *
     * @return object
     */

    public function getAgents()
    {
        return Agent::paginate();
    }

    /**
     * getCustomer method will return a specific customer by id
     *
     * @param  int   $agentId
     * @return object
     */

    public function getAgent(int $agentId)
    {
        if(is_numeric($agentId)){
            return Agent::findOrFail($agentId);
        }
        return false;
    }

    /**
     * updateAgent method will update the specific customer by id
     *
     * @param  array   $data
     * @param  int   $agentId
     * @return object
     */

    public function updateAgent(array $data, int $agentId)
    {
        if(!$agentId) {
            return false;
        }
        if($agent = Agent::where('id', $agentId)->first()){
            return $agent->update($data);
        }
        return false;
    }

    /**
     * createAgentWithOrWithoutWpUser method will create a new agent
     * also it will create a wp user if you want to create one
     * if you want to create a wp user too then the process will be 1st it will create a wp user
     * then after creating the wp user successfully it will create a fluent support agent
     *
     * @param  array   $data
     * @param bool $createWpUser
     * @return object
     */

    public function createAgentWithOrWithoutWpUser(array $data, bool $createWpUser=false)
    {
        if(!$createWpUser) {
            $isExist = Agent::where('email', $data['email'])->first();
            if (!$data['email'] || !is_email($data['email']) || $isExist) {
                return false;
            }
            return Agent::create($data);
        }

        if(!username_exists($data['username'])){
            $authController = new AuthController();
            $createdUser = $authController->createUser($data);
            $updateUser = $authController->maybeUpdateUser($createdUser, $data);
            if($createdUser){
                $data['user_id'] = $createdUser;
                return Agent::create($data);

            }
        }
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function __call($method, $params)
    {
        if (in_array($method, $this->allowedInstanceMethods)) {
            return call_user_func_array([$this->instance, $method], $params);
        }

        throw new \Exception("Method {$method} does not exist.");
    }
}
