<?php

namespace App\Traits;

use App\Models\Service;

trait ParsesChildAgeGroups
{
    /**
     * Parse child_age_groups array from multiple format options in query parameters and request body.
     */
    protected function parseChildAgeGroups(): array
    {
        $numberOfChildren = (int) $this->input('number_of_children', 0);
        $result = [];

        // 1. Try standard input (array, JSON string, or comma-separated string)
        $inputVal = $this->input('child_age_groups');

        if (is_array($inputVal)) {
            $flat = [];
            foreach ($inputVal as $item) {
                if (is_array($item)) {
                    $flat = array_merge($flat, array_map('intval', $item));
                } elseif (is_string($item) && (str_contains($item, ',') || str_contains($item, '['))) {
                    $exploded = explode(',', trim($item, '[] '));
                    foreach ($exploded as $exVal) {
                        if (is_numeric(trim($exVal))) {
                            $flat[] = (int) trim($exVal);
                        }
                    }
                } elseif (is_numeric($item)) {
                    $flat[] = (int) $item;
                }
            }
            $result = $flat;
        } elseif (is_string($inputVal)) {
            $decoded = json_decode($inputVal, true);
            if (is_array($decoded)) {
                $result = array_map('intval', $decoded);
            } else {
                $trimmed = trim($inputVal, '[]');
                if ($trimmed !== '') {
                    $exploded = explode(',', $trimmed);
                    foreach ($exploded as $item) {
                        if (is_numeric(trim($item))) {
                            $result[] = (int) trim($item);
                        }
                    }
                }
            }
        }

        // 2. If result count is less than numberOfChildren, try raw query string parsing for unbracketed duplicate keys (e.g. child_age_groups=1&child_age_groups=2)
        if (count($result) < $numberOfChildren || empty($result)) {
            $queryString = request()->getQueryString();
            if (empty($queryString) && request()->getContent()) {
                $contentType = request()->header('Content-Type', '');
                if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                    $queryString = request()->getContent();
                }
            }

            if ($queryString) {
                $rawChildAgeGroups = [];
                $parts = explode('&', $queryString);
                foreach ($parts as $part) {
                    $subParts = explode('=', $part, 2);
                    if (count($subParts) === 2) {
                        $key = urldecode($subParts[0]);
                        $val = urldecode($subParts[1]);
                        if ($key === 'child_age_groups' || $key === 'child_age_groups[]' || preg_match('/^child_age_groups\[\d+\]$/', $key)) {
                            if (is_numeric($val)) {
                                $rawChildAgeGroups[] = (int) $val;
                            } elseif (is_string($val) && str_contains($val, ',')) {
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

                if (count($rawChildAgeGroups) >= count($result)) {
                    $result = $rawChildAgeGroups;
                }
            }
        }

        // 3. Self-Healing / Auto-Padding fallback logic:
        // Only pad if result is still smaller than numberOfChildren
        if ($numberOfChildren > 0 && count($result) < $numberOfChildren) {
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
