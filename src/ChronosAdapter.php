<?php

namespace RoussKS\FinancialYear;

use Cake\Chronos\Chronos;
use DateInterval;
use DatePeriod;
use DateTimeInterface;
use RoussKS\FinancialYear\Exceptions\ConfigException;
use RoussKS\FinancialYear\Exceptions\Exception;
use Traversable;

/**
 * Implementation of Cakephp\Chronos FinancialYear Adapter
 *
 * Class ChronosAdapter
 *
 * @package RoussKS\FinancialYear\Chronos
 */
class ChronosAdapter extends AbstractAdapter implements AdapterInterface
{
    /**
     * @var Chronos
     */
    protected $fyStartDate;

    /**
     * @var Chronos
     */
    protected $fyEndDate;

    /**
     * DateTimeAdapter constructor.
     *
     * @param  string $fyType
     * @param  Chronos|string $fyStartDate
     * @param  bool $fiftyThreeWeeks
     *
     * @return void
     *
     * @throws Exception
     * @throws ConfigException
     */
    public function __construct(string $fyType, $fyStartDate, bool $fiftyThreeWeeks = false)
    {
        parent::__construct($fyType, $fiftyThreeWeeks);

        $this->setFyStartDate($fyStartDate);

        $this->setFyEndDate();
    }

