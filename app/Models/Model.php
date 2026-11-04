<?php

namespace FluentSupport\App\Models;

use FluentSupport\Framework\Database\Orm\Model as BaseModel;

class Model extends BaseModel
{
    protected $guarded = ['id', 'ID'];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Dates go out as stored: site time, "Y-m-d H:i:s", like every other date
     * column. The framework default is an ISO UTC moment, which made
     * created_at/updated_at the only dates in another format.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        if ($date instanceof \DateTime || $date instanceof \DateTimeImmutable) {
            $date = (clone $date)->setTimezone(wp_timezone());
        }

        return $date->format('Y-m-d H:i:s');
    }

    public function getPerPage()
    {
        if (!isset($_REQUEST['per_page'])) {
            return 15;
        }

        if (isset($_REQUEST['nonce'])) {
            $nonceAction = (defined('REST_REQUEST') && REST_REQUEST) ? 'wp_rest' : 'fluent-support';

            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), $nonceAction)) {
                return 15;
            }
        }

        $perPage = intval(sanitize_text_field(wp_unslash($_REQUEST['per_page'])));

        // Zero or less would divide by zero in the paginator.
        if ($perPage < 1) {
            return 15;
        }

        /*
         * The largest page size a paginated list returns. Without a cap one request
         * could return a whole table. 150 is the largest size the admin UI offers.
         *
         * @since 2.4.7
         * @param int $maxPerPage
         */
        $maxPerPage = (int) apply_filters('fluent_support/max_per_page', 150);

        return min($perPage, max(1, $maxPerPage));
    }
}
