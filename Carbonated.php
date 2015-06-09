<?php

namespace JerseyMilker;

use Carbon\Carbon;

trait CarbonatedDateTime {

    /**
     * Store carbon instances for use by carbon accessor.
     *
     * @var \Carbon\Carbon
     */
    protected $carbonInstances;

    /**
     * Get the intended timestamp format for displaying to end user.
     *
     * @return string
     */
    public function carbonatedTimestampFormat()
    {
        return ($this->carbonateTimestampFormat) ? $this->carbonateTimestampFormat : 'M d, Y g:ia';
    }

    /**
     * Get the intended date format for displaying to end user.
     *
     * @return string
     */
    public function carbonatedDateFormat()
    {
        return ($this->carbonateDateFormat) ? $this->carbonateDateFormat : 'M d, Y';
    }

    /**
     * Get the intended date format for displaying to end user.
     *
     * @return string
     */
    public function carbonatedTimeFormat()
    {
        return ($this->carbonateTimeFormat) ? $this->carbonateTimeFormat : 'g:ia';
    }

    /**
     * Get the intended timezone for displaying to end user.
     *
     * @return array
     */
    public function carbonatedTimezone()
    {
        // Check for timezone property in this class.
        if ($this->timezone) {
            return $this->timezone;
        }

        // If not, check for timezone in authenticated User class.
        // TODO: Avoid error when app does not have user authentication?
        elseif (\Auth::check() && method_exists(config('auth.model'), 'getTimezone')) {
            return \Auth::user()->getTimezone();
        }

        // Otherwise return timezone setting from config.
        return config('app.timezone');
    }

    /**
     * Get the intended database format for timestamp storage.
     *
     * @return string
     */
    protected function databaseTimestampFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the intended database format for date storage.
     *
     * @return string
     */
    protected function databaseDateFormat()
    {
        return 'Y-m-d';
    }

    /**
     * Get the intended database format for time storage.
     *
     * @return string
     */
    protected function databaseTimeFormat()
    {
        return 'H:i:s';
    }

    /**
     * Get the intended timezone for database storage.
     *
     * @return string
     */
    protected function databaseTimezone()
    {
        return config('app.timezone');
    }

    /**
     * Return an object containing carbon instances of all specified date/time fields.
     *
     * @return \Carbon\Carbon
     */
    public function getCarbonAttribute()
    {
        // Check if date/time fields have already been carbonated.
        if ($this->carbonatedInstances) {
            return $this->carbonatedInstances;
        }

        // If not, let's carbonate them.
        foreach ($this->getCarbonatedTimestamps() as $key) {
            $carbonInstances[$key] = $this->getOriginal($key) ? Carbon::createFromFormat($this->databaseTimestampFormat(), $this->getOriginal($key))->timezone($this->carbonatedTimezone()) : null;
        }
        foreach ($this->getCarbonatedDates() as $key) {
            $carbonInstances[$key] = $this->getOriginal($key) ? Carbon::createFromFormat($this->databaseDateFormat(), $this->getOriginal($key))->timezone($this->carbonatedTimezone()) : null;
        }
        foreach ($this->getCarbonatedTimes() as $key) {
            $carbonInstances[$key] = $this->getOriginal($key) ? Carbon::createFromFormat($this->databaseTimeFormat(), $this->getOriginal($key))->timezone($this->carbonatedTimezone()) : null;
        }

        // And store carbonated instances for future use.
        $this->carbonatedInstances = ($carbonInstances) ? (object) $carbonInstances : null;

        return $this->carbonatedInstances;
    }

    /**
     * Get the attributes that should be handled as carbonated timestamps.
     *
     * @return array
     */
    protected function getCarbonatedTimestamps()
    {
        $defaults = [static::CREATED_AT, static::UPDATED_AT, 'deleted_at'];

        return (isset($this->carbonateTimestamps)) ? array_merge($this->carbonateTimestamps, $defaults) : $defaults;
    }

    /**
     * Get the attributes that should be handled as carbonated dates.
     *
     * @return array
     */
    protected function getCarbonatedDates()
    {
        return (isset($this->carbonateDates)) ? (array) $this->carbonateDates : [];
    }

