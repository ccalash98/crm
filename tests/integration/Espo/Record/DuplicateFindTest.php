<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace tests\integration\Espo\Record;

use Espo\Core\{
    Exceptions\ConflictSilent,
    Record\CreateParams,
};

use Espo\Modules\Crm\Services\Account;
use Espo\Modules\Crm\Services\Lead;


class DuplicateFindTest extends \tests\integration\Core\BaseTestCase
{
    public function testAccount1()
    {
        /** @var Account $service */
        $service = $this->getContainer()
            ->get('recordServiceContainer')
            ->get('Account');

        $data1 = (object) [
            'name' => 'test1',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data1, CreateParams::create());

        $this->expectException(ConflictSilent::class);

        $data2 = (object) [
            'name' => 'test2',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data2, CreateParams::create());
    }

    public function testAccountSkip()
    {
        /** @var Account $service */
        $service = $this->getContainer()
            ->get('recordServiceContainer')
            ->get('Account');

        $data1 = (object) [
            'name' => 'test1',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data1, CreateParams::create());

        $data2 = (object) [
            'name' => 'test2',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data2, CreateParams::create()->withSkipDuplicateCheck());

        $this->assertTrue(true);
    }

    public function testLead1()
    {
        /** @var Lead $service */
        $service = $this->getContainer()
            ->get('recordServiceContainer')
            ->get('Lead');

        $data1 = (object) [
            'lastName' => 'test1',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data1, CreateParams::create());

        $this->expectException(ConflictSilent::class);

        $data2 = (object) [
            'lastName' => 'test2',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data2, CreateParams::create());
    }

    public function testLeadSkip()
    {
        /** @var Lead $service */
        $service = $this->getContainer()
            ->get('recordServiceContainer')
            ->get('Lead');

        $data1 = (object) [
            'lastName' => 'test1',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data1, CreateParams::create());

        $data2 = (object) [
            'lastName' => 'test2',
            'emailAddress' => 'test@test.com',
        ];

        $service->create($data2, CreateParams::create()->withSkipDuplicateCheck());

        $this->assertTrue(true);
    }
}
