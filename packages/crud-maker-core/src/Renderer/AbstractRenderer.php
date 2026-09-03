<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

use Doctrine\Inflector\Inflector as LegacyInflector;
use Doctrine\Inflector\InflectorFactory;

/**
 * Base renderer shared by every `kematjaya/crud-maker-*` maker package. Each concrete
 * renderer lives in its own package (e.g. `crud-maker-twig-bundle`, `crud-maker-api-bundle`)
 * alongside its own `Resources/skeleton` directory, so the "own" skeleton path can't be
 * derived from this class's location (`dirname(__DIR__)` here would resolve inside this
 * core package instead) — it must be passed in by the subclass.
 *
 * @package Kematjaya\CrudMakerBundle\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
abstract class AbstractRenderer
{
    /** @var array<int, string> */
    protected array $basePath = [];

    private ?\Doctrine\Inflector\Inflector $inflector = null;

    public function __construct(string $ownSkeletonPath, ?string $customPath = null)
    {
        $path = (null !== $customPath) ? [$customPath] : [];
        $this->basePath = array_merge($path, [$ownSkeletonPath]);

        if (class_exists(InflectorFactory::class)) {
            $this->inflector = InflectorFactory::create()->build();
        }
    }

    protected function pluralize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->pluralize($word);
        }

        /** @phpstan-ignore method.staticCall */
        return LegacyInflector::pluralize($word);
    }

    /**
     * camelCase/PascalCase -> snake_case (e.g. "TestArticle" -> "test_article"). Mirrors
     * ApiPlatform's own default `path_segment_name_generator` (`UnderscorePathSegmentNameGenerator`,
     * `api_platform.metadata.path_segment_name_generator.underscore`), which derives a resource's
     * default collection URI as `pluralize(tableize($shortName))` — same Doctrine Inflector, same
     * order of operations. Used so a generated entity's default resource URI can be predicted
     * *before* ApiPlatform itself resolves it (e.g. {@see \Kematjaya\CrudMakerApiBundle\Renderer\ApiCrudRenderer::buildSpecJson()}).
     */
    protected function tableize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->tableize($word);
        }

        /** @phpstan-ignore method.staticCall */
        return LegacyInflector::tableize($word);
    }

    protected function singularize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->singularize($word);
        }

        /** @phpstan-ignore method.staticCall */
        return LegacyInflector::singularize($word);
    }
    
    /**
     * @return array<int, string>
     */
    public function getBasePath():array
    {
        return $this->basePath;
    }
    /**
     * 
     * @param string $filename
     * @return string
     */
    protected function getPath(string $filename):string
    {
        foreach ($this->basePath as $path) {
            if (!file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
                continue;
            }
            
            return $path . DIRECTORY_SEPARATOR . $filename;
        }
        
        throw new \Exception(sprintf("cannot find template '%s'", $filename));
    }
}
