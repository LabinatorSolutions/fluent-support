<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Modules\Auth\AuthService;
use FluentSupport\App\Models\Agent;

/**
 * Agents PHP API.
 *
 * Example Usage: $agentsApi = FluentSupportApi('agents');
 */
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
     * Paginated list of agents.
     *
     * @return object
     */

    public function getAgents()
    {
        return Agent::paginate();
    }

    /**
     * @param int $agentId
     * @return mixed
     */

    public function getAgent($agentId)
    {
        if (is_numeric($agentId)) {
            return Agent::findOrFail($agentId);
        }
        return false;
    }

    /**
     * @param array $data
     * @param int $agentId
     * @return mixed
     */

    public function updateAgent(array $data, $agentId)
    {
        if (!$agentId) {
            return false;
        }
        if ($agent = Agent::where('id', $agentId)->first()) {
            return $agent->update($data);
        }
        return false;
    }

    /**
     * Create an agent, optionally creating the WP user first.
     *
     * @param array $data
     * @param bool $createWpUser
     * @return mixed
     */

    public function createAgentWithOrWithoutWpUser(array $data, $createWpUser = false)
    {
        if (!$createWpUser) {
            $isExist = Agent::where('email', $data['email'])->first();
            if (!$data['email'] || !is_email($data['email']) || $isExist) {
                return false;
            }
            return Agent::create($data);
        }

        if (!username_exists($data['username'])) {
            $createdUser = AuthService::createUser($data);
            if ($createdUser) {
                $data['user_id'] = $createdUser;
                return Agent::create($data);

            }
        }

        return false;
    }

    /**
     * @param int $id
     */
    public function deleteAgent($id)
    {
        if (!$id) {
            return;
        }
        Agent::findOrFail($id)->delete();
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

        throw new \Exception(sprintf('Method %s does not exist.', esc_html($method)));
    }
}
