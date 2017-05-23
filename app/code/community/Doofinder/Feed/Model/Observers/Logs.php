<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Model_Observers_Logs
{
    const MAX_SIZE = 1000;
    const BATCH_LIMIT = 100;

    /**
     * Clear logs that are beyond the limit
     *
     * @param Varien_Event_Observer $observer
     */
    public function clearLogs($observer)
    {
        $collection = Mage::getModel('doofinder_feed/log')->getCollection();

        $size = $collection->getSize();

        if ($size > static::MAX_SIZE) {
            $collection->setOrder('id', $collection::SORT_ORDER_DESC);

            $offset = max(static::MAX_SIZE, $size - static::BATCH_LIMIT);

            $collection->getSelect()
                ->limit(static::BATCH_LIMIT, $offset);

            $ids = array();
            foreach ($collection->getItems() as $item) {
                $item->delete();
                $ids[] = $item->id;
            }

            Mage::log($ids, null, 'debug.log');
        }
    }
}
