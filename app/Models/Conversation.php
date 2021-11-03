<?php

namespace FluentSupport\App\Models;

class Conversation extends Model
{
    protected $table = 'fs_conversations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ticket_id',
        'person_id',
        'message_id',
        'conversation_type',
        'content',
        'source',
        'is_important'
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
        'content'
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
     * @param string $type
     * @return ModelQueryBuilder
     */
    public function scopeFilterByType($query, $type)
    {
        $query->where('conversation_type', $type);

        return $query;
    }

    /**
     * One2Many: Customer has to many Click Tickets
     * @return Model Collection
     */
    public function ticket()
    {
        $class = __NAMESPACE__ . '\Ticket';

        return $this->belongsTo(
            $class, 'ticket_id', 'id'
        );
    }

    /**
     * One2Many: Customer has to many Click Tickets
     * @return Model Collection
     */
    public function person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'person_id', 'id'
        );
    }

    /**
     * A Conversation belongs to a Customer.
     *
     * @return \FluentSupport\App\Models\Model
     */
    public function customer()
    {
        return $this->person();
    }

    public function attachments()
    {
        $class = __NAMESPACE__ . '\Attachment';
        return $this->hasMany($class, 'conversation_id', 'id');
    }

}
