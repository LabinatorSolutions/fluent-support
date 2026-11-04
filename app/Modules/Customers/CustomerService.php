<?php

namespace FluentSupport\App\Modules\Customers;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\ProfileInfoService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\Framework\Support\Arr;

/**
 * What other code does with customers: find or create one (portal, email
 * piping, imports, integrations), update and delete one (admin, MCP), and
 * import one from a WordPress user or FluentCRM contact found by email.
 */
class CustomerService
{
    /**
     * Fields an agent may set.
     */
    const EDITABLE = ['first_name', 'last_name', 'email', 'title', 'status', 'note', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country'];

    /**
     * The customer for this data, or a new one. Looks up by remote_uid, then
     * user_id, then email. A WordPress user with the email is linked on create.
     * An existing record is only changed when it is linked to a WordPress user
     * or remote account, never moved to another account, and a linked
     * customer's email only changes through an email claim or an agent.
     * Without a user_id or remote_uid from the caller the data is unverified
     * (a guest form or an email anyone can send), so it only fills empty fields.
     *
     * @return Customer|false
     */
    public static function findOrCreate(array $data)
    {
        $verified = !empty($data['user_id']) || !empty($data['remote_uid']);
        $customer = self::find($data);
        $user = !empty($data['email']) ? get_user_by('email', $data['email']) : false;
        if ($user) {
            $data['first_name'] = $user->first_name ?: Arr::get($data, 'first_name');
            $data['last_name'] = $user->last_name ?: Arr::get($data, 'last_name');
            if (empty($data['first_name']) && empty($data['last_name'])) {
                $data['first_name'] = $user->display_name;
            }
            $data['user_id'] = $user->ID;
        }

        if (!$customer) {
            if (!empty($data['last_ip_address'])) {
                $data['ip_address'] = $data['last_ip_address'];
            }

            $customer = Customer::create(Customer::explodeFullName($data));
            if ($customer && $customer->exists) {
                do_action('fluent_support/customer_created', $customer);
            }

            return $customer;
        }

        if ((int) Arr::get($data, 'user_id') !== (int) $customer->user_id) {
            unset($data['user_id']);
        }
        if ((int) $customer->user_id) {
            unset($data['email']);
        }
        if (!empty($data['user_id']) || !empty($data['remote_uid'])) {
            $changes = array_filter($data);
            if (!$verified) {
                $changes = array_filter($changes, fn($key) => empty($customer->getAttribute($key)), ARRAY_FILTER_USE_KEY);
            }
            $customer->fill($changes)->save();
        }

        return $customer;
    }

    /**
     * Look up by remote_uid, then user_id (no email fallback: a WordPress
     * email is unverified), then email. Email isn't unique, so the oldest wins.
     *
     * @return Customer|false
     */
    public static function find(array $data)
    {
        if (!empty($data['remote_uid']) && $customer = Customer::where('remote_uid', $data['remote_uid'])->first()) {
            return $customer;
        }
        if (!empty($data['user_id'])) {
            return Customer::where('user_id', (int) $data['user_id'])->orderBy('id', 'ASC')->first() ?: false;
        }
        if (!empty($data['email'])) {
            return Customer::where('email', $data['email'])->orderBy('id', 'ASC')->first() ?: false;
        }

        return false;
    }

    /**
     * Updates the fields given (others are kept). Email and first name are
     * required. A WordPress user owning the new email is linked only by an
     * admin, and only when no other customer has it.
     *
     * @param array $data EDITABLE fields, plus custom registration field values
     */
    public static function update(Customer $customer, array $data)
    {
        $email = sanitize_email(Arr::get($data, 'email', ''));
        if (!is_email($email) || !trim((string) Arr::get($data, 'first_name', ''))) {
            throw ApiException::invalid(__('A valid email and a first name are required.', 'fluent-support'));
        }
        if (Customer::where('id', '!=', $customer->id)->where('email', $email)->exists()) {
            throw new ApiException(__('Email address has been assigned to other customer', 'fluent-support'), 422, [
                'errors' => ['email' => ['unique' => __('Email address has been assigned to other customer', 'fluent-support')]]
            ]);
        }

        $changes = array_intersect_key($data, array_flip(self::EDITABLE));
        $changes['email'] = $email;
        if (isset($changes['status']) && !in_array($changes['status'], ['active', 'inactive'], true)) {
            unset($changes['status']);
        }

        // Custom registration fields live in the linked user's meta.
        $customKeys = Helper::getCustomRegistrationFieldKeys();
        $user = get_user_by('email', $email);
        if ($user && $customKeys) {
            $userId = self::linkableUserId($customer, (int) $user->ID);
            if ($userId) {
                $changes['user_id'] = $userId;
                foreach ($customKeys as $key) {
                    if (isset($data[$key]) && is_scalar($data[$key])) {
                        update_user_meta($userId, $key, sanitize_text_field($data[$key]));
                    }
                }
            }
        }

        $previousEmail = (string) $customer->email;
        Customer::where('id', $customer->id)->update($changes);
        $customer = Customer::find($customer->id);

        // Revoke signed ticket links sent to the old address.
        if (strtolower(trim($previousEmail)) !== strtolower($email)) {
            ProfileInfoService::onProvenEmailChange($customer, $previousEmail, 'agent');
        }

        return $customer;
    }

    /**
     * Deletes the customer and their tickets (through TicketService, so
     * fluent_support/ticket_deleted fires for each).
     */
    public static function delete(Customer $customer)
    {
        $tickets = new TicketService();
        foreach (Ticket::where('customer_id', $customer->id)->get() as $ticket) {
            $tickets->deleteTicket($ticket);
        }

        $customer->delete();
    }

    /**
     * Creates a customer from the FluentCRM contact, or (Pro) the WordPress
     * user, with this exact email. Support staff are never imported.
     *
     * @return bool whether a customer exists for the email now
     */
    public static function importByEmail($email)
    {
        if (!is_email($email) || Helper::isSupportStaffEmail($email)) {
            return false;
        }

        if (defined('FLUENTCRM') && $contact = \FluentCrm\App\Models\Subscriber::where('email', $email)->first()) {
            return (bool) self::findOrCreate(['email' => $contact->email, 'first_name' => $contact->first_name, 'last_name' => $contact->last_name]);
        }

        if (defined('FLUENT_SUPPORT_PRO_DIR_FILE') && $user = get_user_by('email', $email)) {
            return (bool) self::findOrCreate(['user_id' => $user->ID, 'email' => $user->user_email, 'first_name' => $user->first_name, 'last_name' => $user->last_name]);
        }

        return false;
    }

    /**
     * The WordPress account this customer may be linked to: the portal finds
     * customers by user_id, so moving the link hands over their tickets. Only
     * an admin may move it, and not to an account another customer has.
     *
     * @return int the user id to link, or the current one
     */
    private static function linkableUserId(Customer $customer, $userId)
    {
        $current = (int) $customer->user_id;

        if ($userId === $current || !current_user_can('manage_options')) {
            return $current;
        }
        if (Customer::where('id', '!=', $customer->id)->where('user_id', $userId)->exists()) {
            return $current;
        }

        return $userId;
    }
}
