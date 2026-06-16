<?php

namespace App\Services\OltDriver\Huawei\Parsers;

/**
 * Parses the output of:
 *   display ont-lineprofile gpon all
 *   display ont-srvprofile gpon all
 *
 * Both commands share an identical tabular format:
 *   Profile-ID  Profile-name         Binding times
 *   --------
 *   0           some-profile-name    0
 *   1           FLEXIBLE_GPON        2206
 *   --------
 *   Total: N
 *
 * Returns: array of ['id' => int, 'name' => string, 'bindings' => int]
 */
class ProfileListParser
{
    /** @return list<array{id: int, name: string, bindings: int}> */
    public static function parse(string $output): array
    {
        $profiles = [];

        foreach (explode("\n", $output) as $line) {
            // Match data rows: optional whitespace, integer ID, name, integer bindings
            if (preg_match('/^\s+(\d+)\s+(\S+)\s+(\d+)\s*$/', $line, $m)) {
                $profiles[] = [
                    'id'       => (int) $m[1],
                    'name'     => $m[2],
                    'bindings' => (int) $m[3],
                ];
            }
        }

        return $profiles;
    }

    /**
     * Returns the profile entry whose 'id' matches, or null if not found.
     *
     * @param  list<array{id:int,name:string,bindings:int}> $profiles
     */
    public static function findById(array $profiles, int $id): ?array
    {
        foreach ($profiles as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }
}
