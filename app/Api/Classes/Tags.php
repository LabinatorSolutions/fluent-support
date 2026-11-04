<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Models\TicketTag;

/**
 * Ticket tags PHP API.
 *
 * Example Usage: $ticketTagsApi = FluentSupportApi('tags');
 */
class Tags
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(TicketTag $instance)
    {
        $this->instance = $instance;
    }

    public function getTags()
    {
        return TicketTag::paginate();
    }

    /**
     * @param int $id
     */
    public function getTag(int $id)
    {
        if (!$id) {
            return;
        }

        return TicketTag::findOrFail($id);
    }

    /**
     * @param array $data
     */
    public function createTag(array $data)
    {
        if (empty($data['title'])) {
            return;
        }
        return TicketTag::create(wp_unslash($data));
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function updateTag(int $id, array $data)
    {
        if (!$id || !$data) {
            return;
        }
        return TicketTag::findOrFail($id)->update($data);
    }

    /**
     * @param int $id
     */
    public function deleteTag(int $id)
    {
        if (!$id) {
            return;
        }
        return TicketTag::findOrFail($id)->delete();
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

        throw new \Exception(sprintf('Method %s does not exist.', esc_html($method)));
    }
}
