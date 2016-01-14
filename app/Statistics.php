<?php

class Statistics extends Core
{
    /**
     * name: Statistics::cityCount
     * @param none
     * @return var
     *
    */
    public static function cityCount()
    {
        return static::getDb()->sqlFetchField("SELECT count(DISTINCT(city)) FROM profile");
    }

    /**
     * name: Statistics::lastWeek
     * @param none
     * @return var
     *
     */
    public static function lastWeek()
    {
        return static::getDb()->sqlFetchField("SELECT count(user_id) FROM profile WHERE registration_date BETWEEN now() -  interval 7 day AND now()");
    }

    /**
     * name: Statistics::userCount
     * @param none
     * @return var
     *
     */
    public static function userCount()
    {
        return static::getDb()->sqlFetchField("SELECT COUNT(id) FROM user WHERE usertype='profile'");
    }
}
