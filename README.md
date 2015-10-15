doofinder-magento
=================

# How to configure Doofinder in Magento

## Table Of Contents

1. [Pre-requisites: Cron](#system)
    2. [Microsoft Windows](#system-windows)
    2. [UNIX](#system-unix)
    2. [cPanel](#system-cpanel)
1. [Installation](#installation)
    2. [Install from Magento Connect](#installation-connect)
    2. [Install from a ZIP package file](#installation-zip)
1. [Module Configuration](#module)
    2. [Product Data Feed Configuration](#module-feed)
        3. [Feed Settings](#module-feed-settings)
        3. [Feed Attributes](#module-feed-attributes)
        3. [Schedule Settings](#module-feed-schedule)
            4. [Setting the Step Size](#module-feed-schedule-step)
            4. [On-demand generation](#module-feed-schedule-ondemand)
        3. [Feed Status](#module-feed-status)
    2. [Search Configuration](#module-search)
        3. [Doofinder Layer](#module-search-layer)
1. [Troubleshooting](#help)
1. [Uninstall](#uninstall)

<a name="system"></a>
## Pre-requisites: Cron

This version of the module introduces the scheduled generation of the Doofinder data feed. To use this new feature you will have to properly configure Cron in your system.

__If you are already using scheduled tasks in your store you can skip this section. Just make sure you know the interval you are using to execute the Cron script because you need that value to configure the module.__

Magento comes with two cron scripts: `cron.php` and `cron.sh`. The latter will only work in UNIX-compatible systems.

As stated in the Magento documentation site, the recommended frequency values to execute the Cron script are:

- __Magento Enterprise Edition:__ 1 minute
- __Magento Community Edition:__ 5 minutes

<a name="system-windows"></a>
### Microsoft Windows

In Windows you have to create a scheduled task that will execute the `cron.php`file that comes at the root path of your Magento installation.

The task should run the following command every 1 or 5 minutes depending on your Magento version:

    C:\path\to\your\magento\cron.php

Remember to adapt the path to the file to match your system.

<a name="system-unix"></a>
### UNIX

You can use the crontab service command if you have shell access to your server. Alternatively add it through your cPanel or similar hosting dashboard.

Run the script every 1 or 5 minutes depending on your Magento version.

Your crontab should look like this (assuming you use Magento Community Edition):

    */5 * * * * /bin/sh /path/to/your/magento/cron.sh

Notice that we are pointing to `cron.sh` and not to `cron.php`. That shell script has some internals to check processes and that kind of stuff.

Remember to adapt the path to the file to match your system.

<a name="system-cpanel"></a>
### cPanel

Visit your cPanel, log in and choose _Cron Jobs_ under the _Advanced_ tab.

Configure the task to be run every 1 or 5 minutes depending on your Magento version.

Add a new Cron job for this command (assuming you are using Magento CE):

    */5 * * * * /bin/sh /path/to/your/magento/cron.sh

Notice that we are pointing to cron.sh and not to cron.php. That shell script has some internals to check processes and that kind of stuff.

Remember to adapt the path to the file to match your system.

Click _Add New Cron Job_ to save changes.

<a name="installation"></a>
## Installation

First of all, go to your store admin panel and log in as an Administrator.

Access the Magento Connect Manager through _System > Magento Connect > Magento Connect Manager_. You will have to authenticate again.

![Magento Connect Manager Menu](http://support.doofinder.com/customer/portal/attachments/529267)

<a name="installation-connect"></a>
### Install from Magento Connect

If you have an extension key from Magento Connect, paste it in the text box located under the _Install New Extensions_ panel. Click _Install_.

The extension key usually is:

    http://connect20.magentocommerce.com/community/Doofinder_Feed

But you can get it from the official Magento page:

<http://www.magentocommerce.com/magento-connect/catalog/product/view/id/19116/>

If you typed the extension key correctly a small table with information about the module will be displayed. Click _Proceed_ to actually install the module.

![Installing Doofinder from Magento Connect](http://support.doofinder.com/customer/portal/attachments/529268)

Check the information console to see whether the module has been installed correcly. The new module should appear on the modules list as `Doofinder_Feed`. If that's not the case, try to fix the errors by using the information available in the console.

<a name="installation-zip"></a>
### Install from a ZIP package file

If you've obtained the installation package directly from Doofinder as a compressed file, use the _Direct package file upload_ panel to select the ZIP file provided from your filesystem.

You can download Doofinder releases directly from [this page](https://github.com/doofinder/doofinder-magento/releases).

![Installing Doofinder from a package ZIP file](http://support.doofinder.com/customer/portal/attachments/529269)

Click _Upload_ and check the information console to see whether the module has been installed correcly. The new module should appear on the modules list as `Doofinder_Feed`. If that's not the case, try to fix the errors by using the information available in the console.

<a name="module"></a>
## Module Configuration

Each option of the module can be set globally or locally for a particular store view. All options are set as global by default and they define the way the feed is generated for each store view.

To change the options scope and set different parameters for a particular store view you will have to select the desired scope from the top left menu in the configuration page. Then you will have to uncheck the Use Default option for the setting you want to override. This way the option will refer to a particular store only.

To access the module configuration go to your store admin panel and log in as an Administrator.

Go to the Configuration section through the System menu.

Look for the Doofinder Search section in the menu located at the left of the page.

You will see two configuration menus: _Product Data Feed_ and _Search Configuration_.

__Notice:__ The first time you install the module you can receive a HTTP 404 error message (Page Not Found) at this stage. If this is the case, clean the Magento cache from _System > Cache Management_. Then log out and log in again.

<a name="module-feed"></a>
### Product Data Feed Configuration

<a name="module-feed-settings"></a>
#### Feed Settings

These are options that modify the data that is included in the feed.

- __Display Prices:__ Decide whether to display or not the product price in the data feed.
- __Split Configurable Products:__ Export each component of a configurable product separately, instead of exporting them as a single product.

![Feed Settings Options](http://support.doofinder.com/customer/portal/attachments/529270)

<a name="module-feed-attributes"></a>
#### Feed Attributes

By default Doofinder comes with a minimal set of attributes to be exported in the data feed.

You can add additional attributes by clicking the _Add_ button and choosing a meaningful label, an attribute name to be exported in the XML file, and the actual attribute from your Magento database.

![Feed Attributes Options](http://support.doofinder.com/customer/portal/attachments/529271)

<a name="module-feed-schedule"></a>
#### Schedule Settings

In previous versions of the module the only way to generate the Doofinder data feed was through a URL that generated it on the fly. That is a very resources and time consuming process.

This version of the module can use Cron jobs to generate the data feed in the background by adding your products to a data file located at `media/doofinder` starting at the root of your site. This way Doofinder can read an already generated file, so your server will not be in trouble.

You will have one data feed for each of the store views defined in your Magento. You can enable or disable the feed generation globally or per store view.

The URL of your data feeds will look like this by default:

    http://www.yoursite.com/media/doofinder/doofinder-{store}.xml

To save the system resources, the feed generation process is divided into stages (iterations).

The process is activated as per the Start Time setting. Each time, in each step, it processes the number of products defined in the Step Size setting, and if there are any unprocessed products, another iteration is registered with the delay defined in the Step Delay setting.

When the whole process is completed, a new feed is saved on the drive, the process goes into standby mode and the start time of a new process is registered as per the Frequency setting.

The settings are:

- __Enabled:__ Activate or deactivate the data feed generation.
- __Start Time:__ The time when the generation process should start.
- __Frequency:__ The frequency with which the data feed is generated. A value of Monthly means that the feed will be updated on the first day of each month starting at the time specified by the Start Time setting.
- __Step Size:__ The number of products added to the feed on each iteration. More on this below.
- __Step Delay:__ The interval between subsequent processes in minutes. Do you remember that we had said that the Cron value was important? The Step Delay can’t be lower than that value. In Magento CE `5` is the minimum recommended value.

![Feed Schedule Settings](http://support.doofinder.com/customer/portal/attachments/529272)

<a name="module-feed-schedule-step"></a>
##### Setting the Step Size

There’s no recommended value for the Step Size setting as it depends on your system resources. Use try and error to find the maximum value that works for you. You can start with the default value of 250 items at each stage and see how it performs.

Take into account that if you have a lot of products and use a very low value for Step Size and a value of Daily for the Frequency your feed could take more than one day to generate so take your time and configure the module properly.

<a name="module-feed-schedule-ondemand"></a>
##### On-demand generation

When you are inside a particular store view scope you will be able to generate the data feed for it on-demand by using the Generate Feed button that you will find at the end of the Schedule Settings panel.

The data feed generation will be scheduled to start just after you press the button so it will start almost immediately.

<a name="module-feed-status"></a>
#### Feed Status

Status information is available only in the local scope of each store view. In the global scope you will see the URLs of the already genrated feeds.

To see the status of a feed you will have to use the top-left menu to change to the scope of concrete store view.

The Feed Status panel provides useful information related to the feed generation process:

- __Status:__
    - __Disabled:__ The feed generation is disabled.
    - __Waiting:__ The feed generation is enabled and the next process is waiting to be scheduled.
    - __Pending:__ The next feed generation process has been scheduled and is waiting to be executed.
    - __Error:__ There was an error.
- __Message:__ Displays relevant information regarding the current status.
- __Complete:__ Percentage of the feed that has been generated in the current process.
- __Next Run:__ The date when the next feed generation process will start.
- __Next Iteration:__ When the feed generation process is in progress, this is the date when the next iteration will take place.
- __Last Generated Feed:__ Link to the last generated feed (if any).

<a name="module-search"></a>
### Search Configuration

Under this section you can enable and configure the Doofinder Layer script.

<a name="module-search-layer"></a>
#### Doofinder Layer

- __Enable:__ Enable the layer. You can enable the layer globally or per store view. If you enable the Doofinder Layer then the Magento's autocomplete will be disabled.
- __Script:__ The script code to display the layer. This option can't be set in the global scope and is available only inside the store view scope. You need a different script for each store view.

<a name="help"></a>
## Troubleshooting

__Doofinder can't detect the module__

- If Doofinder says: _Your server returned a 401 HTTP response (authorization required)_ it means that your server is asking for a user and a password via HTTP authentication. Disable authentication for the `*/doofinder/*` path (e.g.: http://www.example.com/doofinder/*).
- If Doofinder says: _Your server returned a 403 HTTP response (access forbidden)_ it means that your server is preventing Doofinder from accessing the `*/doofinder/*` path (e.g.: http://www.example.com/doofinder/*). You can grant access to these IPs (54.171.4.216, 54.174.3.111) or grant access to anyone.
- In any other case:
  - __Check that you've installed the module.__ Go to `System > Magento Connect > Magento Connect Manager` and check if the module is listed in the installed modules list.
  - __Refresh the configuration cache__ of your Magento, and …
  - … Log out from the admin of your Magento store and log in again.
  - If you're using Magento's _compiled mode_ then you will have to recompile because Magento may not be finding the module files in the compilation paths. Go to `System > Tools > Compiler`. Ensure that you understand what's [compilation mode](http://merch.docs.magento.com/ce/user_guide/Magento_Community_Edition_User_Guide.html#system-operations/system-tools-compilation.html#kanchor1022) first! Use this at your own risk!

__The configuration section in my Magento admin is returning a Page Not Found error__

Refresh Magento's cache, log out from the admin panel then log in and try again.

__Cron is not working: `expr: syntax error`__

Update your `cron.sh` file to something more compatible. Change this line:

    if [ "$INSTALLDIR" != "" -a "`expr index $CRONSCRIPT /`" != "1" ];then

by this one:

    if [ "$INSTALLDIR" != "" -a "`echo $CRONSCRIPT | sed -n 's/[/].*//p' | wc -c`" != "1" ]; then

__Other errors__

If you need more help feel free to contact us at support@doofinder.com.

<a name="uninstall"></a>
## Uninstall

Access the Magento Connect Manager through _System > Magento Connect > Magento Connect Manager_. You will have to authenticate again.

Find `Doofinder_Feed` in the modules list and choose _Uninstall_ from the dropdown on the right.

Click _Commit Changes_.

Check the information console to see whether the module has been uninstalled successfully. If the console does not display any errors and the module disappears from the modules list after clicking the Refresh button then everything went right.

