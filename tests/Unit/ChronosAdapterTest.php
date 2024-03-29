<?php

namespace RoussKS\FinancialYear\Carbon\Tests\Unit;

use Cake\Chronos\Chronos;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use RoussKS\FinancialYear\AbstractAdapter;
use RoussKS\FinancialYear\ChronosAdapter;
use RoussKS\FinancialYear\Exceptions\ConfigException;
use RoussKS\FinancialYear\Exceptions\Exception;

/**
 * Class ChronosAdapterTest
 *
 * @package RoussKS\FinancialYear\Tests\Unit
 */
class ChronosAdapterTest extends TestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var array
     */
    protected $fyTypes = [AbstractAdapter::TYPE_CALENDAR, AbstractAdapter::TYPE_BUSINESS];

    /**
     * BaseTestCase constructor.
     *
     * @param null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->faker = Factory::create();
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function settingSameFyWeeksSetsWeeksWithoutChangingEndDateForBusinessType(): void
    {
        $fiftyThreeWeeks = $this->faker->boolean;
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            $carbon,
            $fiftyThreeWeeks
        );

        $fyEndDate = $chronosAdapter->getFyEndDate();

        $chronosAdapter->setFyWeeks($fiftyThreeWeeks);

        $this->assertSame($fyEndDate->toDateTimeString(), $chronosAdapter->getFyEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function settingDifferentFyWeeksSetsWeeksWithDifferentEndDateForBusinessType(): void
    {
        $fiftyThreeWeeks = $this->faker->boolean;
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            $carbon,
            $fiftyThreeWeeks
        );

        $fyEndDate = $chronosAdapter->getFyEndDate();

        // Set the opposite of original weeks.
        $chronosAdapter->setFyWeeks(!$fiftyThreeWeeks);

        $this->assertNotSame($fyEndDate->toDateTimeString(), $chronosAdapter->getFyEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertGetFyStartDateReturnsCarbonImmutableObject(): void
    {
        $type = $this->faker->randomElement($this->fyTypes);
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            $type,
            $type === 'business' ?
                $carbon :
                $this->getRandomDateExcludingDisallowedFyCalendarTypeDates(),
            $this->faker->boolean
        );

        $this->assertInstanceOf(Chronos::class, $chronosAdapter->getFyStartDate());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertSetFyStartDateThrowsExceptionForInvalidDates(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage(
            'This library does not support 29, 30, 31 as start dates of a month for calendar type financial year.'
        );

        // Get a random Carbon instance with an invalid date.
        $dateTime = $this->faker->dateTime;

        // Random Year, random disallowed date. Fix to May as we know it includes all 3 dates.
        $dateTime->setDate(
            (int) $dateTime->format('Y'),
            5,
            $this->faker->randomElement([29, 30, 31])
        );

        $randomCarbon = Chronos::instance($dateTime);

        new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            $randomCarbon,
            $this->faker->boolean
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertSetFyStartDateSetsNewFyEndDateIfFyStartDateChanges(): void
    {
        $type = $this->faker->randomElement($this->fyTypes);
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            $type,
            $type === 'business' ?
                $carbon :
                $this->getRandomDateExcludingDisallowedFyCalendarTypeDates(),
            $this->faker->boolean
        );

        $originalFyStartDate = $chronosAdapter->getFyStartDate();

        $chronosAdapter->setFyStartDate(
            $type === 'business' ? $carbon : $this->getRandomDateExcludingDisallowedFyCalendarTypeDates()
        );

        $this->assertNotSame(
            $originalFyStartDate->toDateTimeString(),
            $chronosAdapter->getFyEndDate()->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertGetFyEndDateReturnsDateTimeImmutableObject(): void
    {
        $type = $this->faker->randomElement($this->fyTypes);
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            $type,
            $type === 'business' ?
                $carbon :
                $this->getRandomDateExcludingDisallowedFyCalendarTypeDates(),
            $this->faker->boolean
        );

        $this->assertInstanceOf(Chronos::class, $chronosAdapter->getFyEndDate());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetPeriodByIdReturnsCorrectTimePeriodForCalendarTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            $this->faker->boolean
        );

        // 2nd Period should be 2019-02-01 - 2019-02-28
        $period = $chronosAdapter->getPeriodById(2);

        $this->assertEquals('2019-02-01 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-02-28 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstPeriodByIdReturnsCorrectTimePeriodForCalendarTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            $this->faker->boolean
        );

        // 1st Period should be 2019-01-01 - 2019-01-31
        $period = $chronosAdapter->getPeriodById(1);

        $this->assertEquals('2019-01-01 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-01-31 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastPeriodByIdReturnsCorrectTimePeriodForCalendarTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            $this->faker->boolean
        );

        // Last Period, 12th for calendar type, should be 2019-12-01 - 2019-12-31
        $period = $chronosAdapter->getPeriodById(12);

        $this->assertEquals('2019-12-01 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-12-31 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetPeriodByIdReturnsCorrectTimePeriodForBusinessTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // 2nd Period should be 2019-01-29 - 2019-02-26
        $period = $chronosAdapter->getPeriodById(2);

        $this->assertEquals('2019-01-29 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-02-25 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstPeriodByIdReturnsCorrectTimePeriodForBusinessTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // 1st Period should be 2019-01-01 - 2019-01-28
        $period = $chronosAdapter->getPeriodById(1);

        $this->assertEquals('2019-01-01 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-01-28 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastPeriodByIdReturnsCorrectTimePeriodForBusinessTypeFinancialYearFiftyTwoWeeks(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            false
        );

        // Last Period, 13th for business type, should be 2019-12-03 - 2019-12-30 for 52 weeks year.
        $period = $chronosAdapter->getPeriodById(13);

        $this->assertEquals('2019-12-03 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-12-30 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastPeriodByIdReturnsCorrectTimePeriodForBusinessTypeFinancialYearFiftyThreeWeeks(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            true
        );

        // Last Period, 13th for business type, should be 2019-12-03 - 2020-01-06 for 53 weeks year.
        $period = $chronosAdapter->getPeriodById(13);

        $this->assertEquals('2019-12-03 00:00:00', $period->getStartDate()->toDateTimeString());
        $this->assertEquals('2020-01-06 00:00:00', $period->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekByIdThrowsExceptionOnNonBusinessTypeFinancialYearType(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Week id is not applicable for non business type financial year.');

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            true
        );

        $chronosAdapter->getBusinessWeekById(1);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertGetBusinessWeekByIdThrowsExceptionOnInvalidWeekId(): void
    {
        $this->expectException(Exception::class);

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Build an array of integers of the financial year weeks.
        $fyWeeksArray = [];

        for ($i = 1; $i <= $chronosAdapter->getFyWeeks(); $i++) {
            $fyWeeksArray[] = $i;
        }

        // Get a random week id that's not equal to the available weeks.
        do {
            $randomWeekId = random_int(-1000, 1000);
        } while (in_array($randomWeekId, $fyWeeksArray, true));

        // Set the expected message after we have set the financial year weeks
        $this->expectExceptionMessage('There is no week with id: ' . $randomWeekId);

        $chronosAdapter->getBusinessWeekById($randomWeekId);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekByIdReturnsCorrectWeekPeriodForBusinessTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // 2nd week should be 2019-01-08 - 2019-01-14.
        $week = $chronosAdapter->getBusinessWeekById(2);

        $this->assertEquals('2019-01-08 00:00:00', $week->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-01-14 00:00:00', $week->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekByIdReturnsCorrectWeekPeriodForFirstWeekOfBusinessTypeFinancialYear(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // First week should be 2019-01-01 - 2019-01-07.
        $firstWeek = $chronosAdapter->getBusinessWeekById(1);

        $this->assertEquals('2019-01-01 00:00:00', $firstWeek->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-01-07 00:00:00', $firstWeek->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekByIdReturnsCorrectWeekPeriodForLastWeekOfBusinessTypeFinancialYearFiftyTwoWeeks(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            false
        );

        // Last week should be 2019-12-24 - 2019-12-30 for 52 weeks year.
        $lastWeek = $chronosAdapter->getBusinessWeekById(52);

        $this->assertEquals('2019-12-24 00:00:00', $lastWeek->getStartDate()->toDateTimeString());
        $this->assertEquals('2019-12-30 00:00:00', $lastWeek->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekByIdReturnsCorrectWeekPeriodForLastWeekOfBusinessTypeFinancialYearFiftyThreeWeeks(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            true
        );

        // Last week should be 2019-12-31 - 2020-01-06 for 53 weeks year.
        $lastWeek = $chronosAdapter->getBusinessWeekById(53);

        $this->assertEquals('2019-12-31 00:00:00', $lastWeek->getStartDate()->toDateTimeString());
        $this->assertEquals('2020-01-06 00:00:00', $lastWeek->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetPeriodIdByDateThrowsExceptionOnDateBeforeFinancialYear(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The requested date is out of range of the current financial year.');

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            $this->faker->randomElement($this->fyTypes),
            '2019-01-01',
            $this->faker->boolean
        );

        $chronosAdapter->getPeriodIdByDate('2018-12-31');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetPeriodIdByDateThrowsExceptionOnDateAfterFinancialYear(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The requested date is out of range of the current financial year.');

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            $this->faker->randomElement($this->fyTypes),
            '2019-01-01',
            $this->faker->boolean
        );

        // 2020-01-07 is out of range even if the type is business and weeks 53, if start date is 2019-01-01
        $chronosAdapter->getPeriodIdByDate('2020-01-07');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetPeriodIdByDateReturnsCorrectIdForDate(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            $this->faker->randomElement($this->fyTypes),
            '2019-01-01',
            $this->faker->boolean
        );

        // 2019-02-07 belongs to 2nd period for both types
        $this->assertEquals(2, $chronosAdapter->getPeriodIdByDate('2019-02-07'));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekIdByDateThrowsExceptionOnDateBeforeFinancialYear(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The requested date is out of range of the current financial year.');

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        $chronosAdapter->getBusinessWeekIdIdByDate('2018-12-31');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekIdByDateThrowsExceptionOnDateAfterFinancialYear(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The requested date is out of range of the current financial year.');

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // 2020-01-07 is out of range even if the type is business and weeks 53, if start date is 2019-01-01
        $chronosAdapter->getBusinessWeekIdIdByDate('2020-01-07');
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetBusinessWeekIdByDateReturnsCorrectIdForDate(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // 2019-01-31 belongs to 5th week
        $this->assertEquals(5, $chronosAdapter->getBusinessWeekIdIdByDate('2019-01-31'));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertGetFirstDateOfPeriodByIdReturnsFinancialYearStartDateForFirstPeriod(): void
    {
        $type = $this->faker->randomElement($this->fyTypes);
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            $type,
            $type === 'business' ?
                $carbon :
                $this->getRandomDateExcludingDisallowedFyCalendarTypeDates(),
            $this->faker->boolean
        );

        $this->assertSame($chronosAdapter->getFyStartDate(), $chronosAdapter->getFirstDateOfPeriodById(1));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstDateOfPeriodByIdReturnsCorrectDateForCalendarType(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            $this->faker->boolean
        );

        $this->assertEquals(
            '2019-04-01 00:00:00',
            $chronosAdapter->getFirstDateOfPeriodById(4)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstDateOfPeriodByIdReturnsCorrectDateForBusinessType(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        $this->assertEquals(
            '2019-12-03 00:00:00',
            $chronosAdapter->getFirstDateOfPeriodById(13)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function assertGetLastDateOfPeriodByIdReturnsFinancialYearEndDateForLastPeriod(): void
    {
        $type = $this->faker->randomElement($this->fyTypes);
        $carbon = Chronos::instance($this->faker->dateTime);

        $chronosAdapter = new ChronosAdapter(
            $type,
            $type === 'business' ?
                $carbon :
                $this->getRandomDateExcludingDisallowedFyCalendarTypeDates(),
            $this->faker->boolean
        );

        $this->assertSame(
            $chronosAdapter->getFyEndDate(),
            $chronosAdapter->getLastDateOfPeriodById($chronosAdapter->getFyPeriods())
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastDateOfPeriodByIdReturnsCorrectDateForCalendarType(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_CALENDAR,
            '2019-01-01',
            $this->faker->boolean
        );

        $this->assertEquals(
            '2019-04-30 00:00:00',
            $chronosAdapter->getLastDateOfPeriodById(4)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastDateOfPeriodByIdReturnsCorrectDateForBusinessType(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        $this->assertEquals(
            '2019-12-02 00:00:00',
            $chronosAdapter->getLastDateOfPeriodById(12)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstDateOfBusinessWeekByIdReturnsFinancialYearStartDateForFirstWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        $this->assertSame($chronosAdapter->getFyStartDate(), $chronosAdapter->getFirstDateOfBusinessWeekById(1));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstDateOfBusinessWeekByIdReturnsCorrectDate(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test start of week 49 (start of period 13).
        // Expect 2019-12-03.
        $this->assertEquals(
            '2019-12-03 00:00:00',
            $chronosAdapter->getFirstDateOfBusinessWeekById(49)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastDateOfBusinessWeekByIdReturnsFinancialYearEndDateForLastWeekWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Use the weeks that are already set in the adapter.
        $this->assertSame(
            $chronosAdapter->getFyEndDate(),
            $chronosAdapter->getLastDateOfBusinessWeekById($chronosAdapter->getFyWeeks())
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetLastDateOfBusinessWeekByIdReturnsCorrectDate(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test end of week 49.
        // Expect 2019-12-09.
        $this->assertEquals(
            '2019-12-09 00:00:00',
            $chronosAdapter->getLastDateOfBusinessWeekById(49)->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFirstBusinessWeekByPeriodIdReturnsCorrectWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test first week of period 13. That is week 49. 2019-12-03 - 2019-12-09.
        $firstBusinessWeekOfPeriod = $chronosAdapter->getFirstBusinessWeekByPeriodId(13);

        $this->assertEquals(
            '2019-12-03 00:00:00',
            $firstBusinessWeekOfPeriod->getStartDate()->toDateTimeString()
        );

        $this->assertEquals(
            '2019-12-09 00:00:00',
            $firstBusinessWeekOfPeriod->getEndDate()->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetSecondBusinessWeekByPeriodIdReturnsCorrectWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test second week of period 12. That is week 46. 2019-11-12 - 2019-11-18.
        $secondBusinessWeekOfPeriod = $chronosAdapter->getSecondBusinessWeekByPeriodId(12);

        $this->assertEquals(
            '2019-11-12 00:00:00',
            $secondBusinessWeekOfPeriod->getStartDate()->toDateTimeString()
        );

        $this->assertEquals(
            '2019-11-18 00:00:00',
            $secondBusinessWeekOfPeriod->getEndDate()->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetThirdBusinessWeekByPeriodIdReturnsCorrectWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test third week of period 11. That is week 43. 2019-10-22 - 2019-10-28.
        $thirdBusinessWeekOfPeriod = $chronosAdapter->getThirdBusinessWeekOfPeriodId(11);

        $this->assertEquals(
            '2019-10-22 00:00:00',
            $thirdBusinessWeekOfPeriod->getStartDate()->toDateTimeString()
        );

        $this->assertEquals(
            '2019-10-28 00:00:00',
            $thirdBusinessWeekOfPeriod->getEndDate()->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFourthBusinessWeekByPeriodIdReturnsCorrectWeek(): void
    {
        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            $this->faker->boolean
        );

        // Test fourth week of period 11. That is week 44. 2019-10-29 - 2019-11-04.
        $fourthBusinessWeekOfPeriod = $chronosAdapter->getFourthBusinessWeekByPeriodId(11);

        $this->assertEquals(
            '2019-10-29 00:00:00',
            $fourthBusinessWeekOfPeriod->getStartDate()->toDateTimeString()
        );

        $this->assertEquals(
            '2019-11-04 00:00:00',
            $fourthBusinessWeekOfPeriod->getEndDate()->toDateTimeString()
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetFiftyThirdBusinessWeekByPeriodIdReturnsCorrectWeek(): void
    {
        // Week 53 is only available for the relevant year and is the last week of the year.

        // Financial Year starts at 2019-01-01
        $chronosAdapter = new ChronosAdapter(
            AbstractAdapter::TYPE_BUSINESS,
            '2019-01-01',
            true
        );

        // Expect fifty third week range: 2019-12-31 - 2020-01-06
        $fiftyThreeWeek = $chronosAdapter->getFiftyThirdBusinessWeek();

        $this->assertEquals(
            '2019-12-31 00:00:00',
            $fiftyThreeWeek->getStartDate()->toDateTimeString()
        );

        $this->assertEquals('2020-01-06 00:00:00', $fiftyThreeWeek->getEndDate()->toDateTimeString());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws ConfigException
     * @throws Exception
     */
    public function assertGetDateObjectThrowsExceptionForInvalidDateType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Invalid date format. Not a valid ISO-8601 date string or Chronos object.'
        );

        new ChronosAdapter(
            $this->faker->randomElement($this->fyTypes),
            $this->faker->randomNumber(4), // Send random number, as string is covered by Carbon itself.
            $this->faker->boolean
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws Exception
     * @throws ConfigException
     * @throws \Exception
     */
    public function assertExceptionOnInvalidPeriodIdForCalendarTypeFinancialYear(): void
    {
        $startDate = new Chronos('2019-01-01');

        $fy = new ChronosAdapter('calendar', $startDate);

        $fyPeriodsArray = [];

        for ($i = 1; $i <= $fy->getFyPeriods(); $i++) {
            $fyPeriodsArray[] = $i;
        }

        do {
            $randomPeriodId = random_int(-1000, 1000);
        } while (in_array($randomPeriodId, $fyPeriodsArray, true));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('There is no period with id: ' . $randomPeriodId . '.');

        // A Calendar Type Financial Year has 12 periods only.
        $fy->getPeriodById($randomPeriodId);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws Exception
     * @throws ConfigException
     * @throws \Exception
     */
    public function assertExceptionOnInvalidPeriodIdForBusinessTypeFinancialYear(): void
    {
        $startDate = new Chronos('2019-01-01');

        $fy = new ChronosAdapter('business', $startDate);

        $fyPeriodsArray = [];

        for ($i = 1; $i <= $fy->getFyPeriods(); $i++) {
            $fyPeriodsArray[] = $i;
        }

        do {
            $randomPeriodId = random_int(-1000, 1000);
        } while (in_array($randomPeriodId, $fyPeriodsArray, true));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('There is no period with id: ' . $randomPeriodId . '.');

        // A Calendar Type Financial Year has 12 periods only.
        $fy->getPeriodById($randomPeriodId);
    }

    /**
     * Generate a random date excluding the ones disallowed for calendar type financial year.
     *
     * @return Chronos
     */
    protected function getRandomDateExcludingDisallowedFyCalendarTypeDates(): Chronos
    {
        $dateTime = $this->faker->dateTime;

        // Random Year, Random month, random date in range 1-28.
        $dateTime->setDate(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('m'),
            $this->faker->numberBetween(1, 28)
        );

        return Chronos::instance($dateTime);
    }
}
