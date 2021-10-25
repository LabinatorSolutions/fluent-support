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
                'id'              => esc_html($course->ID),
                'title'           => esc_html($course->post_title),
                'enrollment_date' => esc_html($student->get_enrollment_date($course->ID, 'enrolled')),
                'status'          => esc_html(llms_get_enrollment_status_name($student->get_enrollment_status($course->ID))),
                'grade'           => esc_html($student->get_grade($course->ID)),
                'progress'        => esc_html($student->get_progress($course->ID, 'course') . '%'),
                'price'           => esc_html(llms_price_raw($course->ID)),
                'completed_at'    => esc_html($student->get_completion_date($course->ID))
            ];
        }

        ob_start();
        ?>

        <ul>
            <?php foreach ($courseData as $data):?>
                <li title="Purchase Date: <?php echo $data['enrollment_date'] ?>">
                    <?php
                    echo $data['title'] . ' <code>' . $data['status'] . '</code>';
                    echo '<br>';
                    echo '<code> Cost:'. $data['price'] .'</code>';
                    echo '<br>';
                    echo '<code> Course Progress:'. $data['progress'] .'</code>';
                    echo '<br>';
                    if ($data['completed_at']){
                        echo '<code> Completed At: '. $data['completed_at'] . '</code>';
                        echo '<br>';
                        echo '<code> Grade: '. $data['grade'] . '</code>';
                        echo '<br>';
                    }
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
