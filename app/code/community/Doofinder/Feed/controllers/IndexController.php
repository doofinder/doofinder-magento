<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   controllers
 * @package    Doofinder_Feed
 * @version    1.5.3
 */

/**
 * Index controller for Doofinder Feed
 *
 * @version    1.5.3
 * @package    Doofinder_Feed
 */
class Doofinder_Feed_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->_redirect('/');
    }

    public function testAction() {
        $time = array(12,0,0);
        $timeStr = strtotime('12:0:0');
        $month = 0;
        $day = 0;
        var_dump(date('Z'));

        var_dump(date('H-i-s'));


        $timescheduled = strftime("%Y-%m-%d %H:%M:%S", mktime($time[0], $time[1], $time[2], date("m") + $month, date("d") + $day, date("Y")));
        $strTime = strtotime('2015-06-11 12:00:00');
        var_dump(date('Y-m-d H-i-s', $strTime));
        echo $timescheduled;
    }
}
