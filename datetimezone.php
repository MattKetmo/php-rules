<?php

class DateTimeTest extends PHPUnit_Framework_TestCase
{
    const SIMPLE_FORMAT = 'Y-m-d H:i:s';

    public function setUp()
    {
        // Make those test independant from your PHP config
        date_default_timezone_set('UTC');
    }

    /** @test */
    public function default_1st_argument_is_now()
    {
        $date1 = new DateTime();
        $date2 = new DateTime('now');

        $this->assertEquals($date1, $date2);
    }

    /**
     * If you don't specify a timezone, then the default config is used.
     *
     * It may be dangerous as it relies on a global configuration and can
     * be changed at any time, or can be different on another process.
     * So you don't masterize it.
     *
     * @test
     */
    public function default_2nd_argument_is_the_timezone_defined_by_the_config()
    {
        // Explicitly specify timezone in the constructor
        $date1 = new DateTime('2014-01-01 12:00:00', new DateTimeZone('America/Los_Angeles'));

        // Implicitly rely on PHP configuration via `date_default_timezone_set` (or php.ini)
        date_default_timezone_set('America/Los_Angeles');
        $date2 = new DateTime('2014-01-01 12:00:00');

        $this->assertEquals($date1->format(self::SIMPLE_FORMAT), $date2->format(self::SIMPLE_FORMAT));
        $this->assertEquals('America/Los_Angeles', $date1->getTimezone()->getName());
        $this->assertEquals('America/Los_Angeles', $date2->getTimezone()->getName());
    }

    /**
     * When the same format is used to instanciate 2 DateTime, but you change the
     * timezone value, then you end with 2 different timestamps.
     *
     * This means you should never instanciate a DateTime without knowing explicitly
     * its timezone. For instance, if you store '2014-01-01 12:00:00' in a MySQL
     * DATETIME column, then 2 processes which create a new DateTime object from this value
     * without the timezone argument will differ if their configuration is not the same.
     *
     * @test
     */
    public function datetime_with_same_format_but_different_timezone_are_not_equals()
    {
        $date1 = new DateTime('2014-01-01 12:00:00', new DateTimeZone('Europe/Paris'));
        $date2 = new DateTime('2014-01-01 12:00:00', new DateTimeZone('America/Los_Angeles'));

        $this->assertEquals($date1->format(self::SIMPLE_FORMAT), $date2->format(self::SIMPLE_FORMAT));
        $this->assertNotEquals($date1->getTimestamp(), $date2->getTimestamp());

        $this->assertEquals('Europe/Paris', $date1->getTimezone()->getName());
        $this->assertEquals('America/Los_Angeles', $date2->getTimezone()->getName());
    }

    /**
     * This is the same as previous test, except we don't explicitly set the timezone
     * of the DateTime objects but rely on the global config.
     *
     * @test
     */
    public function datetime_with_same_format_but_different_timezone_from_config_are_not_equals()
    {
        date_default_timezone_set('Europe/Paris');
        $date1 = new DateTime('2014-01-01 12:00:00');

        date_default_timezone_set('America/Los_Angeles');
        $date2 = new DateTime('2014-01-01 12:00:00');

        $this->assertEquals($date1->format(self::SIMPLE_FORMAT), $date2->format(self::SIMPLE_FORMAT));
        $this->assertNotEquals($date1->getTimestamp(), $date2->getTimestamp());

        $this->assertEquals('Europe/Paris', $date1->getTimezone()->getName());
        $this->assertEquals('America/Los_Angeles', $date2->getTimezone()->getName());
    }

    /**
     * When you specify a timezone in the 1st argument of the DateTime object, then
     * the second argument is ignored.
     *
     * @test
     */
    public function datetime_instanciated_with_same_format_containing_timezone_are_equals()
    {
        $date1 = new DateTime('2014-01-01 12:00:00 +0000', new DateTimeZone('Europe/Paris'));
        $date2 = new DateTime('2014-01-01 12:00:00 +0000', new DateTimeZone('America/Los_Angeles'));

        $this->assertEquals($date1->format(self::SIMPLE_FORMAT), $date2->format(self::SIMPLE_FORMAT));
        $this->assertEquals($date1->getTimestamp(), $date2->getTimestamp());
        $this->assertEquals('+00:00', $date1->getTimezone()->getName());
    }

