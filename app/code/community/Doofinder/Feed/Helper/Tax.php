<?php

class Doofinder_Feed_Helper_Tax extends Mage_Tax_Helper_Data
{
    public function needPriceConversion($store = null)
    {
        return true;
    }
}
