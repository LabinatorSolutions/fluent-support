<?php

namespace FluentSupport\App\Services\Tickets\Importer;

use FluentSupport\App\Models\Person;
use FluentSupport\App\Services\Helper;

class BaseImporter
{
	protected $db;
	protected $handler;
	protected $mailbox;
	protected $stats;
	protected $limit;

	public function __construct()
    {
        $this->db = Helper::FluentSupport('db');
        $this->mailbox = Helper::getDefaultMailBox();
        $this->limit = apply_filters('fluent_support/ticket_import_chunk_limit', 100);
    }


    /**
     * This `getPerson` method will get associated person with a tickets or conversation
     * @param int $userId
     * @param sting $type user type default is `customer`
     * @return object $person
     */
    protected function getPerson($userId = 0, $type = 'customer')
    {
        static $persons = [];

        if (isset($persons[$userId])) {
            $cachedPerson = $persons[$userId];
            if ($cachedPerson->person_type == $type) {
                return $persons[$userId];
            }
        }

        $person = Person::where('remote_uid', $userId)
            ->where('person_type', $type)
            ->first();
         
        if ($person) {
            $persons[$userId] = $person;
            return $persons[$userId];
        }
        if (!$person) {
        	$user = get_user_by('ID', $userId);

	        if (!$user) {
	            $persons[$userId] = false;
	            return $persons[$userId];
	        }

	        $personData = [
	            'email'       => $user->user_email,
	            'first_name'  => $user->first_name,
	            'last_name'   => $user->last_name,
	            'remote_uid'  => $userId,
	            'person_type' => $type,
	            'hash'        => md5(time() . wp_generate_uuid4())
	        ];

	        if (empty($personData['first_name'])) {
	            $personData['first_name'] = $user->display_name;
	        }

	        $person = Person::create($personData);

	        $persons[$userId] = $person;

	        return $persons[$userId];
        }

    }

    protected function fallbackAgentId()
    {
        $agent = Person::where('person_type', 'agent')
                ->oldest('id')
                ->select(['id'])
                ->first();

        return (int)$agent->id;
    }

}