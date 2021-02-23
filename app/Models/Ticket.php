<?php

namespace FluentSupport\App\Models;

use FluentSupport\App\Services\Helper;

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
        'product_source',
        'privacy',
        'priority',
        'status',
        'title',
        'slug',
        'content',
        'last_agent_response',
        'last_customer_response',
        'response_count',
        'first_response_time',
        'total_close_time',
        'resolved_at',
        'closed_by'
    ];

    public static function boot()
    {
//        static::creating(function ($model) {
//            $model->person_type = static::$type;
//        });
    }

    /**
     * $searchable Columns in table to search
     * @var array
     */
    protected $searchable = [
        'title',
        'slug',
        'content',
        'id'
    ];

    /**
     * Local scope to filter subscribers by search/query string
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */
    public function scopeSearchBy($query, $search)
    {
        if ($search) {

            if(strpos($search, ':')) {
                $array = explode(':', $search);
                $column = $array[0];
                $value = $array[1];
                $columns  = $this->fillable;
                $columns[] = 'id';

                if(in_array($column, $columns) && $value) {
                    if(is_numeric($value)) {
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

    public function scopeApplyFilters($query, $filters)
    {
        $supportedColumns = ['product_id', 'agent_id', 'client_priority', 'priority'];
        foreach ($filters as $filterKey => $filterValue) {
            if(!$filterValue) {
                continue;
            }
            if($filterKey == 'status_type') {
                $statusArray = Helper::getTkStatusesByGroupName($filterValue);
                if($statusArray) {
                    $query->whereIn('status', $statusArray);
                }
            } else if(in_array($filterKey, $supportedColumns)) {
                $query->where($filterKey, $filterValue);
            } else if($filterKey == 'waiting_for_reply') {
                if($filterValue != 'yes') {
                    continue;
                }
                global $wpdb;
                $query->where(function ($q) use ($wpdb) {
                    $q->whereRaw($wpdb->prefix.'fs_tickets.last_agent_response < '.$wpdb->prefix.'fs_tickets.last_customer_response')
                        ->orWhereNull('last_agent_response')
                        ->orWhere('status', 'new');
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
     * One2Many: Customer has to many Click Tickets
     * @return Model Collection
     */
    public function responses()
    {
        $class = __NAMESPACE__ . '\Response';

        return $this->hasMany(
            $class, 'ticket_id', 'id'
        );
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

    /**
     * Accessor to get dynamic full_name attribute
     * @return string
     */
    public function getExcerpt()
    {
        $content = $this->content;
        $content = substr(preg_replace( "/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($content))) ), 0, 180);
        return $content.'...';
    }

}
