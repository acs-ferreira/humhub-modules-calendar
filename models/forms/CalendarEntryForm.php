<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */

namespace humhub\modules\calendar\models\forms;

use Yii;
use yii\base\Model;
use DateInterval;
use DateTime;
use DateTimeZone;
use humhub\libs\DbDateValidator;
use humhub\libs\TimezoneHelper;
use humhub\modules\calendar\CalendarUtils;
use humhub\modules\calendar\models\CalendarEntryType;
use humhub\modules\calendar\models\DefaultSettings;
use humhub\modules\content\models\Content;
use humhub\modules\calendar\models\CalendarEntry;

/**
 * Created by PhpStorm.
 * User: buddha
 * Date: 12.07.2017
 * Time: 16:14
 */
class CalendarEntryForm extends Model
{

    /**
     * @var integer Content visibility
     */
    public $is_public;

    /**
     * @var string start date submitted by user will be converted to db date format and timezone after validation
     */
    public $start_date;

    /**
     * @var string start time string
     */
    public $start_time;

    /**
     * @var string end date submitted by user will be converted to db date format and timezone after validation
     */
    public $end_date;

    /**
    * @var string end time string
    */
    public $end_time;

    /**
     * @var string timeZone set in calendar form
     */
    public $timeZone;

    /**
     * @var int calendar event type id
     */
    public $type_id;

    /*
     * @var array
     */
    public $markdownFiles = [];

    /**
     * @var bool
     */
    public $sendUpdateNotification = 0;

    /**
     * @var CalendarEntry
     */
    public $entry;

