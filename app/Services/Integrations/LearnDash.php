<?php

namespace FluentSupport\App\Services\Integrations;

class LearnDash {
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getLDashCoursePurchaseWidgets'), 30, 2);
    }

    public function getLDashCoursePurchaseWidgets($widgets, $customer)
    {
        $courses = learndash_user_get_enrolled_courses($customer->id);

        $enrolledCourses = get_posts([
            'post_status'    => 'publish',
            'post_type'      => 'sfwd-courses',
            'posts_per_page' => 100,
            'post__in'       => $courses,
        ]);

        $courseData = [];
        foreach ($enrolledCourses as $course) {
            $completedAt = get_user_meta($customer->id, 'course_completed_' . $course->ID, true);
            $startAt = get_user_meta($customer->id, 'course_' . $course->ID . '_access_from', true);

            $courseData[] = [
                'id'           => esc_html($course->ID),
                'title'        => esc_html($course->post_title),
                'status'       => esc_html(learndash_course_status($course->ID, $customer->id, false)),
                'completed_at' => esc_html(($completedAt) ? gmdate('Y-m-d H:i', $completedAt) : ''),
                'started_at'   => esc_html(($startAt) ? gmdate('Y-m-d H:i', $startAt) : ''),
                'price'        => learndash_get_course_price($course->ID, $customer->id, false)
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
                    echo $data['title'] . ' <code>' . $data['status'] . '</code>';
                    echo '<br>';
                    echo'<code> Type: '. $data['price']['type'] . '</code>';
                    echo '<br>';
                    if ($data['price']['frequency']){
                        echo '<code> Frequency: '. $data['price']['frequency'] . '</code>';
                        echo '<br>';
                        echo '<code> Interval: '. $data['price']['interval'] . '</code>';
                        echo '<br>';
                        echo '<code> Cost: $'. $data['price']['price'] . '</code>';
                    }
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
