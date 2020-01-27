<?php

namespace Mtolhuys\LaravelSchematics\Services;

use SplFileInfo;
use PhpParser\Node;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Illuminate\Database\Eloquent\Model;

class ModelMapper
{
    public static $models = [];

    /**
     * Maps subclasses of Illuminate\Database\Eloquent\Model
     * found in app_path()
     *
     * @return array
     */
    public static function map(): array
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path()), RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if (self::readablePhp($file)) {
                $class = self::getClassName($file);

                if (is_subclass_of($class, Model::class)) {
                    self::$models[] = $class;
                }
            }
        }

        return self::$models;
    }

    /**
     * @param SplFileInfo $file
     * @return bool
     */
    private static function readablePhp(SplFileInfo $file): bool
    {
        return
            $file->isReadable()
            && $file->isFile()
            && mb_strtolower($file->getExtension()) === 'php';
    }

    /**
     * @param string $path
     * @return string
     */
    protected static function getClassName(string $path): string
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $root = collect(self::getStatements($path, $traverser))
            ->first(static function ($statement) {
                return $statement instanceof Namespace_;
            });

        if (!$root) {
            return '';
        }

        return self::findClass($root);
    }

    /**
     * @param string $path
     * @param NodeTraverser $traverser
     * @return Node[]|null
     */
    protected static function getStatements(string $path, NodeTraverser $traverser)
    {
        $statements = (new ParserFactory())
            ->create(ParserFactory::PREFER_PHP7)
            ->parse(file_get_contents($path));

        $statements = $traverser->traverse($statements);

        return $statements;
    }

    /**
     * @param $root
     * @return string
     */
    protected static function findClass($root): string
    {
        return collect($root->stmts)->filter(function ($statement) {
                return $statement instanceof Class_;
            })->map(static function (Class_ $statement) {
                return $statement->namespacedName->toString();
            })->first() ?? '';
    }
}
