<?php

namespace FluentSupport\App\Models;

use FluentSupport\Framework\Database\Orm\Builder;
use FluentSupport\App\Modules\Customers\CustomerService;

class Customer extends Person
{
    protected static $type = 'customer';

    protected $searchable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'address_line_1',
        'address_line_2',
        'country'
    ];

    /**
     * @return array
     */
    public function getSearchableFields()
    {
        return $this->searchable;
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->person_type = static::$type;
            $model->hash = md5(time() . wp_generate_uuid4());
        });

        static::addGlobalScope(function (Builder $builder) {
            $builder->where('person_type', 'customer');
        });

    }

    public static function mappables()
    {
        return [
            'title'          => __('Customer Title', 'fluent-support'),
            'address_line_1' => __('Address Line 1', 'fluent-support'),
            'address_line_2' => __('Address Line 2', 'fluent-support'),
            'city'           => __('City', 'fluent-support'),
            'state'          => __('State', 'fluent-support'),
            'zip'            => __('Zip Code', 'fluent-support'),
            'country'        => __('Country', 'fluent-support'),
        ];
    }

    /**
     * @deprecated Use CustomerService::findOrCreate().
     * @return false|Customer
     */
    public static function maybeCreateCustomer($customerData)
    {
        return CustomerService::findOrCreate((array) $customerData);
    }

    /**
     * @return Model Collection
     */
    public function tickets()
    {
        $foreign_key = self::$type . '_id';

        $class = __NAMESPACE__ . '\Ticket';

        return $this->hasMany(
            $class, $foreign_key, 'id'
        );
    }

    /**
     * @deprecated Use CustomerService::find().
     * @return false|Customer
     */
    public static function getCustomerFromData($customerData)
    {
        return CustomerService::find((array) $customerData);
    }

    /**
     * Barred from support: any status but 'active'. Imported rows can have an
     * empty status, which counts as active.
     */
    public function isBlocked()
    {
        return ($this->status ?: 'active') !== 'active';
    }

    public function canAccessPortal()
    {
        return !$this->isBlocked();
    }

    /**
     * Same as portal access for now, kept apart so an email verification
     * policy can change one without the other.
     */
    public function canReceiveNotifications()
    {
        return !$this->isBlocked();
    }

    /**
     * No WordPress account (integrations store 0 as well as NULL).
     */
    public function scopeUnclaimed($query)
    {
        return $query->where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', 0));
    }

    public function scopeClaimed($query)
    {
        return $query->whereNotNull('user_id')->where('user_id', '!=', 0);
    }
}
