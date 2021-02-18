<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;

class Helper
{
    public static function getAgentByUserId($userId)
    {
        if(!$userId) {
            return false;
        }
        
        return Agent::where('user_id', $userId)->first();
    }
}
