<?php
/**
 * Exact-ID factories for Fluent Support integration tests.
 *
 * Every fixture carries the run marker in a stable field. Cleanup deletes by
 * recorded ID only, checks the marker first, and then proves the rows are gone.
 *
 * Table map (all under the lab's non-default WP prefix):
 *   fs_persons     Customer + Agent share it, discriminated by person_type
 *   fs_taggables   Tag rows
 *   fs_tag_pivot   ticket<->tag pivot
 *   fs_meta        shared key/value, discriminated by object_type
 */

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\SavedReply;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Tag;
use FluentSupport\App\Models\Ticket;

class FsFactory
{
    /** @var string|null One collision-free ownership marker for this process. */
    private static $marker = null;

    /** @var array<string,int> Per-fixture counters used in readable defaults. */
    private static $counters = [];

    /**
     * Recorded primary IDs and their ownership marker.
     *
     * @var array<string,array<int,string>>
     */
    private static $created = [
        'customers'     => [],
        'agents'        => [],
        'mailboxes'     => [],
        'products'      => [],
        'tags'          => [],
        'tickets'       => [],
        'conversations' => [],
        'saved_replies' => [],
    ];

    /**
     * Return the run marker used by every fixture made in this process.
     *
     * @return string
     */
    public static function marker()
    {
        if (self::$marker === null) {
            self::$marker = FsTest::uniq('fsfixture');
        }

        return self::$marker;
    }

    /**
     * Create one customer person row with a marker-bearing email.
     *
     * @param array<string,mixed> $attributes
     * @return Customer
     */
    public static function customer(array $attributes = [])
    {
        $suffix = self::nextSuffix('customer');
        $defaults = [
            'email'      => self::marker() . '-customer-' . $suffix . '@example.invalid',
            'first_name' => 'Fixture ' . self::marker(),
            'last_name'  => (string) $suffix,
            'status'     => 'active',
        ];

        $customer = Customer::create(array_merge($defaults, $attributes));
        self::record('customers', $customer->id);

        return $customer;
    }

    /**
     * Create one agent person row with a marker-bearing email.
     *
     * @param array<string,mixed> $attributes
     * @return Agent
     */
    public static function agent(array $attributes = [])
    {
        $suffix = self::nextSuffix('agent');
        $defaults = [
            'email'      => self::marker() . '-agent-' . $suffix . '@example.invalid',
            'first_name' => 'Fixture ' . self::marker(),
            'last_name'  => (string) $suffix,
            'status'     => 'active',
        ];

        $agent = Agent::create(array_merge($defaults, $attributes));
        self::record('agents', $agent->id);

        return $agent;
    }

    /**
     * Create one mailbox with the marker in its name and email.
     *
     * @param array<string,mixed> $attributes
     * @return MailBox
     */
    public static function mailbox(array $attributes = [])
    {
        $suffix = self::nextSuffix('mailbox');
        $defaults = [
            'name'     => self::tagTitle('Fixture Mailbox ' . $suffix),
            'email'    => self::marker() . '-box-' . $suffix . '@example.invalid',
            'box_type' => 'web',
            'settings' => ['admin_email_address' => ''],
        ];

        $mailbox = MailBox::create(array_merge($defaults, $attributes));
        self::record('mailboxes', $mailbox->id);

        return $mailbox;
    }

    /**
     * Create one product with the marker in its title.
     *
     * @param array<string,mixed> $attributes
     * @return Product
     */
    public static function product(array $attributes = [])
    {
        $suffix = self::nextSuffix('product');
        $product = Product::create(array_merge([
            'title' => self::tagTitle('Fixture Product ' . $suffix),
        ], $attributes));
        self::record('products', $product->id);

        return $product;
    }

