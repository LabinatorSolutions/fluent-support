<?php

namespace FluentSupport\App\Models\Traits;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Models\Person;
use FluentSupport\Framework\Support\Arr;
use Exception;

trait AgentTrait
{
	public function getAgents($search)
    {
        $agents = static::latest()
            ->searchBy($search)
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

        return $agents;
    }

    public function createAgent(array $data) 
    {
    	
        $user = get_user_by('email', $data['email']);

        if (!$user) {
        	throw new Exception('Sorry, Connected user could not be found with the provided email address');
        }


        $data = $this->setAgentInfo($data, $user);

        // check if another agent has same email address
        $person = Person::where('email', $data['email'])->first();
        
        if ($person) {
            throw new Exception('Sorry, Another agent/person exist with the same email address. Please use a different email address');
        }


        return $this->setAgentMeta(
        	$user,
        	$data,
        	static::create($data)
        );

    }

    private function setAgentInfo(array $data, $user)
    {
    	$data['user_id'] = $user->ID;

    	if (empty($data['first_name'])) {
            $data['first_name'] = $user->first_name;
        }

        if (empty($data['last_name'])) {
            $data['last_name'] = $user->last_name;
        }

        return $data;
    }

    private function setAgentMeta($user, $data, $agent)
    {
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

        return $agent;
    }
}