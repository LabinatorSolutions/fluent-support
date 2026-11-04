<?php

namespace FluentSupport\App\Services\Integrations\FluentBooking;

use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Support\Arr;

class BookingAvailabilityHelper
{
    /**
     * Return a valid IANA timezone, falling back to $fallback, then the site timezone.
     *
     * @param string $timezone
     * @param string $fallback
     * @return string
     */
    public function sanitizeTimezone($timezone, $fallback = '')
    {
        $timezone = sanitize_text_field($timezone);
        $fallback = sanitize_text_field($fallback);

        if ($timezone) {
            $validated = \FluentBooking\App\Services\DateTimeHelper::getValidatedTimeZone($timezone);
            if ($validated === $timezone) {
                return $timezone;
            }
        }

        if ($fallback) {
            $validated = \FluentBooking\App\Services\DateTimeHelper::getValidatedTimeZone($fallback);
            if ($validated === $fallback) {
                return $fallback;
            }
        }

        return \FluentBooking\App\Services\DateTimeHelper::getTimeZone();
    }

    /**
     * Start/end window for an availability query: a named range, specific dates, or a calendar month.
     *
     * @param string $range
     * @param string $timezone
     * @param array  $selectedDates
     * @param string $calendarMonth
     * @return array
     */
    public function getAvailabilityRange($range, $timezone, $selectedDates = [], $calendarMonth = '')
    {
        $range = sanitize_key($range);
        $timezoneObject = new \DateTimeZone($timezone);
        $selectedDates = $this->sanitizeSpecificDates($selectedDates, $timezoneObject);
        $calendarMonth = $this->sanitizeCalendarMonth($calendarMonth, $timezoneObject);

        if ($range === 'specific_dates' && $selectedDates) {
            $start = new \DateTime($selectedDates[0] . ' 00:00:00', $timezoneObject);
            $end = new \DateTime(end($selectedDates) . ' 23:59:59', $timezoneObject);

            return [
                'key'            => $range,
                'start'          => $start->format('Y-m-d H:i:s'),
                'end'            => $end->format('Y-m-d H:i:s'),
                'days'           => count($selectedDates),
                'selected_dates' => $selectedDates
            ];
        }

        if ($range === 'specific_dates' && $calendarMonth) {
            $start = new \DateTime($calendarMonth . '-01 00:00:00', $timezoneObject);
            $end = clone $start;
            $end->modify('last day of this month')->setTime(23, 59, 59);

            return [
                'key'   => $range,
                'start' => $start->format('Y-m-d H:i:s'),
                'end'   => $end->format('Y-m-d H:i:s'),
                'days'  => (int) $end->format('j')
            ];
        }

        $start = new \DateTime('today', $timezoneObject);

        $rangeDays = [
            'next_3_days'  => 3,
            'this_week'    => 7,
            'next_week'    => 7,
            'next_14_days' => 14
        ];

        if ($range === 'next_week') {
            $start->modify('+7 days');
        } elseif (!isset($rangeDays[$range])) {
            $range = 'next_3_days';
        }

        $end = clone $start;
        $end->modify('+' . ($rangeDays[$range] - 1) . ' days')->setTime(23, 59, 59);

        return [
            'key'   => $range,
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
            'days'  => $rangeDays[$range]
        ];
    }

    /**
     * Fetch available spots from FluentBooking and format them as day/slot arrays.
     *
     * @param object      $event
     * @param array       $rangeData
     * @param string      $timezone
     * @param Ticket|null $ticket
     * @param int|null    $duration
     * @throws \Exception
     * @return array
     */
    public function fetchFormattedDays($event, $rangeData, $timezone, ?Ticket $ticket = null, $duration = null)
    {
        $timeSlotService = \FluentBooking\App\Hooks\Handlers\TimeSlotServiceHandler::initService($event->calendar, $event);

        if (is_wp_error($timeSlotService)) {
            throw new \Exception(esc_html($timeSlotService->get_error_message()));
        }

        $availableSpots = $timeSlotService->getAvailableSpots($rangeData['start'], $timezone, (int) $duration);

        if (is_wp_error($availableSpots)) {
            $availableSpots = [];
        }

        $availableSpots = apply_filters(
            'fluent_booking/available_slots_for_view',
            array_filter((array) $availableSpots),
            $event,
            $event->calendar,
            $timezone,
            (int) $duration
        );

        return $this->formatDays($availableSpots, $rangeData, $timezone, $event, $ticket, $duration);
    }

