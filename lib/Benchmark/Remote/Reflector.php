<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Benchmark\Remote;

/**
 * Reflector for remote classes.
 */
class Reflector
{
    /**
     * @param Launcher
     */
    private $launcher;

    /**
     * @param Launcher $launcher
     */
    public function __construct(
        Launcher $launcher
    ) {
        $this->launcher = $launcher;
    }

    /**
     * Return an array of ReflectionClass instances for the given file. The
     * first ReflectionClass is the class contained in the given file (there
     * may be only one) additional ReflectionClass instances are the ancestors
     * of this first class.
     *
     * @param string $file
     *
     * @return ReflectionHierarchy
     */
    public function reflect($file)
    {
        $classFqn = $this->getClassNameFromFile($file);

        if (null === $classFqn) {
            throw new \InvalidArgumentException(sprintf(
                'Could not find class in file "%s"', $file
            ));
        }

        $classHierarchy = $this->launcher->launch(__DIR__ . '/template/reflector.template', array(
            'file' => $file,
            'class' => $classFqn,
        ));

        $hierarchy = new ReflectionHierarchy();

        foreach ($classHierarchy as $classInfo) {
            $reflectionClass = new ReflectionClass();
            $reflectionClass->class = $classInfo['class'];
            $reflectionClass->abstract = $classInfo['abstract'];
            $reflectionClass->comment = $classInfo['comment'];
            $reflectionClass->interfaces = $classInfo['interfaces'];
            $reflectionClass->path = $file;

            foreach ($classInfo['methods'] as $methodInfo) {
                $reflectionMethod = new ReflectionMethod();
                $reflectionMethod->class = $classInfo['class'];
                $reflectionMethod->name = $methodInfo['name'];
                $reflectionMethod->comment = $methodInfo['comment'];
                $reflectionClass->methods[$reflectionMethod->name] = $reflectionMethod;
            }
            $hierarchy->addReflectionClass($reflectionClass);
        }

        return $hierarchy;
    }

    /**
     * Return the parameter sets for the benchmark container in the given file.
     *
     * @param string $file
     * @param string[] $paramProviders
     *
     * @return array
     */
    public function getParameterSets($file, $paramProviders)
    {
        $parameterSets = $this->launcher->launch(__DIR__ . '/template/parameter_set_extractor.template', array(
            'file' => $file,
            'class' => $this->getClassNameFromFile($file),
            'paramProviders' => var_export($paramProviders, true),
        ));

        return $parameterSets;
    }

    /**
     * Return the class name from a file.
     *
     * Taken from http://stackoverflow.com/questions/7153000/get-class-name-from-file
     *
     * @param string $file
     *
     * @return string
     */
    private function getClassNameFromFile($file)
    {
        $fp = fopen($file, 'r');

        $class = $namespace = $buffer = '';
        $i = 0;

        while (!$class) {
            if (feof($fp)) {
                break;
            }

            $buffer .= fread($fp, 512);
            $tokens = @token_get_all($buffer);

            if (strpos($buffer, '{') === false) {
                continue;
            }

            for (;$i < count($tokens);$i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1;$j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\' . $tokens[$j][1];
                        } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1;$j < count($tokens);$j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i + 2][1];
                        }
                    }
                }
            }
        };

        if (!$class) {
            return;
        }

        return $namespace . '\\' . $class;
    }
}