<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
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

namespace Espo\Core\Formula\Functions\DateTimeGroup;

use \Espo\Core\Exceptions\Error;

class DayOfWeekType extends \Espo\Core\Formula\Functions\Base
{
    protected function init()
    {
        $this->addDependency('dateTime');
    }

    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            return true;
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        if (count($item->value) < 1) {
             throw new Error();
        }

        $value = $this->evaluate($item->value[0]);

        $timezone = null;
        if (count($item->value) > 1) {
             $timezone = $this->evaluate($item->value[1]);
        }

        if (empty($value)) return -1;

        if (strlen($value) > 11) {
            $resultString = $this->getInjection('dateTime')->convertSystemDateTime($value, $timezone, 'd');
        } else {
            $resultString = $this->getInjection('dateTime')->convertSystemDate($value, 'd');
        }

        $result = intval($resultString);

        return $result;
    }
}