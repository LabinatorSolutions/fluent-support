<?php

namespace FluentSupport\App\Services\Integrations;

class LifterLMS {
    public function boot()
    {
        add_filter('fluent_support/customer_extra_widgets', array($this, 'getLLMSCoursePurchaseWidgets'), 30, 2);
    }

    public function getLLMSCoursePurchaseWidgets($widgets, $customer)
    {
        $student = llms_get_student($customer->user_id);

        if(!$student){
            return $widgets;
        }
        $courses = $student->get_courses();

        if(!$courses){
            return $widgets;
        }

        $enrolledCourses = get_posts([
            'post_status'    => 'publish',
            'post_type'      => 'course',
            'posts_per_page' => 100,
            'post__in'       => $courses['results'],
        ]);

        $courseData = [];
        foreach ($enrolledCourses as $course) {
            $courseData[] = [
                'title'           => esc_html($course->post_title),
                'status'          => esc_html(llms_get_enrollment_status_name($student->get_enrollment_status($course->ID)))
            ];
        }

        ob_start();
        ?>

        <ul>
            <?php foreach ($courseData as $data):?>
                <li title="Purchase Date: <?php echo $data['enrollment_date'] ?>">
                    <?php
                    echo '<code>Course Name:</code> '. $data['title']. '<br>';
                    echo '<code>Status:</code> '. $data['status']. '';
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $content = ob_get_clean();

        $widgets['llms_purchases'] = [
            'header'    => __('Lifter LMS Courses', 'fluent-support'),
            'body_html' => $content
        ];
        return $widgets;
    }
}