    /**
     * Create one tag with the marker in its title.
     *
     * @param array<string,mixed> $attributes
     * @return Tag
     */
    public static function tag(array $attributes = [])
    {
        $suffix = self::nextSuffix('tag');
        $title = self::tagTitle('Fixture Tag ' . $suffix);
        $tag = Tag::create(array_merge([
            'title' => $title,
            'slug'  => sanitize_title($title),
        ], $attributes));
        self::record('tags', $tag->id);

        return $tag;
    }

    /**
     * Create one ticket linked only to factory-owned (or explicitly passed)
     * parent rows. Creates its own customer/mailbox when none are given.
     *
     * @param array<string,mixed> $attributes
     * @return Ticket
     */
    public static function ticket(array $attributes = [])
    {
        $suffix = self::nextSuffix('ticket');

        if (empty($attributes['customer_id'])) {
            $attributes['customer_id'] = self::customer()->id;
        } else {
            self::assertRecordedId('customers', $attributes['customer_id']);
        }

        if (empty($attributes['mailbox_id'])) {
            $attributes['mailbox_id'] = self::mailbox()->id;
        } else {
            self::assertRecordedId('mailboxes', $attributes['mailbox_id']);
        }

        $title = isset($attributes['title']) && $attributes['title'] !== ''
            ? $attributes['title']
            : 'Fixture Ticket ' . $suffix;
        $title = self::tagTitle($title);

        $defaults = [
            'priority' => 'normal',
            'status'   => 'active',
            'title'    => $title,
            'slug'     => sanitize_title($title),
            'hash'     => md5(self::marker() . '-' . $suffix),
            'source'   => 'web',
            'content'  => '<p>Fixture ticket body ' . esc_html(self::marker()) . '</p>',
        ];

        $ticket = Ticket::create(array_merge($defaults, $attributes, ['title' => $title]));
        self::record('tickets', $ticket->id);

        return $ticket;
    }

    /**
     * Create one conversation on a factory-owned ticket.
     *
     * @param Ticket              $ticket
     * @param array<string,mixed> $attributes
     * @return Conversation
     */
    public static function conversation(Ticket $ticket, array $attributes = [])
    {
        self::assertRecordedId('tickets', $ticket->id);

        $defaults = [
            'ticket_id'         => $ticket->id,
            'person_id'         => $ticket->customer_id,
            'conversation_type' => 'response',
            'content'           => '<p>Fixture reply ' . esc_html(self::marker()) . '</p>',
            'source'            => 'web',
        ];
        $data = array_merge($defaults, $attributes);

        if ((int) $data['ticket_id'] !== (int) $ticket->id) {
            throw new LogicException('Conversation ticket_id must remain factory-owned.');
        }

        $conversation = Conversation::create($data);
        self::record('conversations', $conversation->id);

        return $conversation;
    }

    /**
     * Create one saved reply with the marker in its title.
     *
     * @param array<string,mixed> $attributes
     * @return SavedReply
     */
    public static function savedReply(array $attributes = [])
    {
        $suffix = self::nextSuffix('saved_reply');
        $title = self::tagTitle('Fixture Saved Reply ' . $suffix);
        $reply = SavedReply::create(array_merge([
            'title'      => $title,
            'slug'       => sanitize_title($title),
            'content'    => '<p>Fixture saved reply ' . esc_html(self::marker()) . '</p>',
            'created_by' => get_current_user_id(),
        ], $attributes));
        self::record('saved_replies', $reply->id);

        return $reply;
    }

    /**
     * Attach a factory-owned tag to a factory-owned ticket via the pivot.
     *
     * @param Ticket $ticket
     * @param Tag    $tag
     * @return void
     */
    public static function attachTag(Ticket $ticket, Tag $tag)
    {
        self::assertRecordedId('tickets', $ticket->id);
        self::assertRecordedId('tags', $tag->id);

        // fs_tag_pivot is polymorphic and has no ticket_id column.
        TagPivot::create([
            'tag_id'      => $tag->id,
            'source_id'   => $ticket->id,
            'source_type' => 'ticket_tag',
        ]);
    }

