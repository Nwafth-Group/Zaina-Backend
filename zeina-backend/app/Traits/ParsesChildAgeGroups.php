<?php

namespace App\Traits;

use App\Models\Service;

trait ParsesChildAgeGroups
{
    /**
     * Parse child_age_groups array from multiple format options in query parameters.
     */
    protected function parseChildAgeGroups(): array
    {
        $queryString = request()->getQueryString();
        if (empty($queryString) && request()->getContent()) {
            $contentType = request()->header('Content-Type', '');
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                $queryString = request()->getContent();
            }
        }
        $rawChildAgeGroups = [];

        // 1. Try parsing from raw query string to handle duplicate keys without brackets (e.g. child_age_groups=1&child_age_groups=2)
        if ($queryString) {
            $parts = explode('&', $queryString);
            foreach ($parts as $part) {
                $subParts = explode('=', $part, 2);
                if (count($subParts) === 2) {
                    $key = urldecode($subParts[0]);
                    $val = urldecode($subParts[1]);
                    // Support array brackets with indices as well, e.g. child_age_groups[0]
                    if ($key === 'child_age_groups' || $key === 'child_age_groups[]' || preg_match('/^child_age_groups\[\d+\]$/', $key)) {
                        if (is_numeric($val)) {
                            $rawChildAgeGroups[] = (int) $val;
                        } elseif (is_string($val) && str_contains($val, ',')) {
                            // Support comma-separated strings inside values, e.g. child_age_groups=1,2,3
                            $exploded = explode(',', trim($val, '[] '));
                            foreach ($exploded as $item) {
                                if (is_numeric(trim($item))) {
                                    $rawChildAgeGroups[] = (int) trim($item);
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = [];

        if (! empty($rawChildAgeGroups)) {
            $result = $rawChildAgeGroups;
        } else {
            // 2. Parse child_age_groups if passed as string representation of array (e.g. "[1,2]" or "1,2")
            $childAgeGroups = $this->input('child_age_groups');
            if (is_string($childAgeGroups)) {
                $decoded = json_decode($childAgeGroups, true);
                if (is_array($decoded)) {
                    $result = array_map('intval', $decoded);
                } else {
                    $trimmed = trim($childAgeGroups, '[]');
                    if ($trimmed !== '') {
                        $result = array_map('intval', explode(',', $trimmed));
                    }
                }
            } else {
                // 3. Fallback to normal input if already array, or empty
                $childAgeGroups = $this->input('child_age_groups') ?? [];
                if (is_array($childAgeGroups)) {
                    $flat = [];
                    foreach ($childAgeGroups as $item) {
                        if (is_array($item)) {
                            $flat = array_merge($flat, array_map('intval', $item));
                        } elseif (is_string($item) && (str_contains($item, ',') || str_contains($item, '['))) {
                            // Unpack bracket or comma-separated string, e.g. "1,2,3" or "[1,2,3]"
                            $exploded = explode(',', trim($item, '[] '));
                            foreach ($exploded as $exVal) {
                                $flat[] = (int) trim($exVal);
                            }
                        } else {
                            $flat[] = (int) $item;
                        }
                    }
                    $result = $flat;
                }
            }
        }

        // Self-Healing / Auto-Padding fallback logic:
        // If the mobile app sent fewer child age group items than number_of_children,
        // pad the array to prevent validation errors and ensure proper pricing calculations.
        $numberOfChildren = (int) $this->input('number_of_children', 0);
        if (count($result) < $numberOfChildren) {
            $fillValue = count($result) > 0 ? end($result) : null;
            if ($fillValue === null) {
                $serviceId = $this->input('service_id');
                $service = $serviceId ? Service::find($serviceId) : null;
                if ($service) {
                    $firstGroup = $service->childAgeGroups()->where('status', 'active')->first();
                    if ($firstGroup) {
                        $fillValue = $firstGroup->id;
                    }
                }
            }
            if ($fillValue !== null) {
                while (count($result) < $numberOfChildren) {
                    $result[] = (int) $fillValue;
                }
            }
        }

        return $result;
    }
}
