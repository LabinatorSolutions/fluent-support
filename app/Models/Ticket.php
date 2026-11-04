<?php

namespace FluentSupport\App\Models;

use Exception;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Support\Arr;

/**
 * Display attributes set on a loaded ticket before it is sent:
 *
 * @property string     $human_date       "3 hours ago", from created_at.
 * @property mixed      $preview_response The latest response, for list rows.
 * @property array      $custom_fields    Pro custom field values.
 * @property array|null $created_by_agent Agent who opened the ticket for the customer.
 *
 * Column set outside $fillable:
 *
 * @property string     $content_hash     md5 of content; email piping uses it to spot a repeated email.
 */
class Ticket extends Model
{
    protected $table = 'fs_tickets';

    protected $dates = ['waiting_since'];

    /**
     * The hash is a bearer credential for the public ticket view, so keep it
     * out of API responses. Property access still works for signed URLs.
     *
     * @var array
     */
    protected $hidden = ['hash', 'content_hash'];

    protected $appends = ['display_ticket_number'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'agent_id',
        'product_id',
        'mailbox_id',
        'product_source',
        'privacy',
        'priority',
        'client_priority',
        'status',
        'title',
        'slug',
        'hash',
        'source',
        'message_id',
        'content',
        'last_agent_response',
        'last_customer_response',
        'waiting_since',
        'response_count',
        'first_response_time',
        'total_close_time',
        'resolved_at',
        'closed_by',
        'created_by',
        'serial_number',
        'ticket_number'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::slugify($model->title);
            }

            $model->hash = bin2hex(random_bytes(16));
            $model->content_hash = md5($model->content);

