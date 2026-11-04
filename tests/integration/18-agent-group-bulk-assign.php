<?php
/**
 * Bulk "assign via agent group" must pick a member with zero open tickets
 * without raising a PHP warning, and must skip a ticket cleanly when every
 * member is mailbox-restricted.
 *
 * AgentGroup::getLeastLoadedAgent() seeds $currentCounts from a GROUP BY
 * query over open tickets, so a member with no open tickets gets no key in
 * that array. The bulk-assign loop used to increment that key directly
 * ($currentCounts[$id]++), which raised "Undefined array key" the first
 * time such an agent was picked. The harness fails any case that raises a
 * PHP diagnostic inside the plugin, so no explicit warning assertion is
 * needed below -- it is implicit in every case passing at all.
 */

use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;

return function () {

    $marker = FsFactory::marker();

    /**
     * Create an agent group and attach the given agents to it via
     * fs_tag_pivot (source_type = agent_group).
     *
     * @param array $agents
     * @return AgentGroup
     */
    $makeGroup = function (array $agents) use ($marker) {
        $group = AgentGroup::create([
            'title' => 'Fixture Agent Group ' . $marker . '-' . FsTest::uniq('ag'),
        ]);

        foreach ($agents as $agent) {
            TagPivot::create([
                'tag_id'      => $group->id,
                'source_id'   => $agent->id,
                'source_type' => 'agent_group',
            ]);
        }

        return $group;
    };

    /**
     * Remove a fixture group and its pivot rows. FsFactory has no resolver
     * for agent groups yet, so cleanup is manual here.
     *
     * @param AgentGroup $group
     * @return void
     */
    $dropGroup = function (AgentGroup $group) {
        TagPivot::where('tag_id', $group->id)->where('source_type', 'agent_group')->delete();
        AgentGroup::where('id', $group->id)->delete();
    };

    FsTest::case('assign_agent_group_picks_the_zero_open_ticket_agent_without_a_warning', function () use ($marker, $makeGroup, $dropGroup) {
        $busyAgent = FsFactory::agent();
        $idleAgent = FsFactory::agent();
        $mailbox = FsFactory::mailbox();

        // Give busyAgent open tickets so the seeding query returns a row for
        // them but none for idleAgent -- the exact shape that used to warn.
        // Two (not one) keeps idleAgent's count strictly lower, so the pick
        // is deterministic regardless of the group's iteration order.
        FsFactory::ticket([
            'mailbox_id' => $mailbox->id,
            'agent_id'   => $busyAgent->id,
            'status'     => 'active',
            'title'      => 'Agent group pre-existing one ' . $marker,
        ]);
        FsFactory::ticket([
            'mailbox_id' => $mailbox->id,
            'agent_id'   => $busyAgent->id,
            'status'     => 'active',
            'title'      => 'Agent group pre-existing two ' . $marker,
        ]);

        $group = $makeGroup([$busyAgent, $idleAgent]);

        $target = FsFactory::ticket([
            'mailbox_id' => $mailbox->id,
            'title'      => 'Agent group target ' . $marker,
        ]);

        try {
            $result = FsTest::rest('POST', '/tickets/bulk-actions', [
                'bulk_action'    => 'assign_agent_group',
                'ticket_ids'     => [$target->id],
                'agent_group_id' => $group->id,
            ]);

            FsTest::assertHealthy($result, 'POST /tickets/bulk-actions (assign_agent_group)');

            $reloaded = Ticket::find($target->id);
            FsTest::assertSame((int) $idleAgent->id, (int) $reloaded->agent_id,
                'assign_agent_group did not pick the agent with zero open tickets');
        } finally {
            $dropGroup($group);
        }
    });

    FsTest::case('assign_agent_group_skips_a_ticket_when_every_member_is_mailbox_restricted', function () use ($marker, $makeGroup, $dropGroup) {
        $agentA = FsFactory::agent();
        $agentB = FsFactory::agent();
        $mailbox = FsFactory::mailbox();

        foreach ([$agentA, $agentB] as $agent) {
            $agent->updateMeta('agent_restrictions', [
                'businessBoxRestrictions' => true,
                'restrictedBusinessBoxes' => [(int) $mailbox->id],
            ]);
        }

        $group = $makeGroup([$agentA, $agentB]);

        $target = FsFactory::ticket([
            'mailbox_id' => $mailbox->id,
            'title'      => 'Agent group all-restricted ' . $marker,
        ]);

        try {
            $result = FsTest::rest('POST', '/tickets/bulk-actions', [
                'bulk_action'    => 'assign_agent_group',
                'ticket_ids'     => [$target->id],
                'agent_group_id' => $group->id,
            ]);

            FsTest::assertHealthy($result, 'POST /tickets/bulk-actions (assign_agent_group, all restricted)');

            $reloaded = Ticket::find($target->id);
            FsTest::assert($reloaded->agent_id === null,
                'ticket was assigned despite every group member being restricted from its mailbox');
        } finally {
            $dropGroup($group);
        }
    });
};
