--------------------------------------------------------------------
dRealty - The Drupal Real Estate Framework
--------------------------------------------------------------------

dRealty is a real estate framework to import RETS resources (Property,
Agent, Office, OpenHouse, Media) into Drupal as entities.

It's primary function is facilitating the bridge between getting the data
into the site and mapping fields between resources and Drupal.

From here, a user can setup Views for searching via the typical Views method or
using additional modules like ApacheSolr, SearchAPI and Faceted searching.

--------------------------------------------------------------------
System Requirements
--------------------------------------------------------------------

PHP requirements:
* php 5.3.5 or higher - http://www.php.net
* php cURL support - http://www.php.net/manual/en/curl.setup.php

Most hosting vendors come with this by default.

--------------------------------------------------------------------
Installation
--------------------------------------------------------------------

  - Download the phRETS library to path/to/libraries/phrets (https://github.com/troydavisson/PHRETS)
  - Download and enable dRealty module (for basic functionality and Drush support)
  - Enable dRealty Field Map module (for better Field mapping UI and mapping of
      non-standard fields - entity reference, geofield, taxonomies)
  - Enable dRealty Import module to have RETS import and updates happen
      incrementally (you configure time interval for update) on CRON runs. In
      this case, for Cron tasks scheduling control, use ELYSIA CRON to avoid
      overloading the site.
  - Enable drealty Reconciler module - to have Drealty entities checked against
      RETS system on CRON runs, to see if they still exist there and get
      deactivated/deleted if not.  Configure settings here: /admin/drealty/drealty-import/config
  - Enable optional submodules (dRealty Agent, dRealty Office, dRealty OpenHouse)

--------------------------------------------------------------------
General Configuration
--------------------------------------------------------------------

All dRealty needs to work is the connection credentials to your RETS provider.
Under Drealty > Drealty Connections, you can add one or more connections to
your providers.

Please note that all vendors operate on slightly varied implementations of RETS.
If all goes well, you are connected to your remote RETS provider and can continue
setting up dRealty.

Most of the action happens here. When you enable the submodules listed above, their
configuration links will appear under each active connection.

Each connection can have resources (Property, Agent, Office, OpenHouse) configured here.
For example, clicking 'Configure Property' will ask you to map the Resource, and then
configuring each of the property types. This interface lets you map all the fields
necessary from RETS data to Drupal fields.

1. The RETS ID, RETS KEY, and STATUS field are important and specific on a vendor to vendor
basis and will affect the success of your data querying. For most users, Default should appear
as an option (once configured).

2. MODIFIED FIELD - is also important now as CRON does rely on this field when
detecting TimeDifference setting for incremental updates (which is
configured here: /admin/drealty/drealty-import/config)

This screen also allows you to map the incoming data against defined bundle types to allow
flexibility for displaying them with different field configurations or view modes.

Each submodule type (Agent, Office, OpenHouse) allows for varied bundle configuration(s).

Added Listings minimal reporting functionality based on users reports about missing listings. It will be specific to each connection you create,
and it will only check for Listing properties, not Agents, not Offices or anything else.
Reporting can be found at "/admin/drealty/connections" path, next to each connection there will be a "View Reports" link. The form will allow you to
deactivate the listings which don't have a match in RETS and import the missing ones into your site.

--------------------------------------------------------------------
Configuration - Elysia Cron method
--------------------------------------------------------------------

On hosts like Pantheon, who do not currently allow custom crontab settings,
importing via Drush is not really an option.

It is advised to use dRealty Import module with Elsyia Cron to facilitate getting
data into the site.

The import module facilitates an admin interface to manually queue all or some of
the resources as well as flushing the site.

Note that you may have so many listings that 'Processing Queue' manually from the
backend could timeout while the batch is generating items to process. This is where
Elysia Cron can help process items.

By configuring Elysia Cron to operate the system_cron task frequently, the import
module has a defined cron queue worker to take items from the built-in Drupal Queue
and process as many as it can in one cron run. The cron hook in this module will also
poll for changes in the last few days in RETS, and insert them as queue items to be
processed. You can adjust the timing of these settings under Drealty in the admin
menu.

Every enabled dRealty Resource in each connection will be polled for changes in RETS
however often their cron tasks are set to run in Elysia Cron's configuration.

It is strongly advised to use Elysia Cron to break up the cron tasks into smaller chunks
instead of letting Drupal fire dozens of cron hooks every so often.

--------------------------------------------------------------------
MEDIA IMPORT Configuration
--------------------------------------------------------------------

First of all, on example of Properties/Listings resources:
      under Drealty > Drealty Connections > [Your Specific Connection] > Configure Listings > [Enabled Resource] > Configure

      (the path will be something like this:   admin/drealty/connections/manage/[connection_name]/resource/drealty_listing/[class_id]/)
      at the very bottom of configuration form, find a checkbox "Process Media?" and make sure you enable it to be able to get Media Objects from RETS.

Next, go to configure a Field Mapping  (Drealty > Drealty Property Types > [Your Created Property Type] > Manage Fields) for your Media Object you're willing to import.

Let's say you need to get IMAGEs (some RETS systems allow you to obtain the actual image object you can save into Drupal  OR  you can obtain a URL which points to the file inside
RETS system storage):

    1. To obtain IMAGE OBJECT from RETS  (based on GetObject() call):
        - Create IMAGE type field
        - Under "Drealty Field Mapping" fieldset pick "GetObject() call", if your RETS system support GetObject() functionality.
        - Select a field for "Media Object Modification Timestamp Field", e.g. LIST_134 - Picture Timestamp
        - Pick "Media Object Format"  as "Media Object Data"
        - For "Media Object Type"  select the size of the image you would like to retrieve
        - Save configuration.
    2. To obtain IMAGE URL from RETS (if the RETS system is capable of it):
        - Create TEXT type field
        - Under "Drealty Field Mapping" fieldset pick "Media Object" for "Field mapping entity" field, if your RETS system supports returning URLs for images.
        - For "Media Object Type"  select the size of the image you would like to retrieve
        - Save configuration  (make a field multi-valued).
        - You will have URLs imported => then it's up to you how you render them (e.g. create a field .tpl file, etc.)

