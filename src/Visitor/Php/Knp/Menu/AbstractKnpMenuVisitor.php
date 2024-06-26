<?php

/*
 * This file is part of the PHP Translation package.
 *
 * (c) PHP Translation team <tobias.nyholm@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Translation\Extractor\Visitor\Php\Knp\Menu;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use Translation\Extractor\Model\SourceLocation;
use Translation\Extractor\Visitor\Php\BasePHPVisitor;

/**
 * This class provides common functionality for KnpMenu extractors.
 */
abstract class AbstractKnpMenuVisitor extends BasePHPVisitor implements NodeVisitor
{
    private bool $isKnpMenuBuildingMethod = false;

    private string|bool|null $domain = null;

    /**
     * @var SourceLocation[]
     */
    private array $sourceLocations = [];

    public function beforeTraverse(array $nodes): ?Node
    {
        $this->sourceLocations = [];

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if (!$this->isKnpMenuBuildingMethod($node)) {
            return null;
        }

        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!\is_string($node->name) && !$node->name instanceof Node\Identifier) {
            return null;
        }

        $methodName = (string) $node->name;
        if ('setExtra' !== $methodName) {
            return null;
        }

        $extraKey = $this->getStringArgument($node, 0);
        if ('translation_domain' === $extraKey) {
            if (
                $node->args[1]->value instanceof Node\Expr\ConstFetch
                && 'false' === $node->args[1]->value->name->toString()
            ) {
                // translation disabled
                $this->domain = false;
            } else {
                $extraValue = $this->getStringArgument($node, 1);
                if (null !== $extraValue) {
                    $this->domain = $extraValue;
                }
            }
        }

        return null;
    }

    /**
     * Checks if the given node is a class method returning a knp menu.
     */
    protected function isKnpMenuBuildingMethod(Node $node): bool
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            if (null === $node->returnType) {
                $this->isKnpMenuBuildingMethod = false;
            }
            if ($node->returnType instanceof Node\Identifier) {
                $this->isKnpMenuBuildingMethod = false;
            }

            $returnType = $node->returnType;
            if ($returnType instanceof Node\NullableType) {
                $returnType = $returnType->type;
            }

            if (!$returnType instanceof Node\Name) {
                $this->isKnpMenuBuildingMethod = false;
            } else {
                $this->isKnpMenuBuildingMethod = 'ItemInterface' === $returnType->toString();
            }
        }

        return $this->isKnpMenuBuildingMethod;
    }

    protected function lateCollect(SourceLocation $location): void
    {
        $this->sourceLocations[] = $location;
    }

    public function leaveNode(Node $node): ?Node
    {
        return null;
    }

    public function afterTraverse(array $nodes): ?Node
    {
        if (false === $this->domain) {
            // translation disabled
            return null;
        }

        foreach ($this->sourceLocations as $location) {
            if (null !== $this->domain) {
                $context = $location->getContext();
                $context['domain'] = $this->domain;
                $location = new SourceLocation($location->getMessage(), $location->getPath(), $location->getLine(), $context);
            }
            $this->collection->addLocation($location);
        }
        $this->sourceLocations = [];

        return null;
    }
}
