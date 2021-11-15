<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\App;
use FluentSupport\Framework\Request\Request;

class DocSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $search = sanitize_text_field($request->search);

        $result = [];

        if ($search) {
            $search = apply_filters('fluent-support/search_doc_query', $search);
            $postTypes = apply_filters('fluent-support/search_doc_post_types', ['post']);

            $posts = get_posts([
                'post_type'   => $postTypes,
                's'           => $search,
                'numberposts' => $request->get('per_page', 5)
            ]);

            foreach ($posts as $item) {
                $result[] = [
                    'title' => $item->post_title,
                    'link'  => get_permalink($item)
                ];
            }
        }

        return apply_filters('fluent-support/search_doc_result', $result);
    }
}