Let's say you need to get DOCUMENTS (some RETS systems allow you to obtain the actual file object you can save into Drupal  OR  you can obtain a URL which points to the file inside
RETS system storage):

    1. To obtain DOCUMENT OBJECT from RETS  (based on GetObject() call):
        - Create FILE type field
        - Under "Drealty Field Mapping" fieldset pick "GetObject() call" for "Download media object data via", if your RETS system support GetObject() functionality.
        - Select a field for "Media Object Modification Timestamp Field", e.g. LIST_161 - Document Timestamp
        - Pick "Media Object Format"  either as "Media Object Data"  or "URL of Media Object"  (whichever is supported by your MLS)
        - For "Media Object Type"  select available object type you would like to retrieve, e.g. "PDF Listing Document"
        - Save configuration (make a field multi-valued if you need).
    2. To obtain only DOCUMENT URL from RETS:
        - Create TEXT type field
        - Under "Drealty Field Mapping" fieldset pick "Media Object" for "Field mapping entity" field, if your RETS system supports returning URLs for Documents.
        - For "Media Object Type"  pick the object type you would like to retrieve, e.g. "PDF Listing Document"
        - Save configuration  (make a field multi-valued).
        - You will have URLs imported

Let's say you need to get VIDEO objects (I've seen some entries returned from RETS systems as URL to the Video file, and others have it as Embed code snippet,
so to make it safe, I suggest getting data into LONG TEXT type field):

    1. To obtain VIDEO OBJECT from RETS  (based on GetObject() call):
        - Under "Drealty Field Mapping" fieldset pick "Media Object" for "Field mapping entity" field.
        - For "Media Object Type"  pick the object type you would like to retrieve, e.g. "HTML for Video Embedding"
        - Save configuration.
        - You will have the content imported into the field, and then you can see how the data was entered on RETS side and decide how you can render it.



--------------------------------------------------------------------
Configuration - DRUSH method
--------------------------------------------------------------------

On hosts like Acquia, where you can set your own custom crontab settings, Drush
may be a preferred method of import for you.

After enabling and setting up your dRealty connection(s), getting the site
populated with listings is a matter of creating crontab jobs with some basic
Drush commands.

On hosts like Pantheon, who do not support custom crontab configurations, this
method is not possible, and you should use the Elysia Cron method above.

--------    Workflow:    -------------
DRUSH IMPORT gets data based on the conditions you have configured for each
particular Resource Class (for example, under Drealty > Drealty Connections >
> [Your Specific Connection] > Configure Listings > [Enabled Resource] > Configure)


DIFFERENCE between DRUSH and CRON invocation (for both imports: Property Data
and Media): DRUSH rets-import  (without any options) is not running import with
INCREMENTAL update - it imports all of the data available. CRON relies on
TimeDifference setting to do incremental import, which is
configured here: /admin/drealty/drealty-import/config. For DRUSH to respect the
same TimeDifference setting, add Drush option --increment=TRUE, e.g. "drush ri --increment=TRUE",
"drush rim --increment=TRUE"

1. Importing data:
drush -u admin -d rets-import

2. Importing Media:
drush -u admin -d rets-import-media      // this will create a Drupal queue for the properties that have been configured to import media
drush -u admin -d rets-process-media     // this will actually go through the media queue and grab Media Objects from RETS system

3. Flushing Data
drush -u admin -d rets-flush

!!!!!  NOTE !!!!!  :  handle_expired() function has been removed for the moment
        from Drush workflow - it appeared to delete/set inactive listings when
        they were not supposed to.

        ENABLE "drealty_reconciler" module to have listings checked against
        your RETS system to be active/inactive

--------------------------------------------------------------------
Screencast
--------------------------------------------------------------------

Coming in 2017