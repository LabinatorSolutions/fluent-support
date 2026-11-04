<?php

namespace FluentSupport\App\Modules\Agents;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Http\Controllers\Controller;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\AvatarUploder;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Http\Request\Request;

/**
 * Support staff on the settings page. An agent is a WordPress user with
 * plugin permissions and a few settings kept in person meta. Agents are sent
 * from an allow list: never the portal hash, IP addresses or remote ids.
 */
class AgentController extends Controller
{
    const FIELDS = ['id', 'first_name', 'last_name', 'full_name', 'email', 'title', 'photo', 'avatar', 'user_id', 'status', 'person_type', 'created_at', 'updated_at'];

    /**
     * Settings in person meta, with the value used when none is saved.
     */
    const SETTINGS = [
        'telegram_chat_id'        => '',
        'slack_user_id'           => '',
        'whatsapp_number'         => '',
        'agent_restrictions'      => ['restrictedBusinessBoxes' => [], 'businessBoxRestrictions' => false],
        'agent_signature'         => '',
        'agent_signature_enabled' => 'no',
    ];

    /**
     * Agents, newest first, with their groups, permissions, reply counts and
     * settings; plus the permission groups and mailboxes to choose from.
     */
    public function index(Request $request)
    {
        $page = Agent::latest()
            ->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->with(['groups' => fn($query) => $query->select(['fs_taggables.id', 'title'])])
            ->paginate();

        $ids = $page->pluck('id')->all() ?: [0];
        $replies = Conversation::whereIn('person_id', $ids)->groupBy('person_id')->selectRaw('person_id, COUNT(*) AS total')->pluck('total', 'person_id')->all();
        $interactions = Conversation::whereIn('person_id', $ids)->groupBy('person_id')->selectRaw('person_id, COUNT(DISTINCT ticket_id) AS total')->pluck('total', 'person_id')->all();

        // The oldest row per agent and key wins, as getMeta() reads it.
        $settings = [];
        foreach (Meta::where('object_type', 'person_meta')->whereIn('object_id', $ids)->whereIn('key', array_keys(self::SETTINGS))->orderBy('id')->get(['object_id', 'key', 'value']) as $row) {
            $settings[$row->object_id][$row->key] = $settings[$row->object_id][$row->key] ?? Helper::safeUnserialize($row->value);
        }

        // One query for the users and their meta, for the permission lookups.
        cache_users(array_filter(array_map('intval', $page->pluck('user_id')->all())));

        $rows = [];
        foreach ($page as $agent) {
            $rows[] = $this->format($agent, $settings[$agent->id] ?? []) + [
                'groups'             => $agent->groups->map(fn($group) => ['id' => (int) $group->id, 'title' => $group->title])->all(),
                'permissions'        => PermissionManager::getUserPermissions($agent->user_id),
                'replies_count'      => (int) ($replies[$agent->id] ?? 0),
                'interactions_count' => (int) ($interactions[$agent->id] ?? 0),
            ] + ($agent->user_id ? ['user_profile' => admin_url('user-edit.php?user_id=' . $agent->user_id)] : []);
        }

        $data = $page->toArray();
        $data['data'] = $rows;

        return [
            'agents'        => $data,
            'permissions'   => PermissionManager::getReadablePermissionGroups(),
            'businessBoxes' => PermissionManager::getMailboxesForRestriction(),
        ];
    }

    /**
     * Makes the WordPress user with this email an agent, saves permissions and
     * settings, and adds them to the default agent group.
     */
    public function create(Request $request)
    {
        $email = $request->getSafe('email', 'sanitize_email');
        $names = $this->names($request);
        $user = $email ? get_user_by('email', $email) : false;

        if (!$user) {
            throw ApiException::invalid(__('Sorry, Connected user could not be found with the provided email address', 'fluent-support'));
        }
        if (Person::where('email', $email)->exists()) {
            throw ApiException::invalid(__('Sorry, Another agent/person exist with the same email address. Please use a different email address', 'fluent-support'));
        }

        $agent = Agent::create([
            'email'      => $email,
            'user_id'    => $user->ID,
            'first_name' => $names['first_name'] ?: $user->first_name,
            'last_name'  => ($names['last_name'] ?? '') ?: $user->last_name,
            'title'      => $names['title'] ?? '',
        ]);
        $this->saveSettings($agent, $user, $request, true);

        $default = AgentGroup::whereNotNull('settings')->get()->first(fn($group) => !empty($group->settings['is_default']));
        if ($default) {
            TagPivot::create(['tag_id' => $default->id, 'source_id' => $agent->id, 'source_type' => 'agent_group']);
        }

        return [
            'message' => __('Support Staff has been added', 'fluent-support'),
            'agent'   => $this->format($agent, $this->settings($agent))
        ];
    }

