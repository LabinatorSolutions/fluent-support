<?php

namespace FluentSupport\App\Services\Integrations;

class TutorLMS {
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getTutorLMSCoursePurchaseWidgets'), 30, 2);
    }

    public function getTutorLMSCoursePurchaseWidgets($widgets, $customer)
    {
        $userId = $customer->id;

        if (!$userId) {
            return $widgets;
        }

        $courseIds = tutor_utils()->get_enrolled_courses_ids_by_user($userId);

        if (empty($courseIds)) {
            return $widgets;
        }

        $enrolledCourses = get_posts([
            'post_type'      => tutor()->course_post_type,
            'posts_per_page' => 100,
            'post__in'       => $courseIds,
        ]);


        $courseData = [];
        foreach ($enrolledCourses as $course) {
            $enrolled = wpFluent()->table('posts')
                ->where('post_parent', $course->ID)
                ->where('post_author', $userId)
                ->where('post_type', 'tutor_enrolled')
                ->first();

            $completed_count = tutor_utils()->get_course_completed_percent($course->ID, $userId);

            $courseData[] = [
                'id'         => esc_html($course->ID),
                'title'      => esc_html($course->post_title),
                'progress'   => esc_html($completed_count . '%'),
                'started_at' => esc_html(date_i18n(get_option('date_format'), strtotime($enrolled->post_date)))
            ];
        }
        ob_start();
        ?>

        <ul>
            <?php foreach ($courseData as $data):?>
                <li title="Purchase Date: <?php echo $data['started_at'] ?>">
                    <?php
                    echo $data['title'];
                    echo ' <code>Progress:'. $data['progress'] .'</code>';
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['tlms_purchases'] = [
            'header'    => __('Tutor LMS Courses', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