    /**
     * Get the attributes that should be handled as carbonated times.
     *
     * @return array
     */
    protected function getCarbonatedTimes()
    {
        return (isset($this->carbonateTimes)) ? (array) $this->carbonateTimes : [];
    }

    /**
     * Get final timestamp string for displaying to end user.
     *
     * @param  string  $key
     * @return string
     */
    protected function viewableTimestamp($key)
    {
        return $this->carbon->$key ? $this->carbon->$key->format($this->carbonatedTimestampFormat()) : null;
    }

    /**
     * Get final date string for displaying to end user.
     *
     * @param  string  $key
     * @return string
     */
    protected function viewableDate($key)
    {
        return $this->carbon->$key ? $this->carbon->$key->format($this->carbonatedDateFormat()) : null;
    }

    /**
     * Get final time string for displaying to end user.
     *
     * @param  string  $key
     * @return string
     */
    protected function viewableTime($key)
    {
        return $this->carbon->$key ? $this->carbon->$key->format($this->carbonatedTimeFormat()) : null;
    }

    /**
     * Mutate incoming timestamp for database storage.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function storableTimestamp($value)
    {
        // If Eloquent returns a fresh timestamp, it will already be a carbon object with timezone for database.
        if (is_object($value)) {
            return $value->format($this->databaseTimestampFormat());
        }

        // Otherwise carbonate incoming string before reformatting.
        return ($value) ? Carbon::createFromFormat($this->carbonatedTimestampFormat(), $value, $this->carbonatedTimezone())->timezone($this->databaseTimezone())->format($this->databaseTimestampFormat()) : null;
    }

    /**
     * Mutate incoming date for database storage.
     *
     * @param  string  $value
     * @return string
     */
    protected function storableDate($value)
    {
        return ($value) ? Carbon::createFromFormat($this->carbonatedDateFormat(), $value, $this->carbonatedTimezone())->timezone($this->databaseTimezone())->format($this->databaseDateFormat()) : null;
    }

    /**
     * Mutate incoming time for database storage.
     *
     * @param  string  $value
     * @return string
     */
    protected function storableTime($value)
    {
        return ($value) ? Carbon::createFromFormat($this->carbonatedTimeFormat(), $value, $this->carbonatedTimezone())->timezone($this->databaseTimezone())->format($this->databaseTimeFormat()) : null;
    }

    /**
     * Override default getDates() to allow created_at and updated_at handling by carbonated.
     *
     * @return array
     */
    public function getDates()
    {
        return (array) $this->dates;
    }

    /**
     * Override default freshTimestamp() to be more explicit in setting timezone for storage.
     *
     * @return \Carbon\Carbon
     */
    public function freshTimestamp()
    {
        return Carbon::now($this->databaseTimezone());
    }

    /**
     * Override default getAttributeValue() to include our own accessors.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        // First we will check for the presence of a mutator in our model.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If no accessor found, reference our own accessors for relevant date/time fields.
        if (in_array($key, $this->getCarbonatedTimestamps())) {
            $value = $this->viewableTimestamp($key);
        } elseif (in_array($key, $this->getCarbonatedDates())) {
            $value = $this->viewableDate($key);
        } elseif (in_array($key, $this->getCarbonatedTimes())) {
            $value = $this->viewableTime($key);
        }

        // Otherwise, revert to default Eloquent behavour.
        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        elseif (in_array($key, $this->getDates())) {
            if (!is_null($value)) {
                return $this->asDateTime($value);
            }
        }

        return $value;
    }

    /**
     * Override default setAttribute() to include our own mutators.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // First we will check for the presence of a mutator in our model.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.studly_case($key).'Attribute';

            return $this->{$method}($value);
        }

        // If no mutator found, reference our own mutators for relevant date/time fields.
        elseif (in_array($key, $this->getCarbonatedTimestamps())) {
            $value = $this->storableTimestamp($value);
        } elseif (in_array($key, $this->getCarbonatedDates())) {
            $value = $this->storableDate($value);
        } elseif (in_array($key, $this->getCarbonatedTimes())) {
            $value = $this->storableTime($value);
        }

        // Otherwise, revert to default Eloquent behavour.
        elseif (in_array($key, $this->getDates()) && $value) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isJsonCastable($key)) {
            $value = json_encode($value);
        }

        $this->attributes[$key] = $value;
    }

}