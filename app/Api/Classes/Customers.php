<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Http\Controllers\AuthController;
use FluentSupport\App\Models\Customer;

class Customers
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(Customer $instance)
    {
        $this->instance = $instance;
    }

    /**
     * getCustomers method will return a all available customers
     *
     * @return object
     */

    public function getCustomers()
    {
        return Customer::paginate();
    }

    /**
     * getCustomer method will return a specific customer by id
     *
     * @param  int   $customerId
     * @return object
     */

    public function getCustomer(int $customerId)
    {
        if(is_numeric($customerId)){
            return Customer::findOrFail($customerId);
        }
        return false;
    }

    /**
     * updateCustomer method will update the specific customer by id
     *
     * @param  array   $data
     * @param  int   $customer_id
     * @return object
     */

    public function updateCustomer(array $data, int $customer_id)
    {
        if(!$customer_id) {
            return false;
        }
        if($customer = Customer::where('id', $customer_id)->first()){
            return $customer->update($data);
        }
        return false;
    }

    /**
     * createCustomerWithOrWithoutWpUser method will create a customer
     * also it will create a wp user if you want to create one
     * if you want to create a wp user too then the process will be 1st it will create a wp user
     * then after creating the wp user successfully it will create a fluent support customer
     *
     * @param  array   $data
     * @param bool $createWpUser
     * @return object
     */

    public function createCustomerWithOrWithoutWpUser(array $data, bool $createWpUser=false)
    {
        if(!$createWpUser) {
            $isExist = Customer::where('email', $data['email'])->first();
            if (!$data['email'] || !is_email($data['email']) || $isExist) {
                return false;
            }
            return Customer::create($data);
        }

        if(!username_exists($data['username'])){
            $authController = new AuthController();
            $createdUser = $authController->createUser($data);
            $updateCreatedUser = $authController->maybeUpdateUser($createdUser, $data);
            if($updateCreatedUser) {
               return Customer::create($updateCreatedUser);
            }
        }
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function __call($method, $params)
    {
        if (in_array($method, $this->allowedInstanceMethods)) {
            return call_user_func_array([$this->instance, $method], $params);
        }

        throw new \Exception("Method {$method} does not exist.");
    }
}
