<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

/**
 * @package Kematjaya\CrudMakerBundle\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class ApiCrudResult
{
    /**
     * @param list<string> $nextSteps
     */
    public function __construct(
        public readonly array $nextSteps,
    ) {
    }
}
