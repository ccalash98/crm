<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\unit\Espo\ORM;

use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;
use Espo\ORM\Metadata;
use Espo\ORM\MetadataDataProvider;
use Espo\ORM\Query\DeleteBuilder;
use Espo\ORM\Query\InsertBuilder;
use Espo\ORM\QueryBuilder;
use Espo\ORM\QueryComposer\PostgresqlQueryComposer as QueryComposer;

require_once 'tests/unit/testData/DB/Entities.php';
require_once 'tests/unit/testData/DB/MockPDO.php';
require_once 'tests/unit/testData/DB/MockDBResult.php';

class PostgresqlQueryComposerTest extends \PHPUnit\Framework\TestCase
{
    private ?QueryComposer $queryComposer;
    private ?EntityManager $entityManager;

    protected function setUp(): void
    {
        $ormMetadata = include('tests/unit/testData/DB/ormMetadata.php');

        $metadataDataProvider = $this->createMock(MetadataDataProvider::class);

        $metadataDataProvider
            ->expects($this->any())
            ->method('get')
            ->willReturn($ormMetadata);

        $metadata = new Metadata($metadataDataProvider);

        $this->queryBuilder = new QueryBuilder();

        $pdo = $this->createMock('MockPDO');
        $pdo
            ->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function() {
                $args = func_get_args();

                return "'" . $args[0] . "'";
            }));

        $this->entityManager = $this->createMock(EntityManager::class);
        $entityFactory = $this->createMock(EntityFactory::class);

        $entityFactory
            ->expects($this->any())
            ->method('create')
            ->will(
                $this->returnCallback(function () use ($metadata) {
                    $args = func_get_args();
                    $className = "tests\\unit\\testData\\DB\\" . $args[0];
                    $defs = $metadata->get($args[0]) ?? [];

                    return new $className($args[0], $defs, $this->entityManager);
                })
            );

        $this->queryComposer = new QueryComposer($pdo, $entityFactory, $metadata);

        $this->post = $entityFactory->create('Post');
        $this->comment = $entityFactory->create('Comment');
        $this->tag = $entityFactory->create('Tag');
        $this->note = $entityFactory->create('Note');
        $this->contact = $entityFactory->create('Contact');
        $this->account = $entityFactory->create('Account');
    }

    public function testDelete1(): void
    {
        $query = DeleteBuilder::create()
            ->from('Account')
            ->where(['name' => 'test'])
            ->build();

        $sql = $this->queryComposer->composeDelete($query);

        $expectedSql =
            "DELETE FROM \"account\" " .
            "WHERE \"account\".\"name\" = 'test'";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testInsertUpdate1(): void
    {
        $query = InsertBuilder::create()
            ->into('PostTag')
            ->columns(['id', 'postId', 'tagId'])
            ->values([
                'id' => '1',
                'postId' => 'post-id',
                'tagId' => 'tag-id',
            ])
            ->updateSet([
                'deleted' => 0
            ])
            ->build();

        $sql = $this->queryComposer->composeInsert($query);

        $expectedSql =
            "INSERT INTO \"post_tag\" (\"id\", \"post_id\", \"tag_id\") VALUES ('1', 'post-id', 'tag-id') " .
            "ON CONFLICT(\"post_id\", \"tag_id\") DO UPDATE SET \"deleted\" = 0";

        $this->assertEquals($expectedSql, $sql);
    }

    public function testInsertUpdate2(): void
    {
        $query = InsertBuilder::create()
            ->into('Account')
            ->columns(['id', 'name'])
            ->values([
                'id' => '1',
                'name' => 'name',
            ])
            ->updateSet([
                'deleted' => 0
            ])
            ->build();

        $sql = $this->queryComposer->composeInsert($query);

        $expectedSql =
            "INSERT INTO \"account\" (\"id\", \"name\") VALUES ('1', 'name') " .
            "ON CONFLICT(\"id\") DO UPDATE SET \"deleted\" = 0";

        $this->assertEquals($expectedSql, $sql);
    }
}
