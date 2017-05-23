<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Settings_Panel_LayerDescription extends Doofinder_Feed_Block_Settings_Panel_Description
{
  protected $level = self::WARNING;
  protected $description = '<b>IMPORTANT:</b> You must configure a different Layer script for each store view. Use the "Current Configuration Scope" selector at the top left side of the page to choose a store view.';
}
