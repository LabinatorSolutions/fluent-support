<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\App\Models\Customer;
use FluentSupport\Framework\Request\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::orderBy('id', 'DESC')
            ->orderBy($request->get('order_by', 'id'), $request->get('order_type', 'ASC'))
            ->searchBy($request->get('search'))
            ->paginate();

        foreach ($customers as $customer) {
            $customer->total_tickets = $customer->getTicketCounts();
            $customer->total_responses = $customer->getResponseCounts();
            if($customer->user_id) {
                $customer->user_profile = admin_url('user-edit.php?user_id='.$customer->user_id);
            }
        }

        return [
            'customers' => $customers,
        ];
    }

    public function addAgent(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);

        $email = $data['email'];

        $user = get_user_by('email', $email);

        if(!$user || is_wp_error($user)) {
            return $this->sendError([
                'message' => __('Sorry, Connected user could not be found with the provided email address', 'fluent-support')
            ]);
        }

        // check if another agent has same email address
        $exist = Person::where('email', $email)->first();
        if($exist) {
            return $this->sendError([
                'message' => __('Sorry, Another agent/person exist with the same email address. Please use a different email address', 'fluent-support')
            ]);
        }

        $data['user_id'] = $user->ID;
        $agent = Agent::create($data);
        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        return [
            'message' => __('Support Staff has been added', 'fluent-support'),
            'agent' => $agent
        ];
    }

    public function updateAgent(Request $request, $agentId)
    {
        $agent = Agent::findOrFail($agentId);
        $data = $request->all();
        $this->validate($data, [
            'email' => 'required|email',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);

        $user = get_user_by('ID', $agent->user_id);

        if(!$user || is_wp_error($user)) {
            return $this->sendError([
                'message' => __('Sorry, Connected user could not be found with the provided email address', 'fluent-support')
            ]);
        }

        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        $updateData = Arr::only($data, ['first_name', 'last_name']);
        $updateData['email'] = $user->user_email;

        Agent::where('id', $agent->id)
            ->update($updateData);

        PermissionManager::attachPermissions($user, Arr::get($data, 'permissions', []));

        return [
            'message' => __('Support Staff has been updated', 'fluent-support'),
            'agent' => Agent::findOrFail($agentId)
        ];
    }

    public function deleteAgent(Request $request, $agentId)
    {
        $fallBackAgentId = $request->get('fallback_agent_id');

        if($fallBackAgentId == $agentId) {
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

        Response::where('person_id', $agentId)->update([
            'person_id' => $newAgent->id
        ]);

        Product::where('created_by', $agentId)->update([
            'created_by' => $newAgent->id
        ]);

        Ticket::where('agent_id', $agentId)->update([
            'agent_id' => $newAgent->id
        ]);

        Agent::where('id', $agentId)->delete();

        return [
            'message' => __('Selected support staff has been deleted', 'fluent-support')
        ];

    }
}
