<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Extensions\Dbal\Tests\Functional\Storage\Driver\Dbal;

use PhpBench\Extensions\Dbal\Storage\Driver\Dbal\Persister;
use PhpBench\Extensions\Dbal\Storage\Driver\Dbal\Repository;
use PhpBench\Extensions\Dbal\Tests\Functional\DbalTestCase;
use PhpBench\Model\SuiteCollection;
use PhpBench\Tests\Util\TestUtil;

class RepositoryTest extends DbalTestCase
{
    private $persister;
    private $repository;

    public function setUp()
    {
        $manager = $this->getManager();

        $this->persister = new Persister($manager);
        $this->repository = new Repository($manager);
    }

    /**
     * It should return the history statement.
     */
    public function testHistoryStatement()
    {
        $suiteCollection = new SuiteCollection([
            TestUtil::createSuite([
                'uuid' => 1,
                'env' => [
                    'vcs' => [
                        'system' => 'git',
                        'branch' => 'branch_1',
                    ],
                ],
                'name' => 'one',
                'date' => '2016-01-01',
            ]),
            TestUtil::createSuite([
                'uuid' => 2,
                'date' => '2015-01-01',
                'env' => [
                    'vcs' => [
                        'system' => 'git',
                        'branch' => 'branch_2',
                    ],
                ],
                'name' => 'two',
            ]),
        ]);

        $this->persister->persist($suiteCollection);
        $statement = $this->repository->getHistoryStatement();
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals([
            [
                'run_date' => '2016-01-01 00:00:00',
                'context' => 'one',
                'vcs_branch' => 'branch_1',
                'run_uuid' => 1,
            ],
            [
                'run_date' => '2015-01-01 00:00:00',
                'context' => 'two',
                'vcs_branch' => 'branch_2',
                'run_uuid' => 2,
            ],
        ], $rows);
    }

    /**
     * It should get parameters.
     */
    public function testParameters()
    {
        $parameters = [
            'one' => 'two',
            'two' => ['three', 'four'],
        ];

        $suiteCollection = new SuiteCollection([
            TestUtil::createSuite([
                'uuid' => 1234,
                'parameters' => $parameters,
            ]),
        ]);

        $this->persister->persist($suiteCollection);
        $params = $this->repository->getParameters(1);

        $this->assertEquals($parameters, $params);
    }
}