<?php

namespace FluentSupport\App\Services\Integrations;

class TutorLMS {
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getTutorLMSCoursePurchaseWidgets'), 30, 2);
    }

    public function getTutorLMSCoursePurchaseWidgets($widgets, $customer)
    {
        $userId = $customer->user_id;

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

            $courseData[] = [
                'id'         => esc_html($course->ID),
                'title'      => esc_html($course->post_title),
            ];
        }
        ob_start();
        ?>

        <ul>
            <?php foreach ($courseData as $data):?>
                <li title="Purchase Date: <?php echo $data['started_at'] ?>">
                    <?php
                    echo '<code>Course Name:</code> '. $data['title']. '<br>';
                    echo ' <code>Status:</code> Enrolled';
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
