<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\SavedReply;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Request\Request;

class SavedRepliesController extends Controller
{
    public function index(Request $request)
    {
        $replies = SavedReply::orderBy('id', 'DESC')
            ->with(['person', 'product'])
            ->searchBy($request->get('search'))
            ->productBy($request->get('product_id'))
            ->paginate();

        return [
            'replies' => $replies
        ];
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $currentPerson = Helper::getAgentByUserId();

        $data['created_by'] = $currentPerson->id;

        $reply = SavedReply::create($data);

        return [
            'reply'   => $reply,
            'message' => 'Reply Template has been created'
        ];
    }

    public function update(Request $request, $replyId)
    {
        $reply = SavedReply::findOrFail($replyId);
        $data = $request->all();
        $this->validate($data, [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $reply->fill($data);
        $reply->save();


        return [
            'message' => __('Reply Template has been updated', 'fluent-support'),
            'reply'   => $reply
        ];
    }

    public function delete(Request $request, $replyId)
    {
        SavedReply::findOrFail($replyId);
        SavedReply::where('id', $replyId)->delete();

        return [
            'message' => __('Selected Reply Template has been deleted', 'fluent-support')
        ];

    }
}