    /**
     * Adopt a ticket created through the REST API into exact-ID cleanup.
     * The caller must put this run's marker in the title.
     *
     * @param Ticket $ticket
     * @return Ticket
     */
    public static function adoptRestTicket(Ticket $ticket)
    {
        if (strpos((string) $ticket->title, self::marker()) === false) {
            throw new LogicException('Cannot adopt REST ticket without fixture marker.');
        }

        self::record('tickets', $ticket->id);

        return $ticket;
    }

    /**
     * Adopt a customer created through REST (marker required in email).
     *
     * @param Customer $customer
     * @return Customer
     */
    public static function adoptRestCustomer(Customer $customer)
    {
        if (strpos((string) $customer->email, self::marker()) === false) {
            throw new LogicException('Cannot adopt REST customer without fixture marker.');
        }

        self::record('customers', $customer->id);

        return $customer;
    }

    /**
     * Delete every recorded fixture in dependency-safe order.
     *
     * Rows are marker-verified before any delete. A successful run clears the
     * registry, so a second call is a no-op.
     *
     * @return void
     */
    public static function cleanup()
    {
        if (!self::hasCreatedFixtures()) {
            return;
        }

        self::verifyOwnedPrimaryRows();

        $customerIds = self::ids('customers');
        $agentIds = self::ids('agents');
        $mailboxIds = self::ids('mailboxes');
        $productIds = self::ids('products');
        $tagIds = self::ids('tags');
        $ticketIds = self::ids('tickets');
        $conversationIds = self::ids('conversations');
        $savedReplyIds = self::ids('saved_replies');

        // Per model instance so Ticket::deleting cascades meta, notifications,
        // conversations and attachments. A bulk delete skips those hooks.
        self::deleteModelInstances(Ticket::class, $ticketIds);

        // Covers conversations on tickets this run doesn't own, e.g. a reply on
        // the seed ticket.
        self::deleteModelInstances(Conversation::class, $conversationIds);

        // Tag pivots are not covered by any model cascade.
        if ($ticketIds) {
            TagPivot::where('source_type', 'ticket_tag')
                ->whereIn('source_id', $ticketIds)
                ->delete();
        }
        if ($tagIds) {
            TagPivot::whereIn('tag_id', $tagIds)->delete();
        }
        self::deleteByIds(SavedReply::class, $savedReplyIds);
        self::deleteByIds(Tag::class, $tagIds);
        self::deleteByIds(Product::class, $productIds);

        // The models' global scopes filter fs_persons by person_type.
        self::deleteByIds(Customer::class, $customerIds);
        self::deleteByIds(Agent::class, $agentIds);
        self::deleteByIds(MailBox::class, $mailboxIds);

        self::assertPrimaryRowsDeleted();
        foreach (self::$created as $type => $records) {
            self::$created[$type] = [];
        }
    }

    /**
     * Store one positive primary ID under its fixture type.
     *
     * @param string $type
     * @param int    $id
     * @return void
     */
    private static function record($type, $id)
    {
        $id = (int) $id;
        if ($id <= 0 || !isset(self::$created[$type])) {
            throw new LogicException('Cannot record invalid fixture ID for ' . $type . '.');
        }

        self::$created[$type][$id] = self::marker();
    }

    /**
     * Assert an ID belongs to this factory run before linking/deleting it.
     *
     * @param string $type
     * @param int    $id
     * @return void
     */
    private static function assertRecordedId($type, $id)
    {
        $id = (int) $id;
        if (!isset(self::$created[$type][$id]) || self::$created[$type][$id] !== self::marker()) {
            throw new LogicException('Unowned ' . $type . ' fixture ID: ' . $id . '.');
        }
    }

