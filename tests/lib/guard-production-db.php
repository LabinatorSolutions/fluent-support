<?php
/**
 * Refuses to let a destructive runner touch anything but a throwaway database.
 *
 * Include at the top of any runner that drops or recreates tables or databases.
 * Adapt the two patterns to your suite's naming and nothing else.
 */

function suite_guard_against_production_db(string $dbName, string $tablePrefix): void
{
    // Anything that doesn't match these is treated as a real site.
    $allowedDbPattern = '/^[a-z0-9_]*test[a-z0-9_]*$/i'; // e.g. fftest, wp_test
    $allowedPrefixPattern = '/^[a-z0-9]+test_/i';        // e.g. fftest_

    $violations = [];
    if (!preg_match($allowedDbPattern, $dbName)) {
        $violations[] = "database name '{$dbName}' does not match the throwaway pattern";
    }
    if (!preg_match($allowedPrefixPattern, $tablePrefix)) {
        $violations[] = "table prefix '{$tablePrefix}' does not match the throwaway pattern";
    }

    if ($violations) {
        fwrite(STDERR, "\nREFUSING TO RUN — this runner mutates its database.\n");
        foreach ($violations as $violation) {
            fwrite(STDERR, "  - {$violation}\n");
        }
        fwrite(STDERR, "Point the suite at its own throwaway database, never a real site's.\n\n");
        exit(3);
    }
}