    /**
     * Saves names, title, permissions and settings. The email is always the
     * WordPress user's.
     */
    public function update(Request $request, $agent_id)
    {
        $agent = $this->find($agent_id);
        $user = $agent->user_id ? get_user_by('ID', $agent->user_id) : get_user_by('email', $agent->email);

        if (!$user) {
            throw ApiException::invalid(__('Sorry, Connected user could not be found with the provided email address', 'fluent-support'));
        }

        Agent::where('id', $agent->id)->update(['user_id' => $user->ID, 'email' => $user->user_email] + $this->names($request));
        $agent = Agent::find($agent->id);
        $this->saveSettings($agent, $user, $request);

        return [
            'message' => __('Support Staff has been updated', 'fluent-support'),
            'agent'   => $this->format($agent, $this->settings($agent))
        ];
    }

    /**
     * Deletes the agent. Their tickets, replies, attachments and products move
     * to fallback_agent_id; their permissions, settings and group memberships go.
     */
    public function delete(Request $request, $agent_id)
    {
        $agent = $this->find($agent_id);
        $fallback = Agent::find($request->getInt('fallback_agent_id'));

        if (!$fallback || (int) $fallback->id === (int) $agent->id) {
            throw ApiException::invalid(__('Choose another support staff to take over this one\'s tickets.', 'fluent-support'));
        }

        Ticket::where('agent_id', $agent->id)->update(['agent_id' => $fallback->id]);
        Conversation::where('person_id', $agent->id)->update(['person_id' => $fallback->id]);
        Attachment::where('person_id', $agent->id)->update(['person_id' => $fallback->id]);
        Product::where('created_by', $agent->id)->update(['created_by' => $fallback->id]);

        PermissionManager::detachPermissions($agent->user_id);
        TagPivot::where('source_id', $agent->id)->where('source_type', 'agent_group')->delete();
        $agent->deleteAllMeta();
        $agent->delete();

        return ['message' => __('Support Staff has been deleted', 'fluent-support')];
    }

    public function uploadAvatar(Request $request, AvatarUploder $uploader)
    {
        try {
            return $uploader->addOrUpdateProfileImage($request->files(), $request->getInt('agent_id'), 'agent');
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage(), (int) $e->getCode());
        }
    }

    public function resetAvatar($agent)
    {
        $this->find($agent)->restoreAvatar();

        return ['message' => __('Support Staff avatar reset to gravatar default', 'fluent-support')];
    }

    /**
     * Keeps the admin session alive.
     */
    public function ping()
    {
        return ['ping' => 'pong'];
    }

    private function find($agentId)
    {
        $agent = Agent::find((int) $agentId);
        if (!$agent) {
            throw ApiException::notFound();
        }

        return $agent;
    }

    /**
     * first_name (required), and last_name and title when sent.
     */
    private function names(Request $request)
    {
        $names = ['first_name' => $request->getSafe('first_name', 'sanitize_text_field')];
        if (!$names['first_name']) {
            throw ApiException::invalid(__('First name is required', 'fluent-support'));
        }
        foreach (['last_name', 'title'] as $key) {
            if ($request->has($key)) {
                $names[$key] = $request->getSafe($key, 'sanitize_text_field');
            }
        }

        return $names;
    }

    /**
     * The permissions and settings sent (a new agent always gets its permissions set).
     */
    private function saveSettings(Agent $agent, $user, Request $request, $isNew = false)
    {
        if ($isNew || $request->has('permissions')) {
            PermissionManager::attachPermissions($user, array_map('sanitize_key', (array) $request->get('permissions', [])));
        }

        foreach (['telegram_chat_id', 'slack_user_id', 'whatsapp_number'] as $key) {
            if ($request->has($key)) {
                $agent->updateMeta($key, $request->getSafe($key, 'sanitize_text_field'));
            }
        }

        if ($request->has('restrictions')) {
            $restrictions = (array) $request->get('restrictions');
            $agent->updateMeta('agent_restrictions', [
                'restrictedBusinessBoxes' => array_map('absint', (array) ($restrictions['restrictedBusinessBoxes'] ?? [])),
                'businessBoxRestrictions' => rest_sanitize_boolean($restrictions['businessBoxRestrictions'] ?? false),
            ]);
            AgentTicketAccess::resetCache();
        }

        if ($request->has('agent_signature')) {
            // Already unslashed by the request; unslashing again loses literal backslashes.
            $agent->updateMeta('agent_signature', wp_kses_post($request->get('agent_signature', '')));
        }
        if ($request->has('agent_signature_enabled')) {
            $agent->updateMeta('agent_signature_enabled', $request->get('agent_signature_enabled') === 'yes' ? 'yes' : 'no');
        }
    }

    private function settings(Agent $agent)
    {
        $settings = [];
        foreach (array_keys(self::SETTINGS) as $key) {
            $settings[$key] = $agent->getMeta($key);
        }

        return $settings;
    }

    /**
     * The agent's fields and settings (an empty setting reads as its default).
     */
    private function format(Agent $agent, array $settings)
    {
        $data = array_intersect_key($agent->toArray(), array_flip(self::FIELDS));
        foreach (self::SETTINGS as $key => $default) {
            $data[$key === 'agent_restrictions' ? 'restrictions' : $key] = ($settings[$key] ?? null) ?: $default;
        }

        return $data;
    }
}
