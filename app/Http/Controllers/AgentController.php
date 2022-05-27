<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Modules\StatModule;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Http\Requests\AgentCreateRequest;

/**
 *  AgentController class for REST API
 * This class is responsible for getting data for all request related to agent
 *
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */
class AgentController extends Controller
{
    public function index(Request $request, Agent $agent)
    {
        return [
            'agents' => $agent->getAgents($request->get('search')),
            'permissions' => PermissionManager::getReadablePermissionGroups()
        ];
    }

    /**
     * addAgent method will add new agent in person table
     * @param AgentCreateRequest $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function addAgent(AgentCreateRequest $request, Agent $agent)
    {
        try {
            return [
                'message' => __('Support Staff has been added', 'fluent-support'),
                'agent'   => $agent->createAgent($request->all())
            ];

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => __($e->getMessage(), 'fluent-support')
            ]);
        }
    }

    /**
     * updateAgent method will update the information of an exiting agent
     * @param AgentCreateRequest $request
     * @param Agent $agent
     * @param $agentId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function updateAgent(AgentCreateRequest $request, Agent $agent, $agentId)
    {
        $agent = $agent::findOrFail($agentId);

        if ($agent) {
            $data = $this->validate($request->all(), [
                'email'      => 'required|email',
                'first_name' => 'required',
                'last_name'  => 'required',
            ]);

            try {
                return [
                    'message' => __('Support Staff has been updated', 'fluent-support'),
                    'agent'   => $agent->updateAgent($data, $agent)
                ];
            } catch (\Exception $e) {
                return $this->sendError([
                    'message' => __($e->getMessage(), 'fluent-support')
                ]);
            }
        }

    }

    /**
     * deleteAgent will delete an exiting agent and add an alternative agent as replacement
     * @param Request $request
     * @param Agent $agent
     * @param $agentId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function deleteAgent(Request $request, Agent $agent, $agentId)
    {
        $fallBackAgentId = $request->get('fallback_agent_id');

        try {
            $agent->deleteAgent($fallBackAgentId, $agentId);

            return [
                'message' => __('Support Staff has been deleted', 'fluent-support')
            ];

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => __($e->getMessage(), 'fluent-support')
            ]);
        }

    }

    /**
     * @param Request $request
     * @return array
     */
    public function myStats(Request $request)
    {
        $agent = Helper::getAgentByUserId();//Get logged in agent information
        //Get the statistics and responses by the agent
        $response = $this->getAgentStat($request, $agent->id);

        if (defined('FLUENTSUPPORTPRO')) {
            $response['dashboard_notice'] = apply_filters('fluent_support/dashboard_notice', '', $agent);
        }

        return $response;

    }

    /**
     * getAgentStat method will return ticket statistics by an agent id
     *
     * @param Request $request
     * @param $agentId
     * @return array
     */
    public function getAgentStat(Request $request, $agentId)
    {

        $stats = StatModule::getAgentStat($agentId); //Get ticket statistics

        $with = $request->get('with', []);

        return (new Agent())->getAgentStat($stats, $with, $agentId);
    }

    /**
     * addOrUpdateProfileImage method will upload profile picture for a given agent id
     * @param Request $request
     * @return array
     */
    public function addOrUpdateProfileImage(Request $request)
    {
        $allowExtension = [
            'jpeg', 'jpe', 'jpg', 'png'
        ];

        $agent_id = $request->get('agent_id');
        $file = $request->files();

        $ext = $file['file']->getClientOriginalExtension();

        if(!in_array($ext, $allowExtension)){
            return $this->sendError([
                'message' => __('Unsupported file submitted, please select an image file', 'fluent-support')
            ]);
        }

        $agent = Agent::findOrFail($agent_id);

        $uploadedImage = FileSystem::setSubDir('agents_avatars')->put($file);

        if($avatar = $uploadedImage[0]['url']){
            $agent->avatar = $avatar;
            $agent->save();

            return[
                'message' => __('Profile picture has been updated successfully', 'fluent-support'),
                'image'   => $agent->avatar,
                'agent' => $agent
            ];
        }

        else{
            return $this->sendError([
                'message' => __('Something went wrong while updating the profile picture', 'fluent-support')
            ]);
        }
    }
}
