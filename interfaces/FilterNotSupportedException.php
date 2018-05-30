<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

namespace humhub\modules\calendar\interfaces;

use yii\base\Exception;

class FilterNotSupportedException extends Exception
{
    /**
     * @inheritdoc
     */
    public function getName() {
        return 'Filter not suppored';
    }

}