    /**
     * Verify stable marker fields before any existing primary row is deleted.
     *
     * @return void
     */
    private static function verifyOwnedPrimaryRows()
    {
        $checks = [
            'customers'     => [Customer::class, 'email'],
            'agents'        => [Agent::class, 'email'],
            'mailboxes'     => [MailBox::class, 'email'],
            'products'      => [Product::class, 'title'],
            'tags'          => [Tag::class, 'title'],
            'tickets'       => [Ticket::class, 'title'],
            'saved_replies' => [SavedReply::class, 'title'],
        ];

        foreach ($checks as $type => $check) {
            list($modelClass, $field) = $check;
            foreach (self::ids($type) as $id) {
                $row = $modelClass::find($id);
                if ($row && strpos((string) $row->{$field}, self::marker()) === false) {
                    throw new LogicException(
                        'Refusing to delete ' . $type . ' row without fixture marker: ' . $id . '.'
                    );
                }
            }
        }

        // Conversations carry the marker in their content.
        foreach (self::ids('conversations') as $id) {
            $row = Conversation::find($id);
            if ($row && strpos((string) $row->content, self::marker()) === false) {
                throw new LogicException(
                    'Refusing to delete conversation without fixture marker: ' . $id . '.'
                );
            }
        }
    }

    /**
     * Delete primary rows only after checking every target is recorded.
     *
     * @param class-string $modelClass
     * @param array<int>   $ids
     * @return void
     */
    private static function deleteByIds($modelClass, array $ids)
    {
        if (!$ids) {
            return;
        }

        $type = self::typeForModel($modelClass);
        foreach ($ids as $id) {
            self::assertRecordedId($type, $id);
        }

        $modelClass::whereIn('id', $ids)->delete();
    }

    /**
     * Delete recorded rows one model at a time so the deleting hooks run their
     * cascades. find() skips rows an earlier cascade already removed.
     *
     * @param string     $modelClass
     * @param array<int> $ids
     * @return void
     */
    private static function deleteModelInstances($modelClass, array $ids)
    {
        if (!$ids) {
            return;
        }

        $type = self::typeForModel($modelClass);
        foreach ($ids as $id) {
            self::assertRecordedId($type, $id);
        }

        foreach ($ids as $id) {
            $model = $modelClass::find($id);
            if ($model) {
                $model->delete();
            }
        }
    }

    /**
     * Prove every recorded primary ID is absent after cleanup.
     *
     * @return void
     */
    private static function assertPrimaryRowsDeleted()
    {
        $models = [
            'customers'     => Customer::class,
            'agents'        => Agent::class,
            'mailboxes'     => MailBox::class,
            'products'      => Product::class,
            'tags'          => Tag::class,
            'tickets'       => Ticket::class,
            'conversations' => Conversation::class,
            'saved_replies' => SavedReply::class,
        ];

        foreach ($models as $type => $modelClass) {
            $ids = self::ids($type);
            if (!$ids) {
                continue;
            }

            $remaining = $modelClass::whereIn('id', $ids)->pluck('id')->toArray();
            if ($remaining) {
                throw new RuntimeException(
                    'Fixture cleanup left ' . $type . ' IDs: ' . implode(',', $remaining) . '.'
                );
            }
        }

        self::assertDependentRowsDeleted();
    }

