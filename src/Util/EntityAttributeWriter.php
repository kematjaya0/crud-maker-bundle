<?php

namespace Kematjaya\CrudMakerBundle\Util;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser\Php7;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;

/**
 * Safely adds class-level attributes (`#[ApiResource(...)]`, `#[ApiFilter(...)]`) to an
 * EXISTING, hand-authored entity file, via AST manipulation (nikic/php-parser's
 * format-preserving pretty-printer — the same technique `symfony/maker-bundle`'s
 * `ClassSourceManipulator` uses for `make:entity`) rather than naive string/regex insertion,
 * so the rest of the file's formatting/comments/existing attributes are left untouched.
 *
 * Deliberately narrower than `ClassSourceManipulator`: that class can't build attribute
 * arguments that are themselves expressions (`operations: [new GetCollection(), ...]` isn't a
 * plain scalar/array value) — see `buildNodeExprByValue()`. This writer instead parses the
 * *entire* target attribute block as a small PHP snippet and transplants the resulting
 * `AttributeGroup` nodes onto the real class, which works for arbitrary attribute code.
 *
 * Refuses (throws) rather than risk corrupting or duplicating existing config if the class
 * already carries an attribute with a colliding short name (e.g. re-running against an entity
 * that already has `#[ApiResource]`).
 *
 * @package Kematjaya\CrudMakerBundle\Util
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class EntityAttributeWriter
{
    /**
     * @param string       $attributeCode  Raw PHP attribute syntax, e.g. "#[ApiResource(...)]\n#[ApiFilter(...)]"
     * @param list<string> $useStatements  Fully-qualified class names referenced by $attributeCode
     */
    public function addClassAttributes(string $sourceCode, string $attributeCode, array $useStatements): string
    {
        $lexer = new Emulative(PhpVersion::fromString('8.1'));
        $parser = new Php7($lexer);

        $oldStmts = $parser->parse($sourceCode);
        if (null === $oldStmts) {
            throw new \RuntimeException('Could not parse entity source code.');
        }
        $oldTokens = $parser->getTokens();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        /** @var list<Node\Stmt> $newStmts */
        $newStmts = $traverser->traverse($oldStmts);

        $finder = new NodeFinder();
        $classNode = $finder->findFirstInstanceOf($newStmts, Node\Stmt\Class_::class);
        if (!$classNode instanceof Node\Stmt\Class_) {
            throw new \RuntimeException('Could not find a class declaration in the entity source.');
        }

        $newAttributeGroups = $this->parseAttributeGroups($attributeCode, $parser);
        $this->assertNoDuplicateAttributes($classNode, $newAttributeGroups);
        array_push($classNode->attrGroups, ...$newAttributeGroups);

        $this->addUseStatements($newStmts, $useStatements);

        $printer = new Standard();

        return $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
    }

    /**
     * @return list<Node\AttributeGroup>
     */
    private function parseAttributeGroups(string $attributeCode, Php7 $parser): array
    {
        $snippet = "<?php\n".$attributeCode."\nclass KematjayaCrudMakerBundleDummyTarget {}\n";
        $stmts = $parser->parse($snippet);
        if (null === $stmts) {
            throw new \RuntimeException('Could not parse generated attribute code: '.$attributeCode);
        }

        $finder = new NodeFinder();
        $classNode = $finder->findFirstInstanceOf($stmts, Node\Stmt\Class_::class);
        if (!$classNode instanceof Node\Stmt\Class_) {
            throw new \RuntimeException('Could not parse generated attribute code into a class attribute: '.$attributeCode);
        }

        return $classNode->attrGroups;
    }

    /**
     * @param list<Node\AttributeGroup> $newAttributeGroups
     */
    private function assertNoDuplicateAttributes(Node\Stmt\Class_ $classNode, array $newAttributeGroups): void
    {
        $existingNames = [];
        foreach ($classNode->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $existingNames[$attr->name->getLast()] = true;
            }
        }

        foreach ($newAttributeGroups as $group) {
            foreach ($group->attrs as $attr) {
                $shortName = $attr->name->getLast();
                if (isset($existingNames[$shortName])) {
                    throw new \RuntimeException(sprintf(
                        'Entity already has a #[%s] attribute — refusing to add a duplicate automatically. Remove it (or add the rest by hand) first.',
                        $shortName,
                    ));
                }
            }
        }
    }

    /**
     * @param list<Node\Stmt> $stmts
     * @param list<string>    $useStatements
     */
    private function addUseStatements(array $stmts, array $useStatements): void
    {
        $finder = new NodeFinder();

        $namespaceNode = $finder->findFirstInstanceOf($stmts, Node\Stmt\Namespace_::class);
        if (!$namespaceNode instanceof Node\Stmt\Namespace_) {
            throw new \RuntimeException('Entity file has no namespace declaration.');
        }

        $existing = [];
        foreach ($finder->findInstanceOf($namespaceNode->stmts, Node\Stmt\Use_::class) as $useNode) {
            foreach ($useNode->uses as $useItem) {
                $existing[$useItem->name->toString()] = true;
            }
        }

        $insertAt = 0;
        foreach ($namespaceNode->stmts as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                $insertAt = $i + 1;
            }
        }

        $newUseNodes = [];
        foreach ($useStatements as $fqcn) {
            $fqcn = ltrim($fqcn, '\\');
            if (isset($existing[$fqcn])) {
                continue;
            }
            $existing[$fqcn] = true;
            $newUseNodes[] = new Node\Stmt\Use_([new Node\UseItem(new Node\Name($fqcn))]);
        }

        array_splice($namespaceNode->stmts, $insertAt, 0, $newUseNodes);
    }
}
