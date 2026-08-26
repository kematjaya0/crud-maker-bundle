<?php

namespace Kematjaya\CrudMakerBundle\Api\Renderer;

/**
 * @package Kematjaya\CrudMakerBundle\Api\Renderer
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
