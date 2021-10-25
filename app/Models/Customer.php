<?php

namespace FluentSupport\App\Models;

use FluentSupport\Framework\Database\Orm\Builder;
use FluentSupport\Framework\Support\Arr;

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

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->person_type = static::$type;
            $model->hash = md5(time().wp_generate_uuid4());
        });

        static::addGlobalScope(function (Builder $builder) {
            $builder->where('person_type', 'customer');
        });

    }

    public static function maybeCreateCustomer($customerData)
    {
        $customer = self::getCustomerFromData($customerData);

        $user = get_user_by('email', $customerData['email']);
        if($user) {
            if($user->first_name) {
                $customerData['first_name'] = $user->first_name;
            }
            if($user->last_name) {
                $customerData['last_name'] = $user->last_name;
            }
            if(empty($customerData['first_name']) && empty($customerData['last_name'])) {
                $customerData['first_name'] = $user->display_name;
            }

            $customerData['user_id'] = $user->ID;
        }

        if(!$customer) {
            if(!empty($customerData['last_ip_address'])) {
                $customerData['ip_address'] = $customerData['last_ip_address'];
            }
            // we have to create customer
            $customer = self::create($customerData);

            $customer->newly_created = true;

        } else {
            if(!empty($customerData['user_id']) || !empty($customerData['remote_uid'])) {
                $customerData = array_filter($customerData);
                $customer->fill($customerData);
                $customer->save();
            }

            $customer->newly_created = false;
        }

        return $customer;
    }


    /**
     * One2Many: Customer has to many Click Tickets
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

    public static function getCustomerFromData($customerData)
    {
        $remoteUid = Arr::get($customerData, 'remote_uid');
        $email = $customerData['email'];

        $customer = false;
        if($remoteUid) {
            $customer = self::where('remote_uid', $remoteUid)->first();
        }

        if(!$customer) {
            if(!empty($customerData['user_id'])) {
                $customer = self::where('user_id', $customerData['user_id'])->first();
            }
            if(!$customer) {
                $customer = self::where('email', $email)->first();
            }
        }

        return $customer;
    }

    public function getTicketCounts()
    {
        return Ticket::where('customer_id', $this->id)->count();
    }

    public function getResponseCounts()
    {
        return Conversation::where('person_id', $this->id)->count();
    }
}
