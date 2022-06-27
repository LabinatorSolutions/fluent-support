<?php

namespace FluentSupport\App\Models;

use Exception;
use FluentSupport\App\Http\Controllers\AuthController;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\TicketHelper;
use FluentSupport\App\Services\TicketQueryService;
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
        'content',
        'title',
        'slug',
        'id'
    ];

    /**
     * Local scope to filter tickets by search/query string
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */
    public function scopeSearchBy($query, $search)
    {
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
                $query->orWhere($field, 'LIKE', "%$search%");
            }
        });

        return $query;
    }

    /**
     * Local scope to filter tickets by different filtering condition
     * @param ModelQueryBuilder $query
     * @param mixed $search
     * @return ModelQueryBuilder
     */

    public function doSearchForAdvancedFilter($query, $search)
    {
        foreach ($search as $s) {
            $operator = $s['operator'];
            //If selected item for ticket either title or content
            if(in_array($s['property'], ['title','content' ])){
                //If the selected condition is contains, query operator id LIKE
                if ($operator == 'contains') {
                    $query = $query->where(function ($query) use ($s) {
                        $query->where($s['property'], 'LIKE', "%".$s['value']."%");
                    });
                } elseif ($operator == 'not_contains') {
                    //If the selected condition is not_contains, query operator id NOT LIKE
                    $query = $query->where(function ($query) use ($s){
                        $query->where($s['property'], 'NOT LIKE', '%'.$s['value'].'%');
                    });
                }
            }

            //If selected item is Ticket Conversation Content
            if($s['property'] == 'conversation_content'){
                $operator = $s['operator'];
                if($operator == 'contains') {
                    $query = $query->whereHas('responses', function ($q) use ($s) {
                        $q->where('content', 'LIKE', "%".$s['value']."%");
                    });

                } else if ($operator == 'not_contains') {
                    $query = $query->whereHas('responses', function ($q) use ($s) {
                        $q->where('content', 'NOT LIKE', "%".$s['value']."%");
                    });
                }
            }

            //If selected item is Ticket created or Last Response or Customer Waiting For, or Last Agent Response or Last Customer Response
            if(in_array($s['property'], ['created_at', 'updated_at', 'waiting_since', 'last_agent_response', 'last_customer_response'])){
                $query = (new \FluentSupport\App\Models\Ticket())->buildDateBaseFilterQuery($query, $s);
            }

            //If selected item is Ticket Status or Client Priority or Agent Priority or Tags or Product or Waiting For Reply
            if(in_array($s['property'], ['status', 'client_priority', 'priority', 'tags', 'product', 'waiting_for_reply', 'agent_id', 'mailbox_id'])){
                $query = (new \FluentSupport\App\Models\Ticket())->buildPropertiesFilterQuery($query, $s);
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

    /**
     * Local scope to filter tickets by not response by agent
     * @param $query
     * @return mixed
     */
    public function scopeWaitingOnly($query)
    {
        $query->where(function ($q){
            $q->whereColumn('last_agent_response', '<' ,'last_customer_response')
                ->orWhereNull('last_agent_response')
                ->orWhere('status', 'new');
        });
        return $query;
    }

    /**
     * scopeApplyFilters method will filet ticket based on the selected filters
     * This method will get filter option as parameter, loop through and apply conditions in query
     * @param $query
     * @param $filters
     * @return ModelQueryBuilder
     */
    public function scopeApplyFilters($query, $filters)
    {
        $supportedColumns = ['product_id', 'client_priority', 'priority', 'mailbox_id'];
        foreach ($filters as $filterKey => $filterValue) {
            if (!$filterValue && ($filterValue !== '0' || $filterValue !== 0)) {
                continue;
            }
            //If filer using status
            if ($filterKey == 'status_type') {
                //Get list of ticket status
                $statusArray = Helper::getTkStatusesByGroupName($filterValue);
                if ($statusArray) {
                    //Apply filet where status in
                    $query->whereIn('status', $statusArray);
                }
            } else if (in_array($filterKey, $supportedColumns)) {
                $query->where($filterKey, $filterValue);
            } else if ($filterKey == 'waiting_for_reply') {
                if ($filterValue != 'yes') {
                    continue;
                }
                //Apply filter where no response by agent
                $query = $this->scopeWaitingOnly($query);
            } else if ($filterKey == 'agent_id') {
                //Apply filter where ticket is not assigned
                if ($filterValue == 'unassigned') {
                    $query->whereNull($filterKey);
                }
                else {
                    if(defined('FLUENTSUPPORTPRO')) {
                        if (isset($filters['watcher']) && $filters['watcher'] == 'watcher'){
                            $watcherTickets = TicketHelper::getWatcherTicketIds($filterValue);
                            $query->whereIn('id', $watcherTickets);
                        }else {
                            //Apply filter, get only assigned ticket
                            $query->where($filterKey, $filterValue);
                        }
                    }
                }
            }
            else if ($filterKey == 'ticket_tags') {
                if (!$filterValue) {
                    continue;
                }
                //Apply filter where ticket only has this tag id
                $query->whereHas('tags', function ($q) use ($filterValue) {
                    $q->whereIn('tag_id', $filterValue);
                });
            }
        }

        return $query;
    }

    /**
     * Local scope to filter tickets by agent id
     * @param ModelQueryBuilder $query
     * @param int $agentId
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
     * Parse filter to set proper operator and value for the filter query.
     *
     * @param array $filter
     * @return array
     */
    public static function filterParser($filter)
    {
        switch ($filter['operator']) {
            case 'before':
                $filter['operator'] = '<';
                $filter['value'] = $filter['value'] . ' 23:59:59';
                break;

            case 'after':
                $filter['operator'] = '>';
                $filter['value'] = $filter['value'] . ' 23:59:59';
                break;

            case 'date_equal':
                $filter['operator'] = 'LIKE';
                $filter['value'] = '%' . $filter['value'] . '%';
                break;

            case 'days_before':
                $filter['operator'] = '<';
                $filter['value'] = date('Y-m-d', current_time('timestamp') - $filter['value'] * 24 * 60 * 60);
                break;

            case 'days_within':
                $filter['operator'] = 'BETWEEN';
                $filter['value'] = [
                    date('Y-m-d', current_time('timestamp') - $filter['value'] * 24 * 60 * 60),
                    date('Y-m-d') . ' 23:59:59'
                ];
                break;
            case 'date_range':
                $filter['operator'] = 'BETWEEN';
                if(isset($filter['value'][0]))
                    $filter['value'][0] .=  ' 00:00:00';
                if(isset($filter['value'][1]))
                    $filter['value'][1] .= ' 23:59:59';
                break;
        }

        return $filter;
    }

    /**
     * @param \FluentSupport\Framework\Database\Orm\Builder|\FluentSupport\Framework\Database\Query\Builder $query
     * @param array $filters
     * @return ModelQueryBuilder
     */
    public function buildDateBaseFilterQuery($query, $filters)
    {
        $filter = static::filterParser($filters);
        $query->where(function ($dateQuery) use ($filter) {

            if ($filter['operator'] == 'BETWEEN') {
                $dateQuery->whereBetween($filter['property'], $filter['value']);
            } else {
                $dateQuery->where($filter['property'], $filter['operator'], $filter['value']);
            }
        });

        return $query;
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
     * @return ModelQueryBuilder
     */

    public static function buildRelationFilterQuery($relation, $query, $method, $subMethod, $subField, $filter, $provider = false)
    {
        if (in_array($filter['operator'], ['in_all', 'not_in_all']) && $filter['value']) {
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
     * @param $filter
     * @return ModelQueryBuilder
     */

    public function buildPropertiesFilterQuery($query, $filter)
    {
        if (in_array($filter['property'], ['tags', 'product'])) {
            $subField = $filter['property'] == 'tags' ? 'tag_id' : 'product_id';
            list($method, $subMethod) = static::parseRelationalFilterQueryMethods($filter);
            $query = static::buildRelationFilterQuery($filter['property'], $query, $method, $subMethod, $subField, $filter);
        }

        elseif ($filter['property'] == 'waiting_for_reply'){
            if (($filter['value'] == 'yes' && $filter['operator'] == '=') || ($filter['value'] == 'no' && $filter['operator'] == '!=')){
                $query = $query->where(function ($q){
                    $q->whereColumn('last_agent_response', '<' ,'last_customer_response')
                        ->orWhereNull('last_agent_response')
                        ->orWhere('status', 'new');
                });
            } else {
                $query = $query->where(function ($q) {
                    $q->whereColumn('last_customer_response', '<' ,'last_agent_response');
                });
            }
        }

        else {
            $method = $filter['operator'] == 'in' ? 'whereIn' : 'whereNotIn';
            $query = $query->{$method}($filter['property'], (array) $filter['value']);
        }
        return $query;
    }

    /**
     * method to search by properties
     * @param $provider
     * @param $query
     * @param $search
     * @param string $operator
     * @return ModelQueryBuilder
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
     * @param $provider
     * @param $query
     * @param $filters
     * @return ModelQueryBuilder
     */
    public function filterTicketByUser($provider, $query, $filters)
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

    public function watchers()
    {
        $class = __NAMESPACE__ . '\TagPivot';

        return $this->hasMany($class, 'source_id', 'id')
            ->where('source_type', 'ticket_watcher')
            ->select([ 'tag_id']);
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
        /*
         * Action on ticket deleting
         *
         * @since v1.0.0
         * @param object $ticket
         */
        do_action('fluent_support/deleting_ticket', $this);
        // delete the responses first
        Conversation::where('ticket_id', $this->id)->delete();
        // Delete the ticket meta
        Meta::where('object_type', 'ticket_meta')->where('object_id', $this->id)->delete();
        // Delete attachments related to this ticket
        Attachment::where('ticket_id', $this->id)->delete();
        // Delete the ticket
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

    /**
     * @param $data This is the data that will be saved to the ticket_meta for custom fields
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
        $query =  \FluentSupport\App\App::db()->table('fs_conversations')
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
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * This method will assign tags to the ticket
     * @param $tagIds This is the array of tag ids that will be assigned to the ticket
     * @return \FluentSupport\App\Models\Ticket
     */
    public function applyTags($tagIds)
    {
        $result = false;
        foreach ($tagIds as $tagId) {
            if (!$this->hasTag($tagId)) {
                $this->tags()->attach($tagId, ['source_type' => 'ticket_tag']);
                $result = true;

                /*
                 * Action while tag added to ticket
                 *
                 * @since v1.0.0
                 * @param integer $tagId
                 * @param object  $ticket
                 */
                do_action('fluent_support/ticket_tag_added', $tagId, $this);
            }
        }
        return $result;
    }
    /**
     * This method will remove tags from ticket
     * @param $tagIds This is the array of tag ids that will be removed from the ticket
     * @return \FluentSupport\App\Models\Ticket
     */
    public function detachTags($tagIds)
    {
        $result = false;
        foreach ($tagIds as $tagId) {
            if ($this->hasTag($tagId)) {
                $this->tags()->detach($tagId);

                /*
                 * Action while tag removed from ticket
                 *
                 * @since v1.0.0
                 * @param integer $tagId
                 * @param object  $ticket
                 */
                do_action('fluent_support/ticket_tag_removed', $tagId, $this);
                $result = true;
            }
        }
        return $result;
    }

    /**
     * This `getTickets` method will return the all tickets
     * @param \FluentSupport\Framework\Request\Request $request
     * @param string $filterType
     * @return array $tickets
     */
    public function getTickets ( $request, $filterType )
    {
        $queryArgs = $this->prepareQuery( $request, $filterType );
        $tickets = $this->getTicketsByQuery( $queryArgs );

        foreach ($tickets as $ticket) {
            if ( $request->get('per_page') < 15 ) {
                if ($ticket->status != 'closed') {
                    $ticket->live_activity = TicketHelper::getActivity( $ticket->id );
                } else {
                    $ticket->live_activity = [];
                }
            }
        }

        return [
            'tickets' => $tickets
        ];
    }

    // This is a supporting method for getTickets method
    // it prepare the query arguments for tickets filtering
    private function prepareQuery ( $request, $filterType )
    {
        $queryArgs = [
            'with' => [],
            'filter_type' => $filterType,
            'sort_by' => $request->get('order_by', 'id'),
            'sort_type' => $request->get('order_type', 'DESC'),
        ];

        if( $filterType == 'advanced' ){
            //Get the selected query params for advanced filter
            $queryArgs['filters_groups_raw'] = json_decode( $request->get( 'advanced_filters' ), true );
        } else {
            //Selected filter type is simple
            $queryArgs['simple_filters'] = $request->get('filters', []);
            $queryArgs['search'] = trim( sanitize_text_field( $request->get('search', '') ) );
            if ( $customerId = $request->get( 'customer_id' ) ) {
                $queryArgs['customer_id'] = intval( $customerId );
            }
        }

        return $queryArgs;
    }

    // This is a supporting method for getTickets method
    // it returns the tickets by query arguments
    private function getTicketsByQuery ( $queryArgs )
    {
        $ticketsModel = (new TicketQueryService($queryArgs))->getModel();

        $ticketsModel = $ticketsModel->with([
            'customer'         => function ($query) {
                $query->select(['first_name', 'last_name', 'email', 'id', 'avatar']);
            }, 'agent'         => function ($query) {
                $query->select(['first_name', 'last_name', 'id']);
            },
            'product',
            'tags',
            'preview_response' => function ($query) {
                $query->orderBy('id', 'desc');
            }
        ]);


        // apply filters by access level
        do_action_ref_array('fluent_support/tickets_query_by_permission_ref', [&$ticketsModel, false]);

        return $ticketsModel->paginate();
    }


    /**
     * This `createTicket` method will create a new ticket and it will also create a new customer or
     * a customer with WP profile if given.
     * @param array $ticketData
     * @param array $maybeNewCustomer
     * @return array
     */

    public function createTicket ($ticketData, $maybeNewCustomer )
    {
        $ticketData = $this->maybeCreateNewCustomer( $ticketData, $maybeNewCustomer );
        $customer = Customer::findOrFail($ticketData['customer_id']);
        $ticketData = $this->buildTicketData( $ticketData, $customer );

        return [
            'message' => __('Ticket has been created successfully', 'fluent-support'),
            'ticket'  => $this->storeTicket( $ticketData, $customer )
        ];
    }

    // This is a supporting method for createTicket method
    // it will create ticket data array for ticket creation
    private function buildTicketData ( $ticketData, $customer )
    {
        if (empty($ticketData['mailbox_id'])) {
            $mailbox = Helper::getDefaultMailBox();
            $ticketData['mailbox_id'] = $mailbox->id;
        } else {
            $mailbox = MailBox::findOrFail($ticketData['mailbox_id']); // just for validation
        }

        if (!empty($ticketData['product_id'])) {
            $ticketData['product_source'] = 'local';
        }

        $ticketData['title'] = sanitize_text_field( wp_unslash ( $ticketData['title'] ) );

        $ticketData['content'] = wp_unslash ( wp_kses_post ( $ticketData['content'] ) );

        if (!empty($ticketData['priority'])) {
            $ticketData['priority'] = sanitize_text_field($ticketData['priority']);
        }

        $ticketData['client_priority'] = sanitize_text_field($ticketData['client_priority']);

        /*
         * Filter ticket data
         *
         * @since v1.0.0
         * @param array  $ticketData
         * @param object $customer
         */
        $ticketData = apply_filters('fluent_support/create_ticket_data', $ticketData, $customer);

        return $ticketData;
    }

    // This is a supporting method for createTicket method
    // it will store the ticket and return the ticket object
    private function storeTicket ( $ticketData, $customer )
    {
        /*
         * Action before ticket create
         *
         * @since v1.0.0
         * @param array  $ticketData
         * @param object $customer
         */
        do_action('fluent_support/before_ticket_create', $ticketData, $customer);

        $createdTicket = Ticket::create($ticketData);

        if (defined('FLUENTSUPPORTPRO') && !empty($ticketData['custom_fields'])) {
            $createdTicket->syncCustomFields($ticketData['custom_fields']);
        }

        /*
         * Action on ticket create
         *
         * @since v1.0.0
         * @param object $createdTicket
         * @param object $customer
         */
        do_action('fluent_support/ticket_created', $createdTicket, $customer);

        return $createdTicket;
    }

    // This is a supporting method for createTicket method
    // it will create a new customer or a customer with WP profile if given
    // and after creating a new user it will store customer_id
    // inside $ticketData array as we only need customer_id to create ticket
    private function maybeCreateNewCustomer ( $ticketData, $maybeNewCustomer )
    {
        if ($ticketData['create_wp_user'] == 'yes'){
            // Check if username already in use, if not create a wp new user
            if(!username_exists($maybeNewCustomer['username'])){
                $authController = new AuthController();
                $createdUser = $authController->createUser($maybeNewCustomer);
                $authController->maybeUpdateUser($createdUser, $maybeNewCustomer);
            }else{
                throw new Exception('This username is already exist in WordPress');
            }
        }

        // If user select create customer during ticket creation
        if($ticketData['create_customer'] == 'yes'){
            // Check if user already exist as customer, if not create one
            if ( !empty($maybeNewCustomer) && is_null(Customer::where('email', $maybeNewCustomer['email'])->first()) ){
                $createCustomer = Customer::create($maybeNewCustomer);
                if ($createCustomer){
                    $ticketData['customer_id'] = $createCustomer->id;
                }
            }
            else{
                throw new Exception('Customer with this email already exist');
            }
        }

        return $ticketData;
    }
}
