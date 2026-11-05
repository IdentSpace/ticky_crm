<?php

namespace OCA\TickyCRM\Service;

use InvalidArgumentException;

class DecimalValue {
    public static function normalize(mixed $value, string $field, bool $tax, bool $optional): ?string {
        if ($optional && ($value === null || (is_string($value) && trim($value) === ''))) {
            return null;
        }
        // Reject floats: their precision may already have been lost in JSON.
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException($field);
        }
        $value = str_replace(',', '.', trim((string)$value));
        $scale = $tax ? 2 : 4;
        $digits = $tax ? 3 : 15;
        if (!preg_match('/^([0-9]{1,' . $digits . '})(?:\.([0-9]{1,' . $scale . '}))?$/D', $value, $matches)) {
            throw new InvalidArgumentException($field);
        }
        $whole = ltrim($matches[1], '0') ?: '0';
        $fraction = str_pad($matches[2] ?? '', $scale, '0');
        if ($tax && ((int)$whole > 100 || ((int)$whole === 100 && (int)$fraction > 0))) {
            throw new InvalidArgumentException($field);
        }
        return $whole . '.' . $fraction;
    }
}