            $model->last_customer_response = current_time('mysql');
            $model->created_at = current_time('mysql');
            $model->updated_at = current_time('mysql');
            $model->waiting_since = current_time('mysql');

        });

        static::updating(function ($model) {
            // Rotate the hash on customer change so links already emailed to
            // the previous customer stop working.
            if ($model->isDirty('customer_id')) {
                $model->hash = bin2hex(random_bytes(16));
            }
        });

        static::created(function ($model) {
            if (empty($model->serial_number) || empty($model->ticket_number)) {
                $model->assignTicketNumber();
            }
        });

        static::deleting(function ($model) {
            Meta::where('object_type', 'ticket_meta')->where('object_id', $model->id)->delete();
            // CC info
            Meta::where('object_type', 'ticket')->where('object_id', $model->id)->delete();
            // Drafts
            Meta::where('object_type', '_fs_auto_draft')->where('object_id', $model->id)->delete();
            Notification::deleteByTicketId($model->id);
            // Response attachments are cleaned up by Conversation::deleting
            Conversation::deleteAll($model->id);
            // Ticket-level attachments, plus the ticket upload directory
            $class = __NAMESPACE__ . '\Attachment';
            $ticketAttachments = $class::where('ticket_id', $model->id)->whereNull('conversation_id')->get();
            $class::purgeAttachments($ticketAttachments, $model->id);
            $class::where('ticket_id', $model->id)->whereNull('conversation_id')->delete();
        });
    }

    /**
     * @var array
     */
    protected $searchable = [
        'content',
        'title',
        'slug',
        'id',
        'serial_number',
        'ticket_number'
    ];

    /**
     * Supports `column:value` to match a single fillable column.
     *
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */
    public function scopeSearchBy($query, $search)
    {

        if(!$search) {
            return $query;
        }

        if (strpos($search, ':')) {
            $array = explode(':', (string) $search);
            $column = $array[0];
            $value = $array[1];
            $columns = $this->fillable;
            $columns[] = 'id';

            if (in_array($column, $columns) && $value) {
                if (is_numeric($value)) {
                    $query->where($column, $value);
                } else {
                    $query->where($column, 'LIKE', "%$value%");
                }
                return $query;
            }
        }

        $fields = $this->searchable;
        $query->where(function ($query) use ($fields, $search) {
            $query->where(array_shift($fields), 'LIKE', "%$search%");
            foreach ($fields as $field) {
                $query->orWhere($field, 'LIKE', "%$search%");
            }
        });

        return $query;
    }

    /**
     * @deprecated Use TicketQuery::filterTickets() (the "tickets" advanced filter provider).
     */
    public function doSearchForAdvancedFilter($query, $search)
    {
        return \FluentSupport\App\Services\Tickets\TicketQuery::filterTickets($query, (array) $search);
    }

    /** Advanced-filter value for tickets with no recorded source (older tickets). */
    const SOURCE_NONE = '__none__';

    /**
     * Where tickets come from, for the advanced filter's Source field.
     *
     * @return array<string,string> source => label
     */
    public static function sourceFilterOptions()
    {
        return apply_filters('fluent_support/ticket_source_filter_options', [
            'web'                          => __('Web', 'fluent-support'),
            'email'                        => __('Email', 'fluent-support'),
            self::SOURCE_AGENT_INITIATED   => __('Agent outreach', 'fluent-support'),
            'mcp'                          => __('MCP (AI agents)', 'fluent-support'),
            'telegram'                     => __('Telegram', 'fluent-support'),
            'slack'                        => __('Slack', 'fluent-support'),
            'whatsapp'                     => __('WhatsApp', 'fluent-support'),
            'awesome-support'              => __('Imported: Awesome Support', 'fluent-support'),
            'freshdesk'                    => __('Imported: Freshdesk', 'fluent-support'),
            'helpscout'                    => __('Imported: Help Scout', 'fluent-support'),
            'js-helpdesk'                  => __('Imported: JS Help Desk', 'fluent-support'),
            'support-candy'                => __('Imported: SupportCandy', 'fluent-support'),
            'zendesk'                      => __('Imported: Zendesk', 'fluent-support'),
            self::SOURCE_NONE              => __('No source', 'fluent-support'),
        ]);
    }

    /**
     * @param ModelQueryBuilder $query
     * @param array $statuses
     * @return ModelQueryBuilder
     */
    public function scopeFilterByStatues($query, $statuses)
    {
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        return $query;
    }

    /**
     * Who replied last: 'agent', 'customer', or null when neither timestamp is
     * set. PHP counterpart of the timestamp check in scopeWaitingOnly(). A new
     * ticket resolves to 'customer' because boot() seeds last_customer_response.
     *
     * @return string|null
     */
    public function getLastReplyByAttribute()
    {
        $agentAt    = $this->last_agent_response;
        $customerAt = $this->last_customer_response;

        if (!$agentAt && !$customerAt) {
            return null;
        }
        if (!$agentAt) {
            return 'customer';
        }
        if (!$customerAt) {
            return 'agent';
        }

        // A tie resolves to 'customer'. scopeWaitingOnly() uses a strict <, so SQL treats a tie as answered.
        return strtotime($customerAt) >= strtotime($agentAt) ? 'customer' : 'agent';
    }

    /**
     * Tickets awaiting an agent reply.
     * @param $query
     * @return mixed
     */
    public function scopeWaitingOnly($query)
    {
        $query->where(function ($q) {
            $q->whereColumn('last_agent_response', '<', 'last_customer_response')
                ->orWhereNull('last_agent_response')
                ->orWhere('status', 'new');
        });
        return $query;
    }

    /**
     * @param ModelQueryBuilder $query
     * @param int $productId
     * @return ModelQueryBuilder
     */
    public function scopeFilterByProductId($query, $productId)
    {
        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query;
    }

    /**
     * @deprecated Use TicketQuery::filterPerson() (the "customer" and "agent" advanced filter providers).
     */
    public function filterTicketByUser($provider, $query, $filters)
    {
        return \FluentSupport\App\Services\Tickets\TicketQuery::filterPerson($provider, $query, (array) $filters);
    }

    /**
     * Newest first.
     *
     * @return Model Collection
     */
    public function responses()
    {
        $class = __NAMESPACE__ . '\Conversation';

        return $this->hasMany(
            $class, 'ticket_id', 'id'
        )->where('conversation_type', '!=', 'schedule')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /** source value for a ticket an agent opened to reach out to the customer. */
    const SOURCE_AGENT_INITIATED = 'agent_initiated';

    /**
     * Whether an agent opened this ticket to reach out. The message is then the
     * agent's first response, and the ticket body is only a placeholder.
     *
     * @return bool
     */
    public function isAgentInitiated()
    {
        if ($this->source === self::SOURCE_AGENT_INITIATED) {
            return true;
        }

        // Before 2.4.1 only the "initialized this ticket" placeholder body marked it.
        return $this->created_by && self::hasLegacyAgentInitiatedMarker($this->content);
    }

    /**
     * @param string $content
     * @return bool
     */
    public static function hasLegacyAgentInitiatedMarker($content)
    {
        $content = (string) $content;

        return strpos($content, ' initialized this ticket') !== false
            || strpos($content, __(' initialized this ticket', 'fluent-support')) !== false;
    }

    public function preview_response()
    {
        $class = __NAMESPACE__ . '\Conversation';

        return $this->hasOne(
            $class, 'ticket_id', 'id'
        )->where('conversation_type', '!=', 'schedule');
    }

    public function tags()
    {
        $class = __NAMESPACE__ . '\TicketTag';

        return $this->belongsToMany(
            $class, 'fs_tag_pivot', 'source_id', 'tag_id'
        )->wherePivot('source_type', 'ticket_tag');
    }

    public function watchers()
    {
        $class = __NAMESPACE__ . '\TagPivot';

        return $this->hasMany($class, 'source_id', 'id')
            ->where('source_type', 'ticket_watcher')
            ->select(['tag_id']);
    }

    /**
     * @return Model Collection
     */
    public function customer()
    {
        $class = __NAMESPACE__ . '\Customer';

        return $this->belongsTo(
            $class, 'customer_id', 'id'
        );
    }

    /**
     * @return Model Collection
     */
    public function agent()
    {
        $class = __NAMESPACE__ . '\Agent';

        return $this->belongsTo(
            $class, 'agent_id', 'id'
        );
    }

    public function closed_by_person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'closed_by', 'id'
        );
    }

    public function created_by_person()
    {
        $class = __NAMESPACE__ . '\Agent';

        return $this->belongsTo(
            $class, 'created_by', 'id'
        );
    }

    public function product()
    {
        $class = __NAMESPACE__ . '\Product';

        return $this->belongsTo(
            $class, 'product_id', 'id'
        );
    }

    public function mailbox()
    {
        $class = __NAMESPACE__ . '\MailBox';

        return $this->belongsTo(
            $class, 'mailbox_id', 'id'
        );
    }

    /**
     * @deprecated Use TicketService::deleteTicket(), which also fires ticket_deleted.
     */
    public function deleteTicket()
    {
        (new \FluentSupport\App\Services\Tickets\TicketService())->deleteTicket($this);
    }

    public static function getNextSerialNumber()
    {
        $businessSettings = Helper::getOption('global_business_settings', []);
        $minNumber = (int) ($businessSettings['min_serial_number'] ?? 1);
        $minNumber = (int) apply_filters('fluent_support/min_serial_number', $minNumber);

        try {
            $lastTicketNumber = self::query()->max('serial_number');
        } catch (\Exception $e) {
            $lastTicketNumber = null;
        }

        $nextNumber = ((int) $lastTicketNumber) + 1;

        return max($nextNumber, $minNumber);
    }

    public static function isMinimumSerialNumberEnabled()
    {
        $businessSettings = Helper::getOption('global_business_settings', []);
        return ($businessSettings['enable_min_serial_number'] ?? 'no') === 'yes';
    }

    public static function getTicketPrefix($ticket = null)
    {
        $businessSettings = Helper::getOption('global_business_settings', []);
        $prefix = self::isMinimumSerialNumberEnabled() ? trim((string) ($businessSettings['ticket_prefix'] ?? '')) : '';

        $productId = $ticket ? $ticket->product_id : null;

        return apply_filters('fluent_support/ticket_prefix', $prefix, $ticket, $productId);
    }

    public function getDisplayTicketNumberAttribute()
    {
        return $this->ticket_number ?: ($this->serial_number ?: $this->id);
    }

    public function scopeWherePublicIdentifier($query, $identifier)
    {
        return $query->where('serial_number', $identifier);
    }

    protected function assignTicketNumber()
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $nextNumber = $this->serial_number ?: (self::isMinimumSerialNumberEnabled() ? self::getNextSerialNumber() : $this->id);
            $ticketNumber = $this->ticket_number ?: (self::getTicketPrefix($this) . $nextNumber);

            try {
                self::where('id', $this->id)->update([
                    'serial_number' => $nextNumber,
                    'ticket_number' => $ticketNumber
                ]);
                $this->serial_number = $nextNumber;
                $this->ticket_number = $ticketNumber;

                return $nextNumber;
            } catch (\Exception $e) {
                if (stripos($e->getMessage(), 'duplicate') === false) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a unique ticket number.');
    }

    public static function slugify($title)
    {
        $slug = sanitize_title($title, 'support-ticket-' . time(), 'display');
        if (Ticket::where('slug', $slug)->first()) {
            $slug .= '-' . time();
        }
        return $slug;
    }

    public function hasTag($tagId)
    {
        $tags = $this->tags;
        foreach ($tags as $tag) {
            if ($tag->id == $tagId) {
                return true;
            }
        }

        return false;
    }

    public function attachments()
    {
        $class = __NAMESPACE__ . '\Attachment';
        return $this->hasMany($class, 'ticket_id', 'id')->where('conversation_id', NULL);
    }

    public function customData($scope = 'admin', $rendered = false)
    {
        // Pro defines the fields; without it there are none.
        $fields = apply_filters('fluent_support/custom_field_labels', [], $scope);

        if (!$fields) {
            return [];
        }

        $keys = array_keys($fields);

        $customRows = Meta::where('object_type', 'ticket_meta')->where('object_id', $this->id)
            ->whereIn('key', $keys)
            ->get();

        if (!$customRows) {
            return [];
        }

        $formattedData = [];

        $customRenderers = apply_filters('fluent_support/custom_field_renders_type', []);

        foreach ($customRows as $row) {
            $dataKey = $row->key;

            $value = $row->value;

            $fieldType = $fields[$dataKey]['type'];

            if ($value) {
                if (in_array($fieldType, $customRenderers) && $rendered) {
                    $value = apply_filters('fluent_support/custom_field_render_' . $fieldType, $value, $scope);
                } else if (in_array($fieldType, ['checkbox', 'date-range'])) {
                    $value = array_values(array_filter(explode('|', $value)));
                }

                if (!is_array($value) && !is_object($value)) {
                    $formattedData[$dataKey] = links_add_target(make_clickable($value));
                } else {
                    $formattedData[$dataKey] = $value;
                }
            }
        }

        return $formattedData;
    }

    /**
     * @param array $data Custom field values keyed by field key.
     * @return bool
     */
    public function syncCustomFields($data)
    {
        if (!is_array($data)) {
            return false;
        }

        $fields = apply_filters('fluent_support/ticket_custom_fields', []);

        if (!$fields) {
            return false;
        }

        $keys = array_keys($fields);

        $validData = Arr::only($data, $keys);

        foreach ($validData as $dataKey => $validDatum) {
            if (empty($validDatum)) {
                Meta::where('object_type', 'ticket_meta')
                    ->where('object_id', $this->id)
                    ->where('key', $dataKey)
                    ->delete();
                continue;
            }

            if ($fields[$dataKey]['type'] == 'checkbox' || is_array($validDatum)) {
                $validDatum = implode('|', $validDatum);
                $validDatum = '|' . $validDatum . '|';
            }

            $exist = Meta::where('object_type', 'ticket_meta')
                ->where('object_id', $this->id)
                ->where('key', $dataKey)
                ->first();

            if ($exist) {
                $exist->value = $validDatum;
                $exist->save();
            } else {
                Meta::insert([
                    'object_type' => 'ticket_meta',
                    'object_id'   => $this->id,
                    'key'         => $dataKey,
                    'value'       => $validDatum
                ]);
            }
        }

        return true;
    }

    public function getLastAgentResponse()
    {
        $query = \FluentSupport\App\App::db()->table('fs_conversations')
            ->select(['fs_conversations.*'])
            ->where('fs_conversations.conversation_type', 'response')
            ->where('fs_conversations.ticket_id', $this->id)
            ->where('fs_persons.person_type', '=', 'agent')
            ->join('fs_persons', 'fs_persons.id', '=', 'fs_conversations.person_id')
            ->orderBy('fs_conversations.id', 'DESC');

        return $query->first();
    }

    public function getLastResponse()
    {
        return \FluentSupport\App\App::db()->table('fs_conversations')
            ->where('ticket_id', $this->id)
            ->where('conversation_type', 'response')
            ->latest('id')
            ->first();
    }

    /**
     * @deprecated Use TicketService::addTags().
     * @param int|array $tagIds
     * @return bool True if any tag was added.
     */
    public function applyTags($tagIds)
    {
        return (bool) (new \FluentSupport\App\Services\Tickets\TicketService())->addTags($this, $tagIds);
    }

    /**
     * @deprecated Use TicketService::removeTags().
     * @param int|array $tagIds
     * @return bool True if any tag was removed.
     */
    public function detachTags($tagIds)
    {
        return (bool) (new \FluentSupport\App\Services\Tickets\TicketService())->removeTags($this, $tagIds);
    }

    /**
     * @deprecated Use TicketService::storeTicket() instead.
     */
    public function createTicket($ticketData, $maybeNewCustomer = false)
    {
        _deprecated_function(__METHOD__, '2.0.5', 'TicketService::storeTicket()');

        if (empty($ticketData['customer_id']) && $maybeNewCustomer) {
            $email = Arr::get($maybeNewCustomer, 'email');
            if (!$email || !is_email($email)) {
                return new \WP_Error('error', 'A valid email is required to create a ticket');
            }

            $customer = \FluentSupport\App\Modules\Customers\CustomerService::findOrCreate(Arr::only($maybeNewCustomer, (new Customer())->getFillable()));
            if (!$customer) {
                return new \WP_Error('error', 'Customer could not be created');
            }
            $ticketData['customer_id'] = $customer->id;
        }

        if (empty($ticketData['customer_id'])) {
            return new \WP_Error('error', 'Ticket could not be created');
        }

        $customer = Customer::findOrFail($ticketData['customer_id']);

        return (new TicketService())->storeTicket($ticketData, $customer);
    }

    public function getSettingsValue($valueKey = false, $default = false)
    {
        $exist = Meta::where('object_type', 'ticket')
            ->where('key', 'settings')
            ->where('object_id', $this->id)
            ->first();

        if ($exist) {
            $value = Helper::safeUnserialize($exist->value);
            if ($valueKey) {
                if (!is_array($value)) {
                    return $default;
                }
                return Arr::get($value, $valueKey, $default);
            }
            return $value;
        }

        return $default;
    }

    public function updateSettingsValue($valueKey, $value)
    {
        $exist = Meta::where('object_type', 'ticket')
            ->where('key', 'settings')
            ->where('object_id', $this->id)
            ->first();

        if ($exist) {
            $existingValue = Helper::safeUnserialize($exist->value);

            if (!is_array($existingValue)) {
                $existingValue = [];
            }

            $existingValue[$valueKey] = $value;

            $exist->value = maybe_serialize($existingValue);
            $exist->save();
            return $this;
        }

        $settings = [
            'object_type' => 'ticket',
            'key'         => 'settings',
            'object_id'   => $this->id,
            'value'       => maybe_serialize([
                $valueKey => $value
            ])
        ];

        Meta::create($settings);

        return $this;

    }

}
