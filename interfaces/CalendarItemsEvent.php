<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

namespace humhub\modules\calendar\interfaces;

use DateTime;

class CalendarItemsEvent extends CalendarEvent
{
    /**
     * @var array
     */
    public $filters;

    /**
     * @var DateTime
     */
    public $start;

    /**
     * @var DateTime
     */
    public $end;

    /**
     * @var integer result limit
     */
    public $limit;

    /**
     * @var []
     */
    public $items = [];

    /**
     * @param $item array
     */
    public function addItems($itemType, $items)
    {
        if (!isset($this->items[$itemType])) {
            $this->items[$itemType] = $items;
        } else {
            $this->items[$itemType] = array_merge($this->items[$itemType], $items);
        }
    }

    public static function addItemTypes($itemTypesDef)
    {
        foreach ($itemTypesDef as $calendar) {
            $event->addType($calendar->getItemTypeKey(), $calendar->getFullCalendarArray());
        }
    }

    /**
     * @inheritdoc
     */
    public function getItems()
    {
        return $this->items;
    }

}
