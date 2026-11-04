<?php

namespace FluentSupport\App\Services\Integrations\FluentBooking;

use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class FluentBookingService
{

    /**
     * @return void
     */
    public function init()
    {
        add_action('fluent_booking/after_booking_scheduled', [$this, 'handleBookingScheduled'], 20, 3);
        add_action('fluent_support/response_added_by_agent', [$this, 'promoteGeneratedBookingLinksFromResponse'], 20, 3);
    }

    /**
     * Pass $eventTypes if already loaded to skip the query.
     *
     * @param array|null $eventTypes
     * @return array
     */
    public function getStatus($eventTypes = null)
    {
        $active = $this->isActive();
        $eventTypes = is_array($eventTypes) ? $eventTypes : ($active ? $this->getEventTypes() : []);

        return [
            'active'     => $active,
            'configured' => $active && !empty($eventTypes),
            'admin_url'  => esc_url_raw(admin_url('admin.php?page=fluent-booking#/')),
            'message'    => $active
                ? ''
                : __('FluentBooking is not installed or active.', 'fluent-support')
        ];
    }

    /**
     * Active event types whose landing page is public, for the frontend selector.
     *
     * @return array
     */
    public function getEventTypes()
    {
        if (!$this->isActive()) {
            return [];
        }

        $events = \FluentBooking\App\Models\CalendarSlot::query()
            ->with('calendar')
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->get();

        $eventTypes = [];

        foreach ($events as $event) {
            if (!$event->calendar || !self::canUseEvent($event)) {
                continue;
            }

            $eventTypes[] = [
                'id'             => (int) $event->id,
                'title'          => sanitize_text_field($event->title),
                'duration'       => (int) $event->duration,
                'calendar_title' => sanitize_text_field($event->calendar->title),
            ];
        }

        return $eventTypes;
    }

    /**
     * Build the reply content (HTML and plain text) with a tokenised booking link.
     * Selected slots are re-checked against live availability.
     *
     * @param Ticket $ticket
     * @param int    $eventId
     * @param string $message
     * @param array  $selectedSlots
     * @param string $timezone
     * @throws \Exception
     * @return array
     */
    public function createBookingLink(Ticket $ticket, $eventId, $message = '', $selectedSlots = [], $timezone = '')
    {
        if (!$this->isActive()) {
            throw new \Exception(esc_html__('FluentBooking is not installed or active.', 'fluent-support'));
        }

        $event = \FluentBooking\App\Models\CalendarSlot::with('calendar')->find($eventId);

        if (!$event || !$event->calendar || !self::canUseEvent($event)) {
            throw new \Exception(esc_html__('Selected FluentBooking event type is not available.', 'fluent-support'));
        }

        $linkToken = bin2hex(random_bytes(16));
        $bookingUrl = self::addBookingLinkToken(self::getEventUrl($event, $ticket), $linkToken);
        $message = trim(wp_unslash($message));
        $availability = new BookingAvailabilityHelper();

        $selectedSlots = $availability->sanitizeSelectedSlots($selectedSlots, $event, $ticket, (int) $event->getDuration());
        $selectedSlots = $availability->addTokenToSlots($selectedSlots, $linkToken);
        $slotsHtml = $availability->formatSlotsHtml($selectedSlots, $event, $timezone);

        $htmlParts = [];

        if ($message !== '') {
            $htmlParts[] = '<p>' . esc_html($message) . '</p>';
        }

        if ($slotsHtml) {
            $htmlParts[] = $slotsHtml;
        }

        $htmlParts[] = '<p><a href="' . esc_url($bookingUrl) . '" target="_blank" rel="noopener">' . esc_html__('See all available times', 'fluent-support') . '</a></p>';

        return [
            'html'       => wp_kses_post(implode('', $htmlParts)),
            'plain_text' => $availability->formatSlotsPlainText($message, $selectedSlots, $bookingUrl),
        ];
    }

    /**
     * Available slots for the event in the given range, grouped by day.
     *
     * @param int         $eventId
     * @param string      $range
     * @param string      $timezone
     * @param int|null    $duration
     * @param Ticket|null $ticket
     * @param array       $selectedDates
     * @param string      $calendarMonth
     * @throws \Exception
     * @return array
     */
    public function getAvailabilitySlots($eventId, $range = 'next_3_days', $timezone = '', $duration = null, ?Ticket $ticket = null, $selectedDates = [], $calendarMonth = '')
    {
        if (!$this->isActive()) {
            throw new \Exception(esc_html__('FluentBooking is not installed or active.', 'fluent-support'));
        }

        $event = \FluentBooking\App\Models\CalendarSlot::with('calendar')->find($eventId);

        if (!$event || !$event->calendar || !self::canUseEvent($event)) {
            throw new \Exception(esc_html__('Selected FluentBooking event type is not available.', 'fluent-support'));
        }

        $availability = new BookingAvailabilityHelper();
        $timezone = $availability->sanitizeTimezone($timezone, $event->calendar->author_timezone);
        $rangeData = $availability->getAvailabilityRange($range, $timezone, $selectedDates, $calendarMonth);

        $days = array_map(function ($day) {
            $day['slots'] = array_map(function ($slot) {
                return [
                    'id'         => $slot['id'],
                    'start'      => $slot['start'],
                    'time_label' => $slot['time_label'],
                ];
            }, $day['slots']);

            return $day;
        }, $availability->fetchFormattedDays($event, $rangeData, $timezone, $ticket, (int) $event->getDuration($duration)));

        return [
            'timezone'        => $timezone,
            'max_lookup_date' => $event->getMaxLookUpDate(),
            'days'            => $days,
        ];
    }

    /**
     * Upcoming and past bookings for the ticket's customer, scoped to the
     * events and dates of the ticket's stored links when there are any.
     *
     * @param Ticket $ticket
     * @return array
     */
    public function getTicketMeetings(Ticket $ticket)
    {
        $links = new BookingLinkManager();
        $generatedLinks = $links->getLinksForTicket($ticket);

        $meetings = [
            'upcoming' => [],
            'past'     => [],
            'message'  => '',
        ];

        if (!$this->isActive() || !$ticket->customer || !$ticket->customer->email) {
            $meetings['message'] = __('FluentBooking is not installed or the ticket customer has no email address.', 'fluent-support');
            return $meetings;
        }

        $eventIds = array_values(array_unique(array_filter(array_column($generatedLinks, 'event_type_id'))));
        $earliestLinkDate = $links->getEarliestLinkDate($generatedLinks);

        $bookingQuery = \FluentBooking\App\Models\Booking::query()
            ->with(['calendar', 'calendar_event'])
            ->where('email', sanitize_email($ticket->customer->email))
            ->where('status', '!=', 'reserved');

        if ($eventIds) {
            $bookingQuery->whereIn('event_id', $eventIds);
        }

        if ($earliestLinkDate) {
            $bookingQuery->where('created_at', '>=', $earliestLinkDate);
        }

        $bookings = $bookingQuery->orderBy('start_time', 'DESC')->limit(10)->get();

        foreach ($bookings as $booking) {
            $formatted = $links->formatBooking($booking);

            if (strtotime($booking->end_time) >= time() && $booking->status === 'scheduled') {
                $meetings['upcoming'][] = $formatted;
            } else {
                $meetings['past'][] = $formatted;
            }
        }

        usort($meetings['upcoming'], function ($a, $b) {
            return strtotime($a['start_time']) <=> strtotime($b['start_time']);
        });

        if (!$bookings->count() && $generatedLinks) {
            $meetings['message'] = __('No meeting booked yet from these links.', 'fluent-support');
        } elseif (!$bookings->count()) {
            $meetings['message'] = __('No FluentBooking meetings found for this customer.', 'fluent-support');
        }

        return $meetings;
    }

    /**
     * Add a timeline note when the customer books through a link sent from the ticket.
     *
     * @param object $booking
     * @param object $calendarSlot
     * @param array  $bookingData
     * @return void
     */
    public function handleBookingScheduled($booking, $calendarSlot, $bookingData = [])
    {
        $links = new BookingLinkManager();
        $ticket = $links->getTicketFromBooking($booking, $bookingData);

        if (!$ticket || $links->hasTimelineNote($booking)) {
            return;
        }

        $formatted = $links->formatBooking($booking);
        $personId = (int) $ticket->agent_id ?: (int) $ticket->customer_id;
        $eventTitle = Arr::get($formatted, 'event_title') ?: __('Meeting', 'fluent-support');

        $content = sprintf(
            '<strong>%1$s</strong><br>%2$s<br>%3$s',
            esc_html__('Meeting scheduled via FluentBooking', 'fluent-support'),
            esc_html($eventTitle),
            esc_html(Arr::get($formatted, 'date_text'))
        );

        if ($timezone = Arr::get($formatted, 'timezone')) {
            $content .= '<br><span>' . esc_html($timezone) . '</span>';
        }

        if ($adminUrl = Arr::get($formatted, 'admin_url')) {
            $content .= '<br><a href="' . esc_url($adminUrl) . '" target="_blank" rel="noopener">' . esc_html__('View booking', 'fluent-support') . '</a>';
        }

        ResponseService::addActivity($ticket, $personId, wp_kses_post($content), [
            'message_id' => 'fluent_booking_scheduled_' . (int) $booking->id,
            'source'     => 'fluent_booking',
        ]);
    }

    /**
     * Store any tokenised booking links found in an agent response.
     *
     * @param object $response
     * @param Ticket $ticket
     * @param object $person
     * @return void
     */
    public function promoteGeneratedBookingLinksFromResponse($response, Ticket $ticket, $person)
    {
        if (empty($response->content) || $response->conversation_type !== 'response') {
            return;
        }

        $links = new BookingLinkManager();
        $bookingLinks = $links->extractLinksFromContent($response->content);

        if (!$bookingLinks) {
            return;
        }

        foreach ($bookingLinks as $bookingLink) {
            $links->storeLinkFromUrl($ticket, $person, $bookingLink);
        }
    }

    /**
     * FluentBooking is installed and the integration is enabled.
     *
     * @return bool
     */
    public function isActive()
    {
        if (!defined('FLUENT_BOOKING_VERSION')) {
            return false;
        }
        return Helper::getBusinessSettings('enable_fluent_booking_integration', 'yes') === 'yes';
    }

    /**
     * The calendar's public landing page is enabled and includes this event.
     *
     * @param object $event
     * @return bool
     */
    public static function canUseEvent($event)
    {
        $settings = \FluentBooking\App\Services\LandingPage\LandingPageHelper::getSettings($event->calendar, 'public');

        if (Arr::get($settings, 'enabled') !== 'yes') {
            return false;
        }

        if (Arr::get($settings, 'show_type') === 'all') {
            return true;
        }

        return in_array((int) $event->id, array_map('intval', Arr::get($settings, 'enabled_slots', [])), true);
    }

    /**
     * Landing-page URL for an event, prefilled with the customer and ticket ID when given.
     * Forces getLandingPageUrl() because callers have already checked canUseEvent().
     *
     * @param object      $event
     * @param Ticket|null $ticket
     * @return string
     */
    public static function getEventUrl($event, ?Ticket $ticket = null)
    {
        $calendarBaseUrl = $event->calendar->getLandingPageUrl(true);

        if (!$calendarBaseUrl) {
            return '';
        }

        $url = defined('FLUENT_BOOKING_LANDING_SLUG')
            ? $calendarBaseUrl . '/' . $event->slug
            : $calendarBaseUrl . '&event=' . $event->slug;

        if (!$ticket || !$ticket->customer) {
            return esc_url_raw($url);
        }

        $customer = $ticket->customer;

        return esc_url_raw(add_query_arg(array_filter([
            'invitee_name'  => sanitize_text_field($customer->full_name),
            'invitee_email' => sanitize_email($customer->email),
            'fs_ticket_id'  => (int) $ticket->id
        ]), $url));
    }

    /**
     * Add fs_booking_link_token so a booking can be traced back to the link that was sent.
     *
     * @param string $url
     * @param string $linkToken
     * @return string
     */
    public static function addBookingLinkToken($url, $linkToken)
    {
        $linkToken = sanitize_key($linkToken);

        if (!$url || !$linkToken) {
            return esc_url_raw($url);
        }

        return esc_url_raw(add_query_arg(['fs_booking_link_token' => $linkToken], $url));
    }

}