    public function init()
    {
        $this->timeZone = empty($this->timeZone) ? Yii::$app->formatter->timeZone : $this->timeZone;

        if($this->entry) {
            if($this->entry->all_day) {
                $this->timeZone = $this->entry->time_zone;
            }

            // Translate time/date from app (db) timeZone to user (or configured) timeZone
            $this->translateDateTimes($this->entry->start_datetime, $this->entry->end_datetime, Yii::$app->timeZone, $this->timeZone);
            $this->is_public = $this->entry->content->visibility;

            $type = $this->entry->getType();
            if(!empty($type)) {
                $this->type_id = $type->id;
            }


        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['timeZone'], 'in', 'range' => DateTimeZone::listIdentifiers()],
            [['is_public', 'type_id', 'sendUpdateNotification'], 'integer'],
            [['start_time', 'end_time'], 'date', 'type' => 'time', 'format' => $this->getTimeFormat()],
            [['start_date'], DbDateValidator::className(), 'format' => Yii::$app->params['formatter']['defaultDateFormat'], 'timeAttribute' => 'start_time', 'timeZone' => $this->timeZone],
            [['end_date'], DbDateValidator::className(), 'format' => Yii::$app->params['formatter']['defaultDateFormat'], 'timeAttribute' => 'end_time', 'timeZone' => $this->timeZone],
            [['end_date'], 'validateEndTime'],
            [['type_id'], 'validateType'],
        ];
    }

    public function getTimeFormat()
    {
        return Yii::$app->formatter->isShowMeridiem() ? 'h:mm a' : 'php:H:i';
    }

    public function beforeValidate()
    {
        $this->checkAllDay();
        return parent::beforeValidate(); // TODO: Change the autogenerated stub
    }

    public function checkAllDay()
    {
        Yii::$app->formatter->timeZone = $this->timeZone;
        if($this->entry->all_day) {
            $date = new DateTime('now', new DateTimeZone($this->timeZone));
            $date->setTime(0,0);
            $this->start_time = Yii::$app->formatter->asTime($date, $this->getTimeFormat());
            $date->setTime(23,59);
            $this->end_time = Yii::$app->formatter->asTime($date, $this->getTimeFormat());
        }
        Yii::$app->i18n->autosetLocale();
    }

    /**
     * Validator for the endtime field.
     * Execute this after DbDateValidator
     *
     * @param string $attribute attribute name
     * @param [] $params parameters
     */
    public function validateEndTime($attribute, $params)
    {
        if (new DateTime($this->start_date) >= new DateTime($this->end_date)) {
            $this->addError($attribute, Yii::t('CalendarModule.base', "End time must be after start time!"));
        }
    }

    public function validateType($attribute, $params)
    {
        if(!$this->type_id) {
            return;
        }

        $type = CalendarEntryType::findOne($this->type_id);

        if($type->contentcontainer_id != null && $type->contentcontainer_id !== $this->entry->content->contentcontainer_id) {
            $this->addError($attribute,Yii::t('CalendarModule.base', "Invalid event type id selected."));
        }
    }

    public function attributeLabels()
    {
        return [
            'start_date' => Yii::t('CalendarModule.base', 'Start Date'),
            'type_id' => Yii::t('CalendarModule.base', 'Event Type'),
            'end_date' => Yii::t('CalendarModule.base', 'End Date'),
            'start_time' => Yii::t('CalendarModule.base', 'Start Time'),
            'end_time' => Yii::t('CalendarModule.base', 'End Time'),
            'timeZone' => Yii::t('CalendarModule.base', 'Time Zone'),
            'is_public' => Yii::t('CalendarModule.base', 'Public'),
            'sendUpdateNotification' => Yii::t('CalendarModule.base', 'Send update notification'),
        ];
    }

    public function createNew($contentContainer, $start = null, $end = null)
    {
        $this->entry = new CalendarEntry();
        $this->entry->content->container = $contentContainer;
        $this->is_public = ($this->entry->content->visibility != null) ? $this->entry->content->visibility : Content::VISIBILITY_PRIVATE;
        $this->timeZone = Yii::$app->formatter->timeZone;

        $defaultSettings = new DefaultSettings(['contentContainer' => $contentContainer]);
        $this->entry->participation_mode = $defaultSettings->participation_mode;
        $this->entry->allow_decline = $defaultSettings->allow_decline;
        $this->entry->allow_maybe = $defaultSettings->allow_maybe;

        // Translate from user timeZone to system timeZone note the datepicker expects app timezone
        $this->translateDateTimes($start, $end, $this->timeZone, $this->timeZone);
    }

    public function load($data, $formName = null)
    {
        // Make sure we load the timezone beforehand so its available in validators etc..
        if($data && isset($data[$this->formName()]) && isset($data[$this->formName()]['timeZone']) && !empty($data[$this->formName()]['timeZone'])) {
            $this->timeZone = $data[$this->formName()]['timeZone'];
        }
        if(parent::load($data) && !empty($this->timeZone)) {
            $this->entry->time_zone = $this->timeZone;
        }



        //$this->translateDateTimes($this->entry->start_datetime, $this->entry->end_datetime, Yii::$app->timeZone, $this->timeZone);

        $this->entry->content->visibility = $this->is_public;

        if(!$this->entry->load($data)) {
            return false;
        }

        // change 0, '' etc to null
        if(empty($this->type_id)) {
            $this->type_id = null;
        }

        return true;
    }

    public function save()
    {
        if(!$this->validate()) {
            return false;
        }

        // After validation the date was translated to system time zone, which we expect in the database.
        $this->entry->start_datetime = $this->start_date;
        $this->entry->end_datetime = $this->end_date;

        // The form expects user time zone, so we translate back from app to user timezone
        $this->translateDateTimes($this->entry->start_datetime, $this->entry->end_datetime, Yii::$app->timeZone, $this->timeZone);

        if($this->entry->save()) {
            $this->entry->fileManager->attach($this->entry->files);
            if(!empty($this->type_id)) {
                $this->entry->setType($this->type_id);
            }

            if($this->sendUpdateNotification && !$this->entry->isNewRecord) {
                $this->entry->sendUpdateNotification();
            }

            return true;
        }

        return false;
    }

    public static function getParticipationModeItems()
    {
        return [
            CalendarEntry::PARTICIPATION_MODE_NONE => Yii::t('CalendarModule.views_entry_edit', 'No participants'),
            CalendarEntry::PARTICIPATION_MODE_ALL => Yii::t('CalendarModule.views_entry_edit', 'Everybody can participate')
        ];
    }

    public function showTimeFields()
    {
        return !$this->entry->all_day;
    }

    public function updateTime($start = null, $end = null)
    {
        $this->entry->time_zone = Yii::$app->formatter->timeZone;
        $this->translateDateTimes($start, $end, null, null, 'php:Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Translates the given start and end dates from $sourceTimeZone to $targetTimeZone and populates the form start/end time
     * and dates.
     *
     * By default $sourceTimeZone is the forms timeZone e.g user timeZone and $targetTimeZone is the app timeZone.
     *
     * @param string $start start string date in $sourceTimeZone
     * @param string $end end string date in $targetTimeZone
     * @param string $sourceTimeZone
     * @param string $targetTimeZone
     */
    public function translateDateTimes($start = null, $end = null, $sourceTimeZone = null, $targetTimeZone = null, $dateFormat = 'php:Y-m-d H:i:s e')
    {
        if(!$start) {
            return;
        }

        $sourceTimeZone = (empty($sourceTimeZone)) ? $this->timeZone : $sourceTimeZone;
        $targetTimeZone = (empty($targetTimeZone)) ? Yii::$app->timeZone : $targetTimeZone;

        $startTime = new DateTime($start, new DateTimeZone($sourceTimeZone));
        $endTime = new DateTime($end, new DateTimeZone($sourceTimeZone));

        Yii::$app->formatter->timeZone = $targetTimeZone;
        // Fix FullCalendar EndTime
        if (CalendarUtils::isFullDaySpan($startTime, $endTime, true)) {
            // In Fullcalendar the EndTime is the moment AFTER the event so we substract one second
            $endTime->sub(new DateInterval("PT1S"));
            $this->entry->all_day = 1;
        }

        $this->start_date = Yii::$app->formatter->asDateTime($startTime, $dateFormat);
        $this->start_time = Yii::$app->formatter->asTime($startTime, $this->getTimeFormat());

        $this->end_date = Yii::$app->formatter->asDateTime($endTime, $dateFormat);
        $this->end_time = Yii::$app->formatter->asTime($endTime, $this->getTimeFormat());

        Yii::$app->i18n->autosetLocale();
    }

    public function getCalendarTypeItems()
    {
        $result = [];
        $calendarTypes = CalendarEntryType::findByContainer($this->entry->content->container)->all();
        foreach ($calendarTypes as $calendarType) {
            $result[$calendarType] = $calendarType->name;
        }
        return $result;
    }
}