    /**
     * Keep only the submitted slots that are still bookable.
     *
     * @param array    $selectedSlots
     * @param object   $event
     * @param Ticket   $ticket
     * @param int|null $duration
     * @return array
     */
    public function sanitizeSelectedSlots($selectedSlots, $event, Ticket $ticket, $duration = null)
    {
        if (!is_array($selectedSlots)) {
            return [];
        }

        $timezone = $this->sanitizeTimezone('', $event->calendar->author_timezone);
        $timezoneObject = new \DateTimeZone($timezone);
        $selectedStarts = [];
        $selectedDates = [];

        foreach (array_slice($selectedSlots, 0, 10) as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $startValue = sanitize_text_field(Arr::get($slot, 'start'));

            if (!$startValue) {
                continue;
            }

            try {
                $startDate = new \DateTime($startValue, $timezoneObject);
                $selectedStarts[] = $startDate->format('Y-m-d H:i:s');
                $selectedDates[] = $startDate->format('Y-m-d');
            } catch (\Exception $exception) {
                continue;
            }
        }

        if (!$selectedStarts) {
            return [];
        }

        $selectedStarts = array_values(array_unique($selectedStarts));
        $rangeData = $this->getAvailabilityRange('specific_dates', $timezone, $selectedDates);

        if (empty(Arr::get($rangeData, 'selected_dates'))) {
            return [];
        }

        try {
            $formattedDays = $this->fetchFormattedDays($event, $rangeData, $timezone, $ticket, $duration);
        } catch (\Exception $e) {
            return [];
        }

        $availableSlots = [];

        foreach ($formattedDays as $day) {
            foreach ((array) Arr::get($day, 'slots', []) as $slot) {
                $availableSlots[$slot['start']] = $slot;
            }
        }

        $slots = [];

        foreach ($selectedStarts as $selectedStart) {
            if (empty($availableSlots[$selectedStart])) {
                continue;
            }

            $availableSlot = $availableSlots[$selectedStart];

            $slots[] = [
                'display_text' => $availableSlot['display_text'],
                'time_label'   => $availableSlot['time_label'],
                'start'        => $availableSlot['start'],
                'booking_url'  => $availableSlot['booking_url']
            ];
        }

        return $slots;
    }

    /**
     * Tag each slot's booking_url with the link token so bookings trace back to this send.
     *
     * @param array  $selectedSlots
     * @param string $linkToken
     * @return array
     */
    public function addTokenToSlots($selectedSlots, $linkToken)
    {
        if (!$selectedSlots || !is_array($selectedSlots)) {
            return [];
        }

        return array_map(function ($slot) use ($linkToken) {
            if (!empty($slot['booking_url'])) {
                $slot['booking_url'] = FluentBookingService::addBookingLinkToken($slot['booking_url'], $linkToken);
            }

            return $slot;
        }, $selectedSlots);
    }

