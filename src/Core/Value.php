<?php

declare(strict_types=1);

namespace PrivacyEvidence\Core;

final class Value
{
    public static function string(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('%s must be a string.', $field));
        }

        return $value;
    }

    public static function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::string($value, $field);
    }

    public static function int(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf('%s must be an integer.', $field));
    }

    public static function float(mixed $value, string $field): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new \UnexpectedValueException(sprintf('%s must be numeric.', $field));
    }

    /**
     * @return array<string, scalar|null>
     */
    public static function scalarMap(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf('%s must be an object.', $field));
        }

        $result = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf('%s must be an object.', $field));
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, scalar|array<array-key, scalar>|null>
     */
    public static function configurationMap(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf('%s must be an object.', $field));
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $result[$key] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $nested = [];
            /** @psalm-suppress MixedAssignment */
            foreach ($item as $nestedKey => $nestedValue) {
                if (is_scalar($nestedValue)) {
                    $nested[$nestedKey] = $nestedValue;
                }
            }
            $result[$key] = $nested;
        }

        return $result;
    }
}
