<?php
/**
 * BaseImporter::getPerson() must serve repeat lookups from its static cache,
 * so a repeat customer/agent lookup during an import runs zero queries.
 */

use FluentSupport\App\Modules\TicketImport\Importers\BaseImporter;

return function () {

    $marker = FsFactory::marker();

    /*
     * getPerson() is protected and the class is abstract; a minimal concrete
     * importer exposes it without touching any real helpdesk source.
     */
    $importer = new class extends BaseImporter {
        public function stats()
        {
            return [];
        }

        public function doMigration($page, $handler)
        {
            return [];
        }

        public function deleteTickets($page)
        {
            return [];
        }

        public function resolvePerson($personData, $type = 'customer')
        {
            return $this->getPerson($personData, $type);
        }
    };

    $makeWpUser = function ($slug) use ($marker) {
        $login = substr($marker . '-' . $slug, 0, 60);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $marker . '-' . $slug . '@example.invalid',
            'user_pass'  => wp_generate_password(20),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create importer WP user: ' . $userId->get_error_message());
        }

        return (int) $userId;
    };

    $customerUserId = $makeWpUser('import-customer');
    $agentUserId = $makeWpUser('import-agent');

    try {
        // Rows exist up front so getPerson() resolves them instead of creating
        // unowned persons the factory cannot clean up.
        $customer = FsFactory::customer([
            'email'   => $marker . '-import-customer@example.invalid',
            'user_id' => $customerUserId,
        ]);
        $agent = FsFactory::agent([
            'email'   => $marker . '-import-agent@example.invalid',
            'user_id' => $agentUserId,
        ]);

        $cases = [
            'customer' => [$customer, $customerUserId],
            'agent'    => [$agent, $agentUserId],
        ];

        foreach ($cases as $type => list($expected, $userId)) {
            FsTest::case('importer_get_person_caches_repeat_' . $type . '_lookups', function () use ($importer, $type, $expected, $userId) {
                $personData = [
                    'user_id' => $userId,
                    'email'   => $expected->email,
                ];

                $first = $importer->resolvePerson($personData, $type);

                if (!$first) {
                    FsTest::fail('first getPerson() call returned nothing for the ' . $type . ' fixture');
                    return;
                }

                FsTest::assertSame((int) $expected->id, (int) $first->id,
                    'first call must resolve the existing ' . $type . ' row');

                $second = null;
                $queries = FsTest::countQueries(function () use ($importer, $personData, $type, &$second) {
                    $second = $importer->resolvePerson($personData, $type);
                });

                FsTest::assertSame(0, $queries,
                    'a repeat ' . $type . ' lookup must be served from the cache without touching the database');
                FsTest::assert($second === $first,
                    'a repeat ' . $type . ' lookup must return the cached instance');
            });
        }
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ([$customerUserId, $agentUserId] as $userId) {
            if ($userId) {
                wp_delete_user($userId);
            }
        }
    }
};
