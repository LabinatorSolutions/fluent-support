<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Reporting\Reporting;
use FluentSupport\App\Modules\StatModule;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\TicketHelper;
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
            try {
                return [
                    'message' => __('Support Staff has been updated', 'fluent-support'),
                    'agent'   => $agent->updateAgent($request->all(), $agent)
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
     * @param $agentId
     * @return array
     */
    public function deleteAgent(Request $request, $agentId)
    {
        $fallBackAgentId = $request->get('fallback_agent_id');

        if ($fallBackAgentId == $agentId) {
            return $this->sendError([
                'message' => __('Old Agent and New agent is same person', 'fluent-support')
            ]);
        }

        $agent = Agent::findOrFail($agentId);

        PermissionManager::attachPermissions($agent->user_id, []);

        $newAgent = Agent::findOrFail($fallBackAgentId);

        Attachment::where('person_id', $agentId)->update([
            'person_id' => $newAgent->id
        ]);

        Conversation::where('person_id', $agentId)->update([
            'person_id' => $newAgent->id
        ]);

        Product::where('created_by', $agentId)->update([
            'created_by' => $newAgent->id
        ]);

        Ticket::where('agent_id', $agentId)->update([
            'agent_id' => $newAgent->id
        ]);

        $agent->deleteAllMeta();

        Agent::where('id', $agentId)->delete();

        return [
            'message' => __('Selected support staff has been deleted', 'fluent-support')
        ];

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
        $agent = Agent::findOrFail($agentId);//Get agent information
        $stats = StatModule::getAgentStat($agentId);//Get ticket statistics

        //If the logged-in user has permission to manage unassigned tickets, get number of unassigned tickets
        if (PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            $stats['unassigned_tickets'] = [
                'title' => __('Unassigned Tickets', 'fluent-support'),
                'count' => Ticket::whereNull('agent_id')->where('status', '!=', 'closed')->count()
            ];
        }

        $data = [
            'stats' => $stats
        ];

        $with = $request->get('with', []);

        //If the request come with suggested_tickets
        if (in_array('suggested_tickets', $with)) {
            //Get suggested tickets from ticketHelper
            $data['suggested_tickets'] = TicketHelper::getSuggestedTickets($agent->id);
        }

        //If the request come with mentioned_tickets
        if (defined('FLUENTSUPPORTPRO') && in_array('ticket_to_watch', $with)) {
            //Get the overall statistics by the agent
            $data['ticket_to_watch'] = TicketHelper::getTicketsToWatch();
        }

        //If the request come with overall_stats
        if (in_array('overall_stats', $with)) {
            //Get overall status
            $data['overall_stats'] = (new Reporting())->getActiveStats();
        }

        //If the request come with individual_stat
        if (in_array('individual_stat', $with)) {
            //get overall statistics by agent id
            $data['individual_stat'] = (new Reporting())->getActiveStatByAgent($agent->id);
        }
        //If the request come with my_overall_stats
        if (in_array('my_overall_stats', $with)) {
            //Get the overall statistics by the agent
            $data['my_overall_stats'] = StatModule::getAgentOverallStats($agent->id);
        }

        return $data;
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