    /**
     * {@inheritdoc}
     *
     * Extend parent class in order to recalculate end date if the business year weeks change.
     *
     * @throws Exception
     */
    public function setFyWeeks($fiftyThreeWeeks = false): void
    {
        $originalFyWeeks = $this->fyWeeks;

        parent::setFyWeeks($fiftyThreeWeeks);

        // Reset the financial year end date according to the weeks setting.
        if ($originalFyWeeks !== null && $originalFyWeeks !== $this->fyWeeks) {
            $this->setFyEndDate();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return Chronos
     */
    public function getFyStartDate(): DateTimeInterface
    {
        return $this->fyStartDate;
    }

    /**
     * {@inheritdoc}
     *
     * @param  Chronos|string $date
     *
     * @throws Exception
     */
    public function setFyStartDate($date): void
    {
        // fyStartDate property is an immutable object.
        $originalFyStartDate = $this->fyStartDate;

        $this->fyStartDate = $this->getDateObject($date);

        $this->validateStartDate();

        // If this method was not called on instantiation,
        // recalculate financial year end date from current settings,
        // even if the new start date is the same as the previous one (why re-setting the same date?).
        if ($originalFyStartDate !== null) {
            $this->setFyEndDate();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return Chronos
     */
    public function getFyEndDate(): DateTimeInterface
    {
        return $this->fyEndDate;
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getPeriodById(int $id): Traversable
    {
        return new DatePeriod(
            $this->getFirstDateOfPeriodById($id),
            DateInterval::createFromDateString('1 day'),
            $this->getLastDateOfPeriodById($id)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getBusinessWeekById(int $id): Traversable
    {
        return new DatePeriod(
            $this->getFirstDateOfBusinessWeekById($id),
            DateInterval::createFromDateString('1 day'),
            $this->getLastDateOfBusinessWeekById($id)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param  Chronos|string $date
     *
     * @throws Exception
     */
    public function getPeriodIdByDate($date): int
    {
        $chronos = $this->getDateObject($date);

        // Instantly throw exception for a date that's out of range of the current financial year.
        // Do this to avoid the resource intensive loop.
        $this->validateDateBelongsToCurrentFinancialYear($chronos);

        for ($id = 1; $id <= $this->fyPeriods; $id++) {
            // If the date is between the start and the end date of the period, get the period's id.
            if ($chronos->between($this->getFirstDateOfPeriodById($id), $this->getLastDateOfPeriodById($id))) {
                return $id;
            }
        }

        // We can never reach this stage.
        // However, added for keeping the IDEs happy of non returned value.
        throw new Exception('A period could not be found for the requested date.');
    }

    /**
     * {@inheritdoc}
     *
     * @param  Chronos|string $date
     *
     * @throws Exception
     */
    public function getBusinessWeekIdIdByDate($date): int
    {
        $chronos = $this->getDateObject($date);

        // Instantly throw exception for a date that's out of range of the current financial year.
        // Do this to avoid the resource intensive loop.
        $this->validateDateBelongsToCurrentFinancialYear($chronos);

        for ($id = 1; $id <= $this->fyWeeks; $id++) {
            // If the date is between the start and the end date of the business week, get the period's id.
            if (
                $chronos->between($this->getFirstDateOfBusinessWeekById($id), $this->getLastDateOfBusinessWeekById($id))
            ) {
                return $id;
            }
        }

        // We can never reach this stage.
        // However, added for keeping the IDEs happy of non returned value.
        throw new Exception('A business week could not be found for the specified date.');
    }

    /**
     * {@inheritdoc}
     *
     * First check for calendar type.
     * Otherwise, it will be business type as no other is supported.
     *
     * @return Chronos
     *
     * @throws Exception
     */
    public function getFirstDateOfPeriodById(int $id): DateTimeInterface
    {
        $this->validateConfiguration();

        $this->validatePeriodId($id);

        if ($id === 1) {
            return $this->fyStartDate;
        }

        if ($this->isCalendarType($this->type)) {
            return $this->fyStartDate->addMonths($id - 1);
        }

        return $this->fyStartDate->addWeeks(($id - 1) * 4);
    }

    /**
     * {@inheritdoc}
     *
     * First check for calendar type.
     * Otherwise, it will be business type as no other is supported.
     *
     * @return Chronos
     *
     * @throws Exception
     */
    public function getLastDateOfPeriodById(int $id): DateTimeInterface
    {
        $this->validateConfiguration();

        $this->validatePeriodId($id);

        if ($id === $this->fyPeriods) {
            return $this->fyEndDate;
        }

        if ($this->isCalendarType($this->type)) {
            return $this->fyStartDate->addMonths($id)->subDay();
        }

        return $this->fyStartDate->addWeeks($id * 4)->subDay();
    }

    /**
     * {@inheritdoc}
     *
     * @return Chronos
     *
     * @throws Exception
     */
    public function getFirstDateOfBusinessWeekById(int $id): DateTimeInterface
    {
        $this->validateConfiguration();

        $this->validateBusinessWeekId($id);

        // If 1st week, get the start of the financial year.
        if ($id === 1) {
            return $this->fyStartDate;
        }

        return $this->fyStartDate->addWeeks($id - 1);
    }

    /**
     * {@inheritdoc}
     *
     * @return Chronos
     *
     * @throws Exception
     */
    public function getLastDateOfBusinessWeekById(int $id): DateTimeInterface
    {
        $this->validateConfiguration();

        $this->validateBusinessWeekId($id);

        // If last week, get the end of the financial year.
        if ($id === $this->fyWeeks) {
            return $this->fyEndDate;
        }

        return $this->fyStartDate->addWeeks($id)->subDay();
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getFirstBusinessWeekByPeriodId(int $id): Traversable
    {
        return $this->getBusinessWeekById(($id - 1) * 4 + 1);
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getSecondBusinessWeekByPeriodId(int $id): Traversable
    {
        return $this->getBusinessWeekById(($id - 1) * 4 + 2);
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getThirdBusinessWeekOfPeriodId(int $id): Traversable
    {
        return $this->getBusinessWeekById(($id - 1) * 4 + 3);
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws Exception
     */
    public function getFourthBusinessWeekByPeriodId(int $id): Traversable
    {
        return $this->getBusinessWeekById($id * 4);
    }

    /**
     * {@inheritdoc}
     *
     * @return DatePeriod|Chronos[]
     *
     * @throws  Exception
     */
    public function getFiftyThirdBusinessWeek(): Traversable
    {
        return $this->getBusinessWeekById(53);
    }

    /**
     * {@inheritdoc}
     *
     * @return Chronos
     */
    public function getNextFyStartDate(): DateTimeInterface
    {
        // For calendar type, the next year's start date is + 1 year.
        if ($this->isCalendarType($this->type)) {
            return $this->fyStartDate->addYear();
        }

        // For business type, the next year's start date is + number of weeks.
        // As a financial year would have 52 or 53 weeks, the param handles it.
        return $this->fyStartDate->addWeeks($this->fyWeeks);
    }

    /**
     * Set the financial year end date.
     *
     * We will set end date from the start date object which should be present.
     * Both types calculate end date relative to next financial year start date.
     * As that is automatically calculated for us, regardless of type, we just subtract 1 day.
     *
     * @return void
     */
    protected function setFyEndDate(): void
    {
        $this->fyEndDate = $this->getNextFyStartDate()->subDay();
    }

    /**
     * Validate that the start date is not disallowed.
     *
     * @return void
     *
     * @throws ConfigException
     */
    protected function validateStartDate(): void
    {
        $disallowedFyCalendarTypeDates = [29, 30, 31];

        if (
            $this->isCalendarType($this->type) &&
            in_array($this->fyStartDate->day, $disallowedFyCalendarTypeDates, true)
        ) {
            $this->throwConfigurationException(
                'This library does not support 29, 30, 31 as start dates of a month for calendar type financial year.'
            );
        }
    }

    /**
     * Validate that a date belongs to the set financial year.
     *
     * @param  Chronos $chronos
     *
     * @return void
     *
     * @throws Exception
     */
    protected function validateDateBelongsToCurrentFinancialYear(Chronos $chronos): void
    {
        if (!$chronos->between($this->fyStartDate, $this->fyEndDate)) {
            throw new Exception('The requested date is out of range of the current financial year.');
        }
    }

    /**
     * Get a Chronos object from the provided parameter.
     *
     * @param  Chronos|string $date
     *
     * @return Chronos
     *
     * @throws Exception
     */
    protected function getDateObject($date): Chronos
    {
        // First check if we have received a Chronos object relevant to the adapter.
        // If we did, return the required Chronos.
        if ($date instanceof Chronos) {
            return $date->startOfDay();
        }

        // Then if a string was passed as param, create the Chronos object.
        // Chronos has an internal InvalidArgumentException if the variable's string format is incorrect.
        // Otherwise it is properly created.
        if (is_string($date)) {
            return Chronos::createFromFormat('Y-m-d', $date)->startOfDay();
        }

        // Any different scenario than the above should throw an Exception.
        throw new Exception(
            'Invalid date format. Not a valid ISO-8601 date string or Chronos object.'
        );
    }
}
