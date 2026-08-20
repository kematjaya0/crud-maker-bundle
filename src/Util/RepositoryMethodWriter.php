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
 * Safely adds a method to an EXISTING, hand-authored repository class file, via AST
 * manipulation (nikic/php-parser's format-preserving pretty-printer) — the sibling of
 * {@see EntityAttributeWriter}, same technique, applied to a class method instead of a
 * class-level attribute group.
 *
 * Refuses (throws) rather than risk corrupting or duplicating existing behavior if the class
 * already declares a method with the same name (e.g. re-running the generator, or a
 * hand-written method that happens to collide).
 *
 * @package Kematjaya\CrudMakerBundle\Util
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class RepositoryMethodWriter
{
    /**
     * @param string       $methodCode    Raw PHP method syntax, e.g. "public function findExportData(...): array {\n ... \n}"
     * @param list<string> $useStatements Fully-qualified class names referenced by $methodCode
     */
    public function addMethod(string $sourceCode, string $methodCode, array $useStatements): string
    {
        $lexer = new Emulative(PhpVersion::fromString('8.1'));
        $parser = new Php7($lexer);

        $oldStmts = $parser->parse($sourceCode);
        if (null === $oldStmts) {
            throw new \RuntimeException('Could not parse repository source code.');
        }
        $oldTokens = $parser->getTokens();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        /** @var list<Node\Stmt> $newStmts */
        $newStmts = $traverser->traverse($oldStmts);

        $finder = new NodeFinder();
        $classNode = $finder->findFirstInstanceOf($newStmts, Node\Stmt\Class_::class);
        if (!$classNode instanceof Node\Stmt\Class_) {
            throw new \RuntimeException('Could not find a class declaration in the repository source.');
        }

        $newMethod = $this->parseMethod($methodCode, $parser);
        $this->assertNoDuplicateMethod($classNode, $newMethod);
        $classNode->stmts[] = $newMethod;

        $this->addUseStatements($newStmts, $useStatements);

        $printer = new Standard();

        return $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
    }

    private function parseMethod(string $methodCode, Php7 $parser): Node\Stmt\ClassMethod
    {
        $snippet = "<?php\nclass KematjayaCrudMakerBundleDummyTarget {\n".$methodCode."\n}\n";
        $stmts = $parser->parse($snippet);
        if (null === $stmts) {
            throw new \RuntimeException('Could not parse generated repository method code: '.$methodCode);
        }

        $finder = new NodeFinder();
        $methodNode = $finder->findFirstInstanceOf($stmts, Node\Stmt\ClassMethod::class);
        if (!$methodNode instanceof Node\Stmt\ClassMethod) {
            throw new \RuntimeException('Could not parse generated code into a class method: '.$methodCode);
        }

        return $methodNode;
    }

    private function assertNoDuplicateMethod(Node\Stmt\Class_ $classNode, Node\Stmt\ClassMethod $newMethod): void
    {
        $newName = $newMethod->name->toString();
        foreach ($classNode->getMethods() as $method) {
            if (0 === strcasecmp($method->name->toString(), $newName)) {
                throw new \RuntimeException(sprintf(
                    'Repository already has a method named "%s" — refusing to add a duplicate automatically. Remove it (or add the export method by hand) first.',
                    $newName,
                ));
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
            throw new \RuntimeException('Repository file has no namespace declaration.');
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
