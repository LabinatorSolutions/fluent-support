<?php

namespace FluentSupport\App\Modules\AgentGroups;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Agent groups on the settings page. Members are fs_tag_pivot rows
 * (source_type agent_group); one group may be the default that new agents join.
 * Assigning tickets to a group is AgentGroup::getLeastLoadedAgent().
 */
class AgentGroupController extends Controller
{
    public function index(Request $request)
    {
        $page = AgentGroup::orderBy('id', 'DESC')
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->paginate()
            ->toArray();

        $counts = TagPivot::where('source_type', 'agent_group')
            ->whereIn('tag_id', array_column($page['data'], 'id') ?: [0])
            ->selectRaw('tag_id, COUNT(*) as agents_count')
            ->groupBy('tag_id')
            ->pluck('agents_count', 'tag_id')
            ->toArray();

        $page['data'] = array_map(fn($group) => $this->format($group) + ['agents_count' => (int) ($counts[$group['id']] ?? 0)], $page['data']);

        return ['groups' => $page];
    }

    public function get($group_id)
    {
        $group = $this->find($group_id);

        return [
            'group'  => $this->format($group->toArray()) + ['agent_ids' => $this->members($group->id)],
            'agents' => Agent::select(['id', 'first_name', 'last_name'])->get()->map(fn($agent) => [
                'id'         => (int) $agent->id,
                'first_name' => $agent->first_name,
                'last_name'  => $agent->last_name,
            ])->toArray()
        ];
    }

    public function create(Request $request)
    {
        $data = $this->input($request);

        $group = AgentGroup::create([
            'title'       => $data['title'],
            'description' => $data['description'],
            'settings'    => ['is_default' => $data['is_default']],
        ]);
        $this->setMembers($group->id, $data['agent_ids']);
        $this->keepOneDefault($group);

        return [
            'message' => __('Agent group has been created', 'fluent-support'),
            'group'   => $this->format($group->toArray())
        ];
    }

    public function update(Request $request, $group_id)
    {
        $group = $this->find($group_id);
        $data = $this->input($request);

        // Only what is sent: an update without them keeps the description and default flag.
        $group->title = $data['title'];
        if ($request->has('description')) {
            $group->description = $data['description'];
        }
        if ($request->has('is_default')) {
            $group->settings = ['is_default' => $data['is_default']] + ($group->settings ?: []);
        }
        $group->save();

        if ($request->has('agent_ids')) {
            $this->setMembers($group->id, $data['agent_ids']);
        }
        $this->keepOneDefault($group);

        return [
            'message' => __('Agent group has been updated', 'fluent-support'),
            'group'   => $this->format($group->toArray())
        ];
    }

    /**
     * Deletes the group and moves its members to the fallback group.
     */
    public function delete(Request $request, $group_id)
    {
        $group = $this->find($group_id);
        $fallbackId = $request->getInt('fallback_group_id');

        if (!$fallbackId || $fallbackId === (int) $group->id) {
            throw ApiException::invalid(__('A valid fallback group is required', 'fluent-support'));
        }
        if (!AgentGroup::find($fallbackId)) {
            throw ApiException::invalid(__('The selected fallback group does not exist', 'fluent-support'));
        }

        $this->setMembers($fallbackId, array_merge($this->members($fallbackId), $this->members($group->id)));
        $this->setMembers($group->id, []);
        $group->delete();

        return [
            'message' => __('Agent group has been deleted and agents have been reassigned', 'fluent-support')
        ];
    }

    private function find($groupId)
    {
        $group = AgentGroup::find($groupId);

        if (!$group) {
            throw ApiException::notFound();
        }

        return $group;
    }

    private function input(Request $request)
    {
        $title = $request->getSafe('title', 'sanitize_text_field');

        if (!$title) {
            throw ApiException::invalid(__('The title field is required.', 'fluent-support'));
        }

        return [
            'title'       => $title,
            'description' => $request->getSafe('description', 'sanitize_textarea_field'),
            'is_default'  => rest_sanitize_boolean($request->get('is_default', false)),
            'agent_ids'   => array_map('intval', (array) $request->get('agent_ids', [])),
        ];
    }

    private function format(array $group)
    {
        return array_intersect_key($group, array_flip(['id', 'title', 'slug', 'description', 'settings', 'created_at', 'updated_at']));
    }

    /**
     * @return int[] the agent ids in the group
     */
    private function members($groupId)
    {
        return array_map('intval', TagPivot::where('tag_id', $groupId)
            ->where('source_type', 'agent_group')
            ->pluck('source_id')
            ->toArray());
    }

    /**
     * Replaces the group's members. Ids that aren't agents are dropped.
     */
    private function setMembers($groupId, array $agentIds)
    {
        TagPivot::where('tag_id', $groupId)->where('source_type', 'agent_group')->delete();

        $agentIds = $agentIds ? Agent::whereIn('id', array_unique($agentIds))->pluck('id')->toArray() : [];

        foreach ($agentIds as $agentId) {
            TagPivot::create([
                'tag_id'      => $groupId,
                'source_id'   => (int) $agentId,
                'source_type' => 'agent_group',
            ]);
        }
    }

    /**
     * When $group is the default, no other group is.
     */
    private function keepOneDefault(AgentGroup $group)
    {
        if (empty($group->settings['is_default'])) {
            return;
        }

        foreach (AgentGroup::where('id', '!=', $group->id)->whereNotNull('settings')->get() as $other) {
            /** @var AgentGroup $other */
            if (!empty($other->settings['is_default'])) {
                $other->settings = ['is_default' => false] + $other->settings;
                $other->save();
            }
        }
    }
}
