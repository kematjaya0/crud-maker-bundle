<?php

namespace Kematjaya\CrudMakerBundle\Maker;

/**
 * Normalisasi argumen yes/no dari input interaktif maupun non-interaktif
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */
trait InputArgumentTrait
{
    /**
     * "y", "yes", "true", "1" => true, selain itu false
     */
    protected function isYes(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['y', 'yes', 'true', '1'], true);
    }
}
