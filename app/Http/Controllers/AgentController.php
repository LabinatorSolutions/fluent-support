<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Person;
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
use FluentSupport\Framework\Support\Arr;

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
    public function index(Request $request)
    {
        $agents = Agent::orderBy('id', 'DESC')
            ->searchBy($request->get('search'))
            ->paginate();

        foreach ($agents as $agent) {
            $agent->permissions = PermissionManager::getUserPermissions($agent->user_id);
            if ($agent->user_id) {
                $agent->user_profile = admin_url('user-edit.php?user_id=' . $agent->user_id);
            }
            $agent->replies_count = Conversation::where('person_id', $agent->id)->count();
            $agent->interactions_count = Conversation::where('person_id', $agent->id)->groupBy('ticket_id')->get()->count();

            $agent->telegram_chat_id = $agent->getMeta('telegram_chat_id');
            $agent->slack_user_id = $agent->getMeta('slack_user_id');
            $agent->whatsapp_number = $agent->getMeta('whatsapp_number');
        }

        return [
            'agents'      => $agents,
            'permissions' => PermissionManager::getReadablePermissionGroups()
        ];
    }

    /**
     * addAgent method will add new agent in person table
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function addAgent(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email'
        ]);

        $email = $data['email'];

        $user = get_user_by('email', $email);

        if (!$user || is_wp_error($user)) {
            return $this->sendError([
                'message' => __('Sorry, Connected user could not be found with the provided email address', 'fluent-support')
            ]);
        }

        if (empty($data['first_name'])) {
            $data['first_name'] = $user->first_name;
        }

        if (empty($data['last_name'])) {
            $data['last_name'] = $user->last_name;
        }

        // check if another agent has same email address
        $exist = Person::where('email', $email)->first();
        if ($exist) {
            return $this->sendError([
                'message' => __('Sorry, Another agent/person exist with the same email address. Please use a different email address', 'fluent-support')
            ]);
        }

        $data['user_id'] = $user->ID;
        $agent = Agent::create($data);
        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        if (!empty($data['telegram_chat_id'])) {
            $chatId = sanitize_text_field($data['telegram_chat_id']);
            $agent->updateMeta('telegram_chat_id', $chatId);
            $agent->telegram_chat_id = $chatId;
        }

        if (!empty($data['slack_user_id'])) {
            $chatId = sanitize_text_field($data['slack_user_id']);
            $agent->updateMeta('slack_user_id', $chatId);
            $agent->slack_user_id = $chatId;
        }

        if (!empty($data['whatsapp_number'])) {
            $whatsappNumber = sanitize_text_field($data['whatsapp_number']);
            $agent->updateMeta('whatsapp_number', $whatsappNumber);
            $agent->whatsapp_number = $whatsappNumber;
        }

        return [
            'message' => __('Support Staff has been added', 'fluent-support'),
            'agent'   => $agent
        ];
    }

    /**
     * updateAgent method will update the information of an exiting agent
     * @param Request $request
     * @param $agentId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function updateAgent(Request $request, $agentId)
    {
        $agent = Agent::findOrFail($agentId);
        $data = $request->all();
        $this->validate($data, [
            'email'      => 'required|email',
            'first_name' => 'required',
            'last_name'  => 'required',
        ]);

        $user = get_user_by('ID', $agent->user_id);

        if (!$user || is_wp_error($user)) {
            return $this->sendError([
                'message' => __('Sorry, Connected user could not be found with the provided email address', 'fluent-support')
            ]);
        }

        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        $updateData = Arr::only($data, ['first_name', 'last_name', 'title']);
        $updateData['email'] = $user->user_email;

        Agent::where('id', $agent->id)
            ->update($updateData);

        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        $agent = Agent::findOrFail($agentId);

        if (isset($data['telegram_chat_id'])) {
            $chatId = sanitize_text_field($data['telegram_chat_id']);
            $agent->updateMeta('telegram_chat_id', $chatId);
            $agent->telegram_chat_id = $chatId;
        }

        if (isset($data['slack_user_id'])) {
            $chatId = sanitize_text_field($data['slack_user_id']);
            $agent->updateMeta('slack_user_id', $chatId);
            $agent->slack_user_id = $chatId;
        }

        if (isset($data['whatsapp_number'])) {
            $whatsappNumber = sanitize_text_field($data['whatsapp_number']);
            $agent->updateMeta('whatsapp_number', $whatsappNumber);
            $agent->whatsapp_number = $whatsappNumber;
        }

        return [
            'message' => __('Support Staff has been updated', 'fluent-support'),
            'agent'   => $agent
        ];
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
