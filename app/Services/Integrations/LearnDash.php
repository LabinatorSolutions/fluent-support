<?php

namespace FluentSupport\App\Services\Integrations;

class LearnDash {
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getLDashCoursePurchaseWidgets'), 30, 2);
    }

    public function getLDashCoursePurchaseWidgets($widgets, $customer)
    {
        $courses = learndash_user_get_enrolled_courses($customer->user_id);

        $enrolledCourses = get_posts([
            'post_status'    => 'publish',
            'post_type'      => 'sfwd-courses',
            'posts_per_page' => 100,
            'post__in'       => $courses,
        ]);

        $courseData = [];
        foreach ($enrolledCourses as $course) {
            $courseData[] = [
                'title'        => esc_html($course->post_title),
                'status'       => esc_html(learndash_course_status($course->ID, $customer->id, false))
            ];
        }

        if(!$courses || !$enrolledCourses || !$courseData){
            return $widgets;
        }

        ob_start();
        ?>

        <ul>
            <?php foreach ($courseData as $data):?>
                <li title="Purchase Date: <?php echo $data['started_at'] ?>">
                    <?php
                    echo '<code>Courese Name:</code> '. $data['title'] . '<br>';
                    echo '<code>Status:</code> '. $data['status'] . '<br>';
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();
        $widgets['lrndesh_purchases'] = [
            'header'    => __('LearnDash Courses', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;

    }
}