    /**
     * Using a timestamp as the first argument is like using the UTC timezone.
     * Then 2nd argument is ignored.
     *
     * @test
     */
    public function datetime_instanciated_with_same_timezone_are_equals()
    {
        $date1 = new DateTime('@1388577600', new DateTimeZone('Europe/Paris'));
        $date2 = new DateTime('@1388577600', new DateTimeZone('America/Los_Angeles'));

        $this->assertEquals($date1->format(self::SIMPLE_FORMAT), $date2->format(self::SIMPLE_FORMAT));
        $this->assertEquals($date1->getTimestamp(), $date2->getTimestamp());
        $this->assertEquals('+00:00', $date1->getTimezone()->getName());
    }

    /**
     * RULE #1 - Never format without a timezone.
     *
     * Never use the `DateTime::format()` method without a timezone to save or
     * serialize a DateTime object and use the result to recreate a new instance.
     *
     * In other words, don't loose the timezone property of your DateTime instance.
     *
     * @test
     */
    public function rule_1_never_use_the_format_method_without_timezone()
    {
        $date1 = new DateTime('now');
        $simpleFormat = $date1->format(self::SIMPLE_FORMAT); // eg. 2014-01-01 12:00:00
        $tzFormat = $date1->format(DateTime::ISO8601);       // eg. 2014-01-01T12:00:00+0000
        $timestamp = $date1->getTimestamp();                 // eg. 1414486361

        // You're not garanteed that the timezone will be the same the
        // moment you want to deserialize the dumped datetime.
        date_default_timezone_set('Europe/Paris');
        $date2 = DateTime::createFromFormat(self::SIMPLE_FORMAT, $simpleFormat);
        $this->assertNotEquals($date1, $date2);

        // Always use a format which have the timezone value, such as the
        // ISO8601 format, or a timestamp
        date_default_timezone_set('Europe/Paris');
        $date2 = DateTime::createFromFormat(DateTime::ISO8601, $tzFormat);
        $this->assertEquals($date1, $date2);

        $date2 = new DateTime("@$timestamp");
        $this->assertEquals($date1, $date2);

        // However if (and only if) you're sure the "simple format" provides from
        // an UTC datetime, you can use this format to instanciate a new object
        // but explicitly set the timezone.
        // This can be useful when you store the date in a MySQL DATETIME
        // column, because you can't save the timezone.
        $date2 = DateTime::createFromFormat(self::SIMPLE_FORMAT, $simpleFormat, new DateTimeZone('UTC'));
        $this->assertEquals($date1, $date2);
    }

    /**
     * RULE #2 - Display to the user according to a specified timezone.
     *
     * When you have to display a date to an end user, always use the timezone
     * he expects to see, don't keep the timezone of the DateTime object.
     *
     * This timezone is generally guessed from the user profile, his current location,
     * the configuration of the site he browses, or can be attached to another
     * location (eg. the timezone of the departure of your journey).
     *
     * @test
     */
    public function rule_2_display_to_end_user_accordind_to_specified_timezone()
    {
        $dateUtc = new DateTime('2014-08-01 12:00:00 +0000');
        $expectedTz = 'Europe/Paris';

        // Using a temporary datetime object
        $dateToDisplay = clone $dateUtc;
        $dateToDisplay->setTimezone(new DateTimeZone($expectedTz));
        $this->assertEquals('2014-08-01 14:00:00', $dateToDisplay->format(self::SIMPLE_FORMAT));

        // Using a formatter
        // Warning: the format of the pattern is not the same as the DateTime::format() method.
        // See http://userguide.icu-project.org/formatparse/datetime
        $formatter = new IntlDateFormatter(null, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $expectedTz);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');
        $this->assertEquals('2014-08-01 14:00:00', $formatter->format($dateUtc));
    }
}