    /**
     * HTML block of time-slot links, grouped by day, for an agent reply.
     *
     * @param array  $selectedSlots
     * @param object $event
     * @param string $timezone
     * @return string
     */
    public function formatSlotsHtml($selectedSlots, $event, $timezone = '')
    {
        if (!$selectedSlots) {
            return '';
        }

        $timezone = sanitize_text_field($timezone) ?: $this->sanitizeTimezone('', Arr::get((array) $event->calendar, 'author_timezone', ''));
        $timezoneObject = new \DateTimeZone($timezone);
        $groupedSlots = [];

        foreach ($selectedSlots as $slot) {
            $startValue = $slot['start'] ?? '';
            $slotUrl = $slot['booking_url'] ?? '';

            if (!$startValue) {
                continue;
            }

            $start = new \DateTime($startValue, $timezoneObject);
            $dateKey = $start->format('Y-m-d');

            $groupedSlots[$dateKey] = $groupedSlots[$dateKey] ?? [
                'heading' => wp_date('l, F j', $start->getTimestamp(), $timezoneObject),
                'slots'   => []
            ];

            $groupedSlots[$dateKey]['slots'][] = [
                'label' => $slot['time_label'],
                'url'   => esc_url($slotUrl)
            ];
        }

        if (!$groupedSlots) {
            return '';
        }

        $eventTitle = '';

        if (!empty($event->title)) {
            $eventTitle = sanitize_text_field($event->title);
        } elseif (!empty($event->calendar_title)) {
            $eventTitle = sanitize_text_field($event->calendar_title);
        }

        $eventDuration = $this->formatMeetingDuration((int) $event->getDuration());
        $html = '<div class="fs_fluent_booking_suggested_times">';

        if ($eventTitle) {
            $html .= '<p class="fs_fluent_booking_suggested_times__title"><strong>' . esc_html($eventTitle) . '</strong></p>';
        }

        if ($eventDuration) {
            $html .= '<p class="fs_fluent_booking_suggested_times__meta">' . esc_html($eventDuration) . '</p>';
        }

        if ($timezone) {
            $html .= '<p class="fs_fluent_booking_suggested_times__meta">';
            $html .= esc_html__('Time zone:', 'fluent-support') . ' ' . esc_html($timezone);
            $html .= '</p>';
        }

        foreach ($groupedSlots as $group) {
            $html .= '<div class="fs_fluent_booking_suggested_times__group">';
            $html .= '<p class="fs_fluent_booking_suggested_times__heading"><strong>' . esc_html($group['heading']) . '</strong></p>';
            $html .= '<div class="fs_fluent_booking_suggested_times__slots">';

            foreach ($group['slots'] as $slot) {
                $slotLabel = esc_html($slot['label']);
                $slotUrl = !empty($slot['url']) ? esc_url($slot['url']) : '';

                if ($slotUrl) {
                    $html .= '<a class="fs_fluent_booking_suggested_times__slot" href="' . $slotUrl . '" target="_blank" rel="noopener">' . $slotLabel . '</a>';
                } else {
                    $html .= '<span class="fs_fluent_booking_suggested_times__slot">' . $slotLabel . '</span>';
                }
            }

            $html .= '</div></div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Plain-text version of the booking suggestion, for clipboard copy.
     *
     * @param string $message
     * @param array  $selectedSlots
     * @param string $bookingUrl
     * @return string
     */
    public function formatSlotsPlainText($message, $selectedSlots, $bookingUrl)
    {
        $parts = [];

        if ($message !== '') {
            $parts[] = wp_strip_all_tags($message);
        }

        $slotLines = [];

        foreach ($selectedSlots as $slot) {
            $displayText = sanitize_text_field(Arr::get($slot, 'display_text'));
            $slotUrl = esc_url_raw(Arr::get($slot, 'booking_url'));

            if ($displayText && $slotUrl) {
                $slotLines[] = "{$displayText}: {$slotUrl}";
            }
        }

        if ($slotLines) {
            $parts[] = implode("\n", $slotLines);
        }

        /* translators: followed by the booking URL. */
        $parts[] = esc_html__('See all available times', 'fluent-support') . ': ' . esc_url_raw($bookingUrl);

        return implode("\n\n", $parts);
    }

    /**
     * Group raw FluentBooking spots by day, limited to the requested range.
     *
     * @param array       $availableSpots
     * @param array       $rangeData
     * @param string      $timezone
     * @param object      $event
     * @param Ticket|null $ticket
     * @param int|null    $duration
     * @return array
     */
    private function formatDays($availableSpots, $rangeData, $timezone, $event, ?Ticket $ticket = null, $duration = null)
    {
        if (!$availableSpots || !is_array($availableSpots)) {
            return [];
        }

        $timezoneObject = new \DateTimeZone($timezone);
        $rangeStart = new \DateTime($rangeData['start'], $timezoneObject);
        $rangeEnd = new \DateTime($rangeData['end'], $timezoneObject);
        $days = [];
        $maxDays = (int) Arr::get($rangeData, 'days', 7);
        $selectedDateMap = array_flip((array) Arr::get($rangeData, 'selected_dates', []));

        foreach ($availableSpots as $date => $spots) {
            if (!is_array($spots)) {
                continue;
            }

            foreach ($spots as $spot) {
                $startValue = sanitize_text_field(Arr::get($spot, 'start'));
                $endValue = sanitize_text_field(Arr::get($spot, 'end'));

                if (!$startValue || !$endValue) {
                    continue;
                }

                $start = new \DateTime($startValue, $timezoneObject);
                $end = new \DateTime($endValue, $timezoneObject);

                if ($start < $rangeStart || $start > $rangeEnd) {
                    continue;
                }

                $dateKey = $start->format('Y-m-d');

                if ($selectedDateMap && !isset($selectedDateMap[$dateKey])) {
                    continue;
                }

                $days[$dateKey] = $days[$dateKey] ?? [
                    'date'  => $dateKey,
                    'label' => wp_date('l, M j', $start->getTimestamp(), $timezoneObject),
                    'slots' => []
                ];

                $timeLabel = wp_date(get_option('time_format'), $start->getTimestamp(), $timezoneObject);
                $endLabel  = wp_date(get_option('time_format'), $end->getTimestamp(), $timezoneObject);

                $days[$dateKey]['slots'][] = [
                    'id'           => sanitize_key($dateKey . '_' . $start->format('His')),
                    'start'        => $start->format('Y-m-d H:i:s'),
                    'booking_url'  => $this->getSlotBookingUrl($event, $ticket, $start, $timezone, $duration),
                    'time_label'   => $timeLabel,
                    'display_text' => sprintf(
                        /* translators: %1$s is date, %2$s is start time, %3$s is end time, %4$s is timezone. */
                        __('%1$s at %2$s - %3$s (%4$s)', 'fluent-support'),
                        wp_date('D, M j', $start->getTimestamp(), $timezoneObject),
                        $timeLabel,
                        $endLabel,
                        $timezone
                    )
                ];
            }
        }

        ksort($days);

        return array_slice(array_values($days), 0, $maxDays);
    }

    /**
     * Valid, unique, sorted Y-m-d dates, at most 7.
     *
     * @param array         $selectedDates
     * @param \DateTimeZone $timezoneObject
     * @return array
     */
    private function sanitizeSpecificDates($selectedDates, \DateTimeZone $timezoneObject)
    {
        if (!is_array($selectedDates)) {
            return [];
        }

        $dates = [];

        foreach (array_slice($selectedDates, 0, 7) as $selectedDate) {
            $selectedDate = sanitize_text_field($selectedDate);

            if (!$selectedDate) {
                continue;
            }

            $date = \DateTime::createFromFormat('Y-m-d', $selectedDate, $timezoneObject);

            if (!$date || $date->format('Y-m-d') !== $selectedDate) {
                continue;
            }

            $dates[] = $selectedDate;
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * Return the Y-m month string, or '' if invalid.
     *
     * @param string        $calendarMonth
     * @param \DateTimeZone $timezoneObject
     * @return string
     */
    private function sanitizeCalendarMonth($calendarMonth, \DateTimeZone $timezoneObject)
    {
        $calendarMonth = sanitize_text_field($calendarMonth);

        if (!$calendarMonth) {
            return '';
        }

        $monthDate = \DateTime::createFromFormat('Y-m', $calendarMonth, $timezoneObject);

        if (!$monthDate || $monthDate->format('Y-m') !== $calendarMonth) {
            return '';
        }

        return $calendarMonth;
    }

    /**
     * Booking URL with the date, time, duration and timezone preselected.
     *
     * @param object      $event
     * @param Ticket|null $ticket
     * @param \DateTime   $start
     * @param string      $timezone
     * @param int|null    $duration
     * @return string
     */
    private function getSlotBookingUrl($event, ?Ticket $ticket, \DateTime $start, $timezone = '', $duration = null)
    {
        $queryArgs = [
            'month'    => $start->format('Y-m'),
            'date'     => $start->format('Y-m-d'),
            'time'     => $start->format('H:i:s'),
            'duration' => (int) $duration,
            'timezone' => sanitize_text_field($timezone)
        ];

        return esc_url_raw(add_query_arg(array_filter($queryArgs), FluentBookingService::getEventUrl($event, $ticket)));
    }

    /**
     * Localised "%d min", or '' for non-positive durations.
     *
     * @param int $duration
     * @return string
     */
    private function formatMeetingDuration($duration)
    {
        $duration = (int) $duration;

        if ($duration <= 0) {
            return '';
        }

        /* translators: %d is meeting duration in minutes. */
        return sprintf(esc_html__('%d min', 'fluent-support'), $duration);
    }
}
