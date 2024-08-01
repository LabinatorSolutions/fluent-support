<?php

namespace FluentSupport\App\Models;

class AIActivityLogs extends Model
{
    protected $table = 'fs_ai_activity_logs';

    protected $fillable = ['agent_id', 'ticket_id', 'model_name', 'used_tokens', 'prompt'];
}
