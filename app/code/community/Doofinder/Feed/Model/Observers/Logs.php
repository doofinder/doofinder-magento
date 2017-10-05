<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   Models
 * @package    Doofinder_Feed
 * @version    1.8.18
 */

class Doofinder_Feed_Model_Observers_Logs
{
    const MAX_SIZE = 1000;
    const BATCH_LIMIT = 100;

    /**
     * Clear logs that are beyond the limit
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param Varien_Event_Observer $observer
     */
    // @codingStandardsIgnoreStart
    public function clearLogs($observer)
    {
    // @codingStandardsIgnoreEnd
        $collection = Mage::getModel('doofinder_feed/log')->getCollection();

        $size = $collection->getSize();

        if ($size > static::MAX_SIZE) {
            $collection->setOrder('id', $collection::SORT_ORDER_DESC);

            $offset = max(static::MAX_SIZE, $size - static::BATCH_LIMIT);

            // @codingStandardsIgnoreStart
            $collection->getSelect()->limit(static::BATCH_LIMIT, $offset);
            // @codingStandardsIgnoreEnd

            $ids = array();
            foreach ($collection->getItems() as $item) {
                // @codingStandardsIgnoreStart
                $item->delete();
                // @codingStandardsIgnoreEnd
                $ids[] = $item->id;
            }

            Mage::log($ids, null, 'debug.log');
        }
    }
}
