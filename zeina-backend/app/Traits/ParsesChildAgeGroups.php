<?php

namespace App\Traits;

trait ParsesChildAgeGroups
{
    /**
     * Parse child_age_groups array from multiple format options in query parameters.
     */
    protected function parseChildAgeGroups(): array
    {
        $queryString = request()->getQueryString();
        $rawChildAgeGroups = [];
        
        // 1. Try parsing from raw query string to handle duplicate keys without brackets (e.g. child_age_groups=1&child_age_groups=2)
        if ($queryString) {
            $parts = explode('&', $queryString);
            foreach ($parts as $part) {
                $subParts = explode('=', $part, 2);
                if (count($subParts) === 2) {
                    $key = urldecode($subParts[0]);
                    $val = urldecode($subParts[1]);
                    if ($key === 'child_age_groups' || $key === 'child_age_groups[]') {
                        $rawChildAgeGroups[] = (int) $val;
                    }
                }
            }
        }

        if (!empty($rawChildAgeGroups)) {
            return $rawChildAgeGroups;
        }

        // 2. Parse child_age_groups if passed as string representation of array (e.g. "[1,2]" or "1,2")
        $childAgeGroups = $this->input('child_age_groups');
        if (is_string($childAgeGroups)) {
            $decoded = json_decode($childAgeGroups, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $trimmed = trim($childAgeGroups, '[]');
            if ($trimmed !== '') {
                return array_map('intval', explode(',', $trimmed));
            }
            return [];
        }

        // 3. Fallback to normal input if already array, or empty
        $childAgeGroups = $this->input('child_age_groups') ?? [];
        return is_array($childAgeGroups) ? $childAgeGroups : [];
    }
}
