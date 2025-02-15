<?php

namespace SilverStripe\GraphQL\Tests\Scaffolders\Scaffolding;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\GraphQL\Tests\Fake\FakeQueryPermissionChecker;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

class ListQueryScaffolderTest extends SapphireTest
{
    public function testGetPaginationLimit()
    {
        /** @var Manager $observer */
        $observer = $this->getMockBuilder(Manager::class)
            ->setMethods(['addQuery'])
            ->getMock();
        $scaffold = new ListQueryScaffolder($observer, 'test');

        $this->assertEquals(100, $scaffold->getPaginationLimit());

        $scaffold->setPaginationLimit(200);
        $this->assertEquals(100, $scaffold->getPaginationLimit());

        $scaffold->setPaginationLimit(25);
        $this->assertEquals(25, $scaffold->getPaginationLimit());
    }

    public function testMaximumPaginationLimit()
    {
        /** @var Manager $observer */
        $observer = $this->getMockBuilder(Manager::class)
            ->setMethods(['addQuery'])
            ->getMock();
        $scaffold = new ListQueryScaffolder($observer, 'test');

        $this->assertEquals(100, $scaffold->getMaximumPaginationLimit());

        $scaffold->setMaximumPaginationLimit(200);
        $this->assertEquals(200, $scaffold->getMaximumPaginationLimit());

        $scaffold->setMaximumPaginationLimit(25);
        $this->assertEquals(25, $scaffold->getPaginationLimit());
    }

    public function testListQueryScaffolderUnpaginated()
    {
        /** @var Manager $observer */
        $observer = $this->getMockBuilder(Manager::class)
            ->setMethods(['addQuery'])
            ->getMock();
        $scaffolder = new ListQueryScaffolder('testQuery', 'test');
        $scaffolder->setDescription('My description');
        $scaffolder->setUsePagination(false);
        $scaffolder->addArgs(['Test' => 'String']);
        $manager = new Manager();
        $manager->addType($o = new ObjectType([
            'name' => 'test',
            'fields' => [],
        ]));
        $o->Test = true;

        $scaffold = $scaffolder->scaffold($manager);


        $this->assertEquals('testQuery', $scaffold['name']);
        $this->assertEquals('My description', $scaffold['description']);
        $this->assertArrayHasKey('Test', $scaffold['args']);
        $this->assertTrue(is_callable($scaffold['resolve']));
        $this->assertTrue($scaffold['type']->getWrappedType()->Test);

        $observer->expects($this->once())
            ->method('addQuery')
            ->with(
                function ($arg) use ($scaffold) {
                    return $arg() === $scaffold;
                },
                $this->equalTo('testQuery')
            );

        $scaffolder->addToManager($observer);
    }

    public function testListQueryScaffolderPaginated()
    {
        $scaffolder = new ListQueryScaffolder('testQuery', 'test');
        $scaffolder->setUsePagination(true);
        $scaffolder->setPaginationLimit(25);
        $scaffolder->setMaximumPaginationLimit(110);
        $scaffolder->addArgs(['Test' => 'String']);
        $scaffolder->addSortableFields(['test']);
        $manager = new Manager();
        $manager->addType(new ObjectType([
            'name' => 'test',
            'fields' => [],
        ]));
        $scaffolder->addToManager($manager);
        $scaffold = $scaffolder->scaffold($manager);
        $config = $scaffold['type']->config;

        $this->assertEquals('testQueryConnection', $config['name']);
        $this->assertArrayHasKey('pageInfo', $config['fields']());
        $this->assertArrayHasKey('edges', $config['fields']());
    }

    public function testListQueryScaffolderApplyConfig()
    {
        /** @var ListQueryScaffolder $mock */
        $mock = $this->getMockBuilder(ListQueryScaffolder::class)
            ->setConstructorArgs(['testQuery', 'testType'])
            ->setMethods([
                'addSortableFields',
                'setUsePagination',
                'setPaginationLimit',
                'setMaximumPaginationLimit',
            ])
            ->getMock();
        $mock->expects($this->once())
            ->method('addSortableFields')
            ->with(['Test1', 'Test2']);
        $mock->expects($this->exactly(3))
            ->method('setUsePagination')
            ->withConsecutive([false], [[
                'limit' => 25,
                'maximumLimit' => 110
            ]], [[
                'defaultLimit' => 25,
                'maximumLimit' => 110
            ]]);

        $mock->applyConfig([
            'sortableFields' => ['Test1', 'Test2'],
            'paginate' => false,
        ]);

        $mock->expects($this->exactly(2))
            ->method('setPaginationLimit')
            ->with(25);
        $mock->expects($this->exactly(2))
            ->method('setMaximumPaginationLimit')
            ->with(110);

        $mock->applyConfig([
            'paginate' => [
                'limit' => 25,
                'maximumLimit' => 110
            ],
        ]);

        $mock->applyConfig([
            'paginate' => [
                'defaultLimit' => 25,
                'maximumLimit' => 110
            ],
        ]);
    }

    public function testListQueryScaffolderApplyConfigThrowsOnBadSortableFields()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sortableFields must be an array/');
        $scaffolder = new ListQueryScaffolder('testQuery', 'testType');
        $scaffolder->applyConfig([
            'sortableFields' => 'fail',
        ]);
    }

    /**
     * @dataProvider permissionProvider
     * @param bool|null $allow
     */
    public function testPermissionCheck($allow)
    {
        $resolver = function () {
            return new ArrayList([
                new ArrayData(['Foo' => 'Bar'])
            ]);
        };
        $manager = new Manager();
        $manager->addType(new ObjectType([
            'name' => 'testType',
            'fields' => [],
        ]));

        $scaffolder = new ListQueryScaffolder('testQuery', 'testType', $resolver);
        $scaffolder->setUsePagination(false);
        if ($allow !== null) {
            $scaffolder->setPermissionChecker(new FakeQueryPermissionChecker($allow));
        }

        $scaffolder->addToManager($manager);
        $arr = $scaffolder->scaffold($manager);
        $result = $arr['resolve'](null, [], ['currentUser' => null], new ResolveInfo([]));
        $this->assertNotNull($result);
        $expected = $allow === false ? 0 : 1;
        $this->assertCount($expected, $result);
        if ($allow !== false) {
            $this->assertEquals('Bar', $result->first()->Foo);
        }
    }

    /**
     * @return array
     */
    public function permissionProvider()
    {
        return [
            [null],
            [true],
            [false]
        ];
    }
}
