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
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Framework\Support\Arr;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::orderBy('id', 'DESC')
            //->whereNotNull('user_id')
            ->paginate();

        foreach ($agents as $agent) {
            $agent->permissions = PermissionManager::getUserPermissions($agent->user_id);
            if ($agent->user_id) {
                $agent->user_profile = admin_url('user-edit.php?user_id=' . $agent->user_id);
            }
            $agent->replies_count = Conversation::where('person_id', $agent->id)->count();
            $agent->telegram_chat_id = $agent->getMeta('telegram_chat_id');
        }

        return [
            'agents'      => $agents,
            'permissions' => PermissionManager::pluginPermissions()
        ];
    }

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

        if(!empty($data['telegram_chat_id'])) {
            $chatId = sanitize_text_field($data['telegram_chat_id']);
            $agent->updateMeta('telegram_chat_id', $chatId);
            $agent->telegram_chat_id = $chatId;
        }

        return [
            'message' => __('Support Staff has been added', 'fluent-support'),
            'agent'   => $agent
        ];
    }

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

        if(isset($data['telegram_chat_id'])) {
            $chatId = sanitize_text_field($data['telegram_chat_id']);
            $agent->updateMeta('telegram_chat_id', $chatId);
            $agent->telegram_chat_id = $chatId;
        }

        return [
            'message' => __('Support Staff has been updated', 'fluent-support'),
            'agent'   => $agent
        ];
    }

    public function deleteAgent(Request $request, $agentId)
    {
        $fallBackAgentId = $request->get('fallback_agent_id');

        if ($fallBackAgentId == $agentId) {
            return $this->sendError([
                'message' => 'Old Agent and New agent is same person'
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

    public function myStats(Request $request)
    {
        $agent = Helper::getAgentByUserId();
        return $this->getAgentStat($request, $agent->id);
    }

    public function getAgentStat(Request $request, $agentId)
    {
        $agent = Agent::findOrFail($agentId);
        $stats = StatModule::getAgentStat($agentId);

        if (PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            $stats['unassigned_tickets'] = [
                'title' => 'Unassigned Tickets',
                'count' => Ticket::whereNull('agent_id')->where('status', '!=', 'closed')->count()
            ];
        }

        $data = [
            'stats' => $stats
        ];

        $with = $request->get('with', []);

        if (in_array('suggested_tickets', $with)) {
            $data['suggested_tickets'] = TicketHelper::getSuggestedTickets($agent->id);
        }

        if(in_array('overall_stats', $with)) {
            $data['overall_stats'] = (new Reporting())->getActiveStats();
        }

        if(in_array('individual_stat', $with)) {
            $data['individual_stat'] = (new Reporting())->getActiveStatByAgent($agent->id);
        }

        return $data;
    }
}
