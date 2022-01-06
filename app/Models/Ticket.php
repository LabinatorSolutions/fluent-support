<?php

namespace FluentSupport\App\Models;

use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class Ticket extends Model
{
    protected $table = 'fs_tickets';

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
        'closed_by'
    ];

    public static function boot()
    {
        static::creating(function ($model) {
            $model->slug = static::slugify($model->title);
            $model->hash = substr(md5(time() . wp_generate_uuid4()), 0, 8) . mt_rand(1, 99);
            $model->last_customer_response = current_time('mysql');
            $model->content_hash = md5($model->content);
            $model->created_at = current_time('mysql');
            $model->updated_at = current_time('mysql');
            $model->waiting_since = current_time('mysql');
        });
    }

    /**
     * $searchable Columns in table to search
     * @var array
     */
    protected $searchable = [
        'title',
        'slug',
        'content',
        'id',
        'email',
        'first_name',
        'last_name',
        'country',
        'city',
        'zip',
        'state',
        'country',
        'note',
        'address_line_1',
        'address_line_2'
    ];

    /**
     * Local scope to filter subscribers by search/query string
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */
    public function scopeSearchBy($query, $search, $provider=false)
    {
        if ($search && !$provider) {

            if (strpos($search, ':')) {
                $array = explode(':', $search);
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
                    $query->orWhere($field, 'LIKE', "$search%");
                }
            });
        }else{
            foreach ($search as $s) {
                $operator = $s['operator'];
                if ($operator == 'contains') {
                    $query = $query->where(function ($query) use ($s) {
                        $query->where($s['property'], 'LIKE', "%".$s['value']."%");
                    });
                } elseif ($operator == 'not_contains') {
                    $query = $query->where(function ($query) use ($s){
                        $query->where($s['property'], 'NOT LIKE', '%'.$s['value'].'%');
                    });
                }
            }
        }

        return $query;
    }

    /**
     * Local scope to filter subscribers by search/query string
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

    public function scopeWaitingOnly($query)
    {
        global $wpdb;
        $query->where(function ($q) use ($wpdb) {
            $q->whereRaw($wpdb->prefix . 'fs_tickets.last_agent_response < ' . $wpdb->prefix . 'fs_tickets.last_customer_response')
                ->orWhereNull('last_agent_response')
                ->orWhere('status', 'new');
        });
        return $query;
    }

    public function scopeApplyFilters($query, $filters)
    {
        $supportedColumns = ['product_id', 'client_priority', 'priority', 'mailbox_id'];
        foreach ($filters as $filterKey => $filterValue) {
            if (!$filterValue && ($filterValue !== '0' || $filterValue !== 0)) {
                continue;
            }
            if ($filterKey == 'status_type') {
                $statusArray = Helper::getTkStatusesByGroupName($filterValue);
                if ($statusArray) {
                    $query->whereIn('status', $statusArray);
                }
            } else if (in_array($filterKey, $supportedColumns)) {
                $query->where($filterKey, $filterValue);
            } else if ($filterKey == 'waiting_for_reply') {
                if ($filterValue != 'yes') {
                    continue;
                }
                $query = $this->scopeWaitingOnly($query);
            } else if ($filterKey == 'agent_id') {
                if ($filterValue == 'unassigned') {
                    $query->whereNull($filterKey);
                } else {
                    $query->where($filterKey, $filterValue);
                }
            } else if ($filterKey == 'ticket_tags') {
                if (!$filterValue) {
                    continue;
                }
                $query->whereHas('tags', function ($q) use ($filterValue) {
                    $q->whereIn('tag_id', $filterValue);
                });
            }
        }

        return $query;
    }

    /**
     * Local scope to filter subscribers by search/query string
     * @param ModelQueryBuilder $query
     * @param array $statuses
     * @return ModelQueryBuilder
     */
    public function scopeFilterByAgentId($query, $agentId)
    {
        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query;
    }

    /**
     * Local scope to filter subscribers by search/query string
     * @param ModelQueryBuilder $query
     * @param int $customerId
     * @return ModelQueryBuilder
     */
    public function scopeFilterByCustomerId($query, $customerId)
    {
        $query->where('customer_id', $customerId);

        return $query;
    }

    /**
     * Local scope to filter subscribers by search/query string
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
     * Local scope to filter subscribers by search/query string
     * @param ModelQueryBuilder $query
     * @param array $priorities
     * @return ModelQueryBuilder
     */
    public function scopeFilterByPriorities($query, $priorities)
    {
        if ($priorities) {
            $query->whereIn('priority', $priorities);
        }

        return $query;
    }

    /**
     * @param $filter
     * @return string[]
     */

    public static function parseRelationalFilterQueryMethods($filter)
    {
        // default operator = in
        $method = 'whereHas';
        $subMethod = 'whereIn';

        switch ($filter['operator']) {
            case 'not_in':
                $method = 'whereDoesntHave';
                $subMethod = 'whereIn';

                break;
            case 'in_all':
                $method = 'whereHas';
                $subMethod = 'where';

                break;
            case 'not_in_all':
                $method = 'whereDoesntHave';
                $subMethod = 'where';

                break;
        }

        return [$method, $subMethod];
    }

    /**
     * Relation builder
     * @param $relation
     * @param $query
     * @param $method
     * @param $subMethod
     * @param $subField
     * @param $filter
     * @param false $provider
     * @return mixed
     */

    public static function buildRelationFilterQuery($relation, $query, $method, $subMethod, $subField, $filter, $provider = false)
    {
        if (in_array($filter['operator'], ['in_all', 'not_in_all'])) {
            foreach ($filter['value'] as $item) {
                $query = static::buildRelationFilterQuery($relation, $query, $method, $subMethod, $subField, ['value' => $item, 'operator' => ''], $provider);
            }
        } else {
            $query = $query->{$method}($relation, function ($relationQuery) use ($subMethod, $subField, $filter, $provider) {
                $relationQuery = $relationQuery->{$subMethod}($subField, $filter['value']);

                if ($provider) {
                    $relationQuery = $relationQuery->where('provider', $provider);
                }

                return $relationQuery;
            });
        }

        return $query;
    }

    /**
     * get tickets by advanced filter segment data
     * @param $query
     * @param $filters
     * @return ModelQueryBuilder
     */

    public function buildSegmentFilterQuery($query, $filters)
    {
        foreach ($filters as $filter) {
            if (in_array($filter['property'], ['tags', 'product'])) {
                $subField = $filter['property'] == 'tags' ? 'tag_id' : 'product_id';
                list($method, $subMethod) = static::parseRelationalFilterQueryMethods($filter);
                $query = static::buildRelationFilterQuery($filter['property'], $query, $method, $subMethod, $subField, $filter);
            } else {
                $method = $filter['operator'] == 'in' ? 'whereIn' : 'whereNotIn';

                $query = $query->{$method}($filter['property'], (array) $filter['value']);
            }
        }

        return $query;
    }

    /**
     * method to search by properties
     * @param $provider
     * @param $query
     * @param $search
     * @param string $operator
     * @return mixed
     */
    public function buildSearchableQuery($provider, $query, $search, $operator = 'LIKE')
    {
        $fields = $this->searchable;

        $query->whereHas($provider, function ($query) use ($fields, $search, $operator) {
            $query->where(array_shift($fields), $operator, $search);

            $nameArray = explode(' ', $search);

            if (count($nameArray) >= 2) {
                $query->orWhere(function ($q) use ($nameArray, $operator) {
                    $firstName = array_shift($nameArray);
                    $lastName = implode(' ', $nameArray);

                    $q->where('first_name', $operator, $firstName);
                    $q->where('last_name', $operator, $lastName);
                });
            }

            foreach ($fields as $field) {
                $query->orWhere($field, $operator, $search);
            }
        });

        return $query;
    }

    /**
     * Filter by ticket general properties like customer name, agent name etc
     * @param $query
     * @param $filters
     */

    public function filterTicketByProperties($provider, $query, $filters)
    {
        foreach ($filters as $index=>$filter) {
            if ($filter['operator']=='in' || $filter['operator']=='not_in') {
                $method = $filter['operator'] == 'in' ? 'whereIn' : 'whereNotIn';
                $query = $query->whereHas($provider, function ($q) use ($method, $filter) {
                        $q->{$method}($filter['property'], $filter['value']);
                });
            }
            elseif ($filter['operator'] == 'contains') {
                $operator = 'LIKE';
                $searchTerm = '%' . $filter['value'] . '%';
                $query = $this->buildSearchableQuery($provider, $query, $searchTerm, $operator);
            } elseif ($filter['operator'] == 'not_contains') {
                $operator = 'NOT LIKE';
                $searchTerm = '%' . $filter['value'] . '%';
                $query = $this->buildSearchableQuery($provider, $query, $searchTerm, $operator);
            }

            if ($filter['operator'] == '=') {
                $query->whereHas($provider, function ($q) use ($index, $filter) {
                    if ($index == 0){
                        $q->where($filter['property'], '=', $filter['value']);
                    }else{
                        $q->orWhere($filter['property'], '=', $filter['value']);
                    }
                });
            } elseif ($filter['operator'] == '!=') {
                    $query->whereHas($provider, function ($q) use ($index, $filter) {
                        if ($index == 0){
                            $q->where($filter['property'], '!=', $filter['value']);
                        }else{
                            $q->orWhere($filter['property'], '!=', $filter['value']);
                        }
                    });

            }
            return $query;
        }
    }
    /**
     * One2Many: Customer has to many Click Tickets
     * @return Model Collection
     */
    public function responses()
    {
        $class = __NAMESPACE__ . '\Conversation';

        return $this->hasMany(
            $class, 'ticket_id', 'id'
        );
    }

    public function preview_response()
    {
        $class = __NAMESPACE__ . '\Conversation';

        return $this->hasOne(
            $class, 'ticket_id', 'id'
        );
    }

    public function tags()
    {
        $class = __NAMESPACE__ . '\TicketTag';

        return $this->belongsToMany(
            $class, 'fs_tag_pivot', 'source_id', 'tag_id'
        )->wherePivot('source_type', 'ticket_tag');
    }


    /**
     * One2one: Customer has to many Click Tickets
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
     * One2one: Customer has to many Click Tickets
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


    public function deleteTicket()
    {
        do_action('fluent_support/deleting_ticket', $this);
        // delete the responses first
        Conversation::where('ticket_id', $this->id)->delete();
        // Delete the ticket meta
        Meta::where('object_type', 'ticket_meta')->where('object_id', $this->id)->delete();

        Ticket::where('id', $this->id)->delete();
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
        if (!defined('FLUENTSUPPORTPRO')) {
            return [];
        }

        $fields = \FluentSupportPro\App\Services\CustomFieldsService::getFieldLabels($scope);

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

        $customRenderers = \FluentSupportPro\App\Services\CustomFieldsService::getCustomerRenderers();

        foreach ($customRows as $row) {
            $dataKey = $row->key;

            $value = $row->value;

            $fieldType = $fields[$dataKey]['type'];

            if ($value) {
                if (in_array($fieldType, $customRenderers) && $rendered) {
                    $value = apply_filters('fluent_support/custom_field_render_' . $fieldType, $value, $scope);
                } else if ($fieldType == 'checkbox') {
                    $value = array_values(array_filter(explode('|', $value)));
                }
            }

            $formattedData[$dataKey] = $value;
        }

        return $formattedData;
    }

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
            if ($fields[$dataKey]['type'] == 'checkbox' || is_array($validDatum)) {
                $validDatum = implode('|', $validDatum);
                $validDatum = '|' . $validDatum . '|';
            }

            $exist = Meta::where('object_type', 'ticket_meta')->where('object_id', $this->id)
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

        // maybe delete data
        $deletedSlugs = array_diff($keys, array_keys($validData));

        if ($deletedSlugs) {
            Meta::where('object_type', 'ticket_meta')->where('object_id', $this->id)
                ->whereIn('key', $deletedSlugs)
                ->delete();
        }

        return true;
    }

    public function getLastAgentResponse()
    {
        return \FluentSupport\App\App::db()->table('fs_conversations')
            ->select(['fs_conversations.*'])
            ->where('fs_conversations.conversation_type', 'response')
            ->where('fs_conversations.ticket_id', $this->id)
            ->join('fs_persons', 'fs_persons.person_type', '=', 'agent')
            ->orderBy('fs_conversations.id', 'DESC')
            ->first();
    }

    public function getLastResponse()
    {
        return \FluentSupport\App\App::db()->table('fs_conversations')
            ->where('ticket_id', $this->id)
            ->where('conversation_type', 'response')
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function applyTags($tagIds)
    {
        $result = false;
        foreach ($tagIds as $tagId) {
            if (!$this->hasTag($tagId)) {
                $this->tags()->attach($tagId, ['source_type' => 'ticket_tag']);
                $result = true;
                do_action('fluent_support/ticket_tag_added', $tagId, $this);
            }
        }
        return $result;
    }

    public function detachTags($tagIds)
    {
        $result = false;
        foreach ($tagIds as $tagId) {
            if ($this->hasTag($tagId)) {
                $this->tags()->detach($tagId);
                do_action('fluent_support/ticket_tag_removed', $tagId, $this);
                $result = true;
            }
        }
        return $result;
    }
}