    /**
     * Prove the delete cascades ran: no conversation, meta, attachment or
     * tag-pivot row survives for a recorded ticket or conversation. Notification
     * tables are optional, so they aren't checked. The lifecycle test plants
     * meta and attachment rows so these checks never pass vacuously.
     *
     * @return void
     */
    private static function assertDependentRowsDeleted()
    {
        $ticketIds = self::ids('tickets');
        if ($ticketIds) {
            $orphans = Conversation::whereIn('ticket_id', $ticketIds)->pluck('id')->toArray();
            if ($orphans) {
                throw new RuntimeException(
                    'Fixture cleanup left conversations of recorded tickets: ' . implode(',', $orphans) . '.'
                );
            }

            $meta = Meta::whereIn('object_type', ['ticket', 'ticket_meta', '_fs_auto_draft'])
                ->whereIn('object_id', $ticketIds)
                ->pluck('id')->toArray();
            if ($meta) {
                throw new RuntimeException(
                    'Fixture cleanup left ticket meta rows: ' . implode(',', $meta) . '.'
                );
            }

            $attachments = Attachment::whereIn('ticket_id', $ticketIds)->pluck('id')->toArray();
            if ($attachments) {
                throw new RuntimeException(
                    'Fixture cleanup left ticket attachments: ' . implode(',', $attachments) . '.'
                );
            }

            $pivots = TagPivot::where('source_type', 'ticket_tag')
                ->whereIn('source_id', $ticketIds)
                ->pluck('id')->toArray();
            if ($pivots) {
                throw new RuntimeException(
                    'Fixture cleanup left ticket tag pivots: ' . implode(',', $pivots) . '.'
                );
            }
        }

        $tagIds = self::ids('tags');
        if ($tagIds) {
            $pivots = TagPivot::whereIn('tag_id', $tagIds)->pluck('id')->toArray();
            if ($pivots) {
                throw new RuntimeException(
                    'Fixture cleanup left tag pivots of recorded tags: ' . implode(',', $pivots) . '.'
                );
            }
        }

        $conversationIds = self::ids('conversations');
        if ($conversationIds) {
            $meta = Meta::where('object_type', 'response')
                ->whereIn('object_id', $conversationIds)
                ->pluck('id')->toArray();
            if ($meta) {
                throw new RuntimeException(
                    'Fixture cleanup left conversation cc meta rows: ' . implode(',', $meta) . '.'
                );
            }

            $attachments = Attachment::whereIn('conversation_id', $conversationIds)->pluck('id')->toArray();
            if ($attachments) {
                throw new RuntimeException(
                    'Fixture cleanup left conversation attachments: ' . implode(',', $attachments) . '.'
                );
            }
        }
    }

    /**
     * Map a model class back to the exact ownership registry.
     *
     * @param string $modelClass
     * @return string
     */
    private static function typeForModel($modelClass)
    {
        $types = [
            Customer::class     => 'customers',
            Agent::class        => 'agents',
            MailBox::class      => 'mailboxes',
            Product::class      => 'products',
            Tag::class          => 'tags',
            Ticket::class       => 'tickets',
            Conversation::class => 'conversations',
            SavedReply::class   => 'saved_replies',
        ];

        if (!isset($types[$modelClass])) {
            throw new LogicException('Unknown fixture model class: ' . $modelClass . '.');
        }

        return $types[$modelClass];
    }

    /**
     * Return positive IDs for one recorded fixture type.
     *
     * @param string $type
     * @return array<int>
     */
    private static function ids($type)
    {
        if (!isset(self::$created[$type])) {
            throw new LogicException('Unknown fixture type: ' . $type . '.');
        }

        $ids = array_map('intval', array_keys(self::$created[$type]));
        foreach ($ids as $id) {
            self::assertRecordedId($type, $id);
        }

        return $ids;
    }

    /**
     * Add the process marker to a human title without duplicating it.
     *
     * @param string $title
     * @return string
     */
    private static function tagTitle($title)
    {
        $title = (string) $title;
        if (strpos($title, self::marker()) !== false) {
            return $title;
        }

        return $title . ' [' . self::marker() . ']';
    }

    /**
     * Generate a readable counter value unique within one fixture type.
     *
     * @param string $type
     * @return int
     */
    private static function nextSuffix($type)
    {
        if (!isset(self::$counters[$type])) {
            self::$counters[$type] = 0;
        }

        self::$counters[$type]++;

        return self::$counters[$type];
    }

    /**
     * Whether cleanup has any recorded primary fixtures to process.
     *
     * @return bool
     */
    private static function hasCreatedFixtures()
    {
        foreach (self::$created as $records) {
            if ($records) {
                return true;
            }
        }

        return false;
    }
}
