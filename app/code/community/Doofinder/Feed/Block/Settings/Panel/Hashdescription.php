<?php
class Doofinder_Feed_Block_Settings_Panel_HashDescription extends Doofinder_Feed_Block_Settings_Panel_Description
{
  protected $level = self::WARNING;
  protected $description = '<b>IMPORTANT:</b> You must configure a "hashid" for each store view. Use the "Current Configuration Scope" selector at the top left side of the page to choose a store view.';
}
