# Multi Sites

Multi Sites is a module for [ProcessWire](http://processwire.com/) that allows Superusers to easily create/install ProcessWire sites *on the same server* space the module is running in. Only Superusers can use the module. You can create both **stand-alone** and **multi-sites**. 

**Stand-alone or single-sites** are sites that will run in their own document root/directory with *their own* **wire and site folders, .htaccess, index.php**, etc. In other words, a *normal* ProcessWire site.

**Multi-sites** are sites that will run off *one* **wire** folder (shared amongst two or more sites) each having their own **site folder and database**. In this regard, it is important to note that Multi Sites is not in itself a multiple sites solution! Rather, it is a utility that helps you create multi-sites to be run using the ProcessWire core multiple sites feature. For more on this core feature, see the official ProcessWire [documentation](https://processwire.com/api/modules/multi-site-support/), specifically the solution referred to as **Option #1**.

## Features

* Install unlimited number of sites in one (multi-sites) or independent (single-site) ProcessWire installs.
* Install by completing a Form, Typing or pasting in configurations or using pre-created install configurations.
* Choose an Admin Theme to auto-install along with the site installation.
* For single-sites installation, download, save and reuse ProcessWire versions of your choice.
* Install and maintain site profiles for reuse to create other sites.
* Create install configurations to speed up installation tasks.
* Client and server-side validation of site creation values.
* Edit uploaded profiles (e.g., replace profile file).
* Lock installed sites, configurations and profiles to prevent editing.
* Bulk delete items such as site profiles, installed site directories and/or databases (confirmation required for latter two).
* View important site details (admin login, chmod, etc).
* Links to installed sites home and admin pages.
* Timezones auto-complete/-suggest.

## Pre-requisites

### Module Components
On install, the module installs various fields, templates and pages only if it does not find identically named components. In such cases, installation is aborted. Rectify the displayed error(s) then try to install the module again.

### Domains

The domains/sub-domains for the sites you wish to install must exist before installing the site. This is needed in order to update/save the Superuser account for the new site. For instance, if creating a site with the subdomain **blog.mydomain.com** (with directory *site-blog* for a multi-site setup, for instance), that subdomain must be active. For local development, if using Apache, for instance, a *virtual host directive* for **blog.mydomain.com** must have been set up prior to the installation. For remote/live sites, the subdomain must have been set up at your Domain Name Registrar.

### Site Profiles

You will need to prepare site profiles for use with the module. Example site profiles ship with ProcessWire. These include *site-default*, *site-classic*, *site-blank*, etc. The module **only accepts zip files**. Each of these folders needs to be compressed into a zip file, for instance *site-default.zip*, *site-languages.zip*, etc. Please note that the other content you get with ProcessWire downloads are not required. These include the wire folder and .gitignore, index.php, etc files. Do not include any of these in the *site-whatever.zip*.
You are not limited to using the site profiles that ship with ProcessWire. You can create and use your own custom profiles as long as they are structured according to the format ProcessWire expects (See *site-default* that ships with ProcessWire as an example).

## How to Install

1. Install the module from within the ProcessWire admin or download the module and copy the file contents to /site/modules/ProcessMultiSites/
2. In Admin, click Modules > check for new modules
3. Click install for ProcessMultiSites.
4. In the Admin menu, click Multi Sites to start creating sites.

## Module Pages

Multi Sites consists of a number of pages or dashboards. Each dashboard is for a specific task.

1. **Sites**: This dashboard lists all installed sites displaying information pertinent to each site. These include site type (single- or multi-site), admin login URL, site's default timezone, profile used, summary, etc. You can lock/unlock sites for editing and delete their directories on the file system and/or their databases.
2. **Create**: This page is accessed from within the **Sites** dashboard. For new installs, click on the *create* link in the intro text on the **Sites** page. Once at least one site has been created, you will see a link to create new sites situated on the top right of the **Sites** page. The **Create** page, as the name suggests, is where you create/install your ProcessWire sites. More information follows [below](https://github.com/kongondo/MultiSites#installingcreating-sites). Note that whenever you load this page, pre-install checks are carried out behind the scences. These include checking if required PHP functions exist, PHP version, etc. Although the module is running inside ProcessWire, meaning these checks *must have passed*, the checks are carried out anyway, just in case.
3. **Profiles**: Lists uploaded site profiles. A *Site Profile* is a ProcessWire site profile as defined [here](https://modules.processwire.com/categories/site-profile/). For use in this module, the *wire* folder is not required and **should not be added to your site profile**. Only the *site* folder is needed. The *site profile* must be structed as required by ProcessWire. Here are some [example](https://modules.processwire.com/categories/site-profile/) profiles and a [tutorial](https://processwire.com/docs/tutorials/default-site-profile/).
4. **Upload**: Via the **Profiles** page, on the top right corner, click the link to upload profiles. If you've only just installed the module, click on the *upload* link in the intro text to launch this page. Complete the form to upload a new profile. The profile file must be a zip file (of your compressed *site* folder).
5. **Configs**: Multi Sites allows you to install sites using pre-defined install configurations. This dashboard lists all the site installation configurations you have created with information about each. To create a configuration, access the **Config** page (see below). On this dashboard you can edit an install configuration, or bulk action several configurations (e.g. lock, delete, configurations, etc.).
6. **Config**: Access this dashboard using the link within the **Configs** page. Use the form on the page to create an install configuration. The form is pretty straightforward. An install configuration contains site creation values/data that can stay consistent across several installs. These include file and directory permissions, Superuser name, admin theme, timezone, database port and host, etc. You can create (and edit) as many install configurations as you want.
7. **Wire**: This dashboard applies to single-sites. Use it to to download different versions of ProcessWire for selection when installing a single site. You can also refresh your download to grab the latest available version of a particular ProcessWire version. You are able to download **ProcessWire 2.7** (master and dev); **ProcessWire 2.8** (legacy master) and **ProcessWire 3.x** (master and dev). You need to note that ProcessWire 2.7 is an older version of ProcesWire that is no longer in development. However, many sites still use this version, hence its availability to download. Once downloaded, the download is processed to remove the folder **site** and other files not needed to install ProcessWire such as **.git** files. The **site** folder is removed since you already have site profiles for your installs. The processed directory is then compressed into a zip file and stored in a page, ready for selecting for single site installations. This avoids the need to keep on downloading ProcessWire everytime you need to install a single site.
8. **Cleanup**: This dashboard is used when you want to uninstall the module. Please see the **Uninstall section** [below](https://github.com/kongondo/MultiSites#uninstall).

## How to Use

Creating/Installing sites using the module is easy. 
1. Load the module dashboard.
2. If it is your first time using the module, you will need to first upload at least one **Site Profile**.
3. If installing a single-site, you will first need to download at least one version of ProcessWire. Use the **Wire** dashboard for this.
4. Click on the navigation item *Profiles* and follow the instructions.
5. If you wish, you can also create an install configuration using the **Configs** dashboard.
6. Head over to **Sites** page. Click on the link (top right) to create a new site.
7. Complete the site creation form, save and (if there were no errors) you are done!
8. Go back to **Sites** to see a record of your newly created site. Click on the Admin or domain name to visit the site (opens in a new window).


### Installing/Creating Sites

Sites are installed using either of the 3 methods outlined below. Depending on the type of site (single or multi) and the method you select to create that site, different form fields will be displayed for completion. Unless stated otherwise, all fields must be completed. Client-side validation will help you with this. Irrespective of the selected method, you have to specify whether you are installing a single- or a multi-site.

#### Form
Using the form method, you complete a full form, inputting values required to install ProcessWire. These include Superuser name, email, host domain and name, database configurations, etc. Complete all required fields and save. 

#### Type or Paste
This method allows you to type or copy and paste in most of the required form values as **key=value** properties. Some values need to be input in the form directly, for instance, installation profile and ProcessWire version (for single sites). Below is the list of property names that require values. Note that a key and its property are separated using an equals sign (**=**), and key/value pairs are separated using a comma (**,**).

**Required properties in Type or Paste**

**Note that all key names are case-sensitive**.

1.	site
2.	hostDomain
3.	admin
4.	colour
5.	theme
6.	timezone
7.	dbName
8.	dbUser
9.	dbPass
10.	dbHost
11.	dbPort
12.	user
13.	pass
14.	passConfirm
15.	email
16.	chmodDir
17.	chmodFile

**Key explanations**


1.	**site**: *For multi-sites only*. The name to give to your site directory. Enter as *name* only. The *name* will be auto-appended to the *site* directory for the multi-site on install. For instance, *site-sports* if the supplied name here is *sports*.
2.	**hostDomain**: For instance, *mydomain.com*
3.	**admin**: The name to give to admin login URL for the site being created. For instance, **crabs**, which will result in *mydomain.com/crabs/* for logging into the ProcessWire admin for the site you are installing.
4.	**colour**: The colour of the admin theme. For instance, for *AdminThemeDefault*, *warm* or *classic*.
5.	**theme**: The name of the admin theme to install, entered as its class name, e.g. *AdminThemeDefault*.
6.	**timezone**: Timezone to use for the site, in the format *Asia/Aqtau*.
7.	**dbName**: Name of the database ProcessWire will use for the site being installed.
8.	**dbUser**: The database user name.
9.	**dbPass**: The database password.
10.	**dbHost**: The database host (pre-filled with *localhost*).
11.	**dbPort**: Database port to connect to (pre-filled with *3306*).
12.	**user**: The ProcessWrie Superuser name for the site being installed.
13.	**pass**: The Superuser's password.
14.	**passConfirm**: Confirmation of Superuser's password.
15.	**email**: Superuser email.
16.	**chmodDir**: Default directory permissions for the site being installed, e.g. *755*.
17.	**chmodFile**: Default file permissions for the site being installed, e.g. *644*.


**Example key=value pairs**

>site=animals,hostDomain=mydomain.com,admin=crabs,colour=blue,theme=AdminThemeReno,timezone=Asia/Bahrain,dbName=some_database_name_db,dbUser=root,dbPass=veryXStrong55DBPassWord,dbHost=localhost,dbPort=3306,user=morpheus,pass=secretStrongPassword123456M,passConfirm=secretStrongPassword123456M,email=morpheus@trixma,chmodDir=755,chmodFile=644

#### Install Configuration
Use this method to create a site using a pre-defined install configuration that you created using the **Config** dashboard. Select the radio labelled *Saved Values* to use this method. **Note that passwords and dynamic values bound to change across sites are not saved in an install configuration**. For instance, host and domain information, admin login, database name, etc.

## Uninstall

1. First, *Cleanup* the modules components (fields, templates, files and pages) using the Cleanup dashboard.
2. Uninstall the module in the Module's page in your ProcessWire Admin.

## Resources

* [Support Forum](https://processwire.com/talk/topic/17372-multi-sites-processmultisites/)
* Video [Alpha Release demo](https://youtu.be/Uw4wG4qRn6k) (a bit outdated now).

## License
MPL2

## Changelog

#### Version 0.0.2
1. Support for installing single/stand-alone sites.
2. Added *Type or Paste* and *Install Configuration* methods for creating sites.
3. Create and edit site install configurations.
4. Download, store and restore various versions of ProcessWire (for single-site installations).
5. UI enhancements.

#### Version 0.0.1
1. Initial Commit.