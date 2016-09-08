# WP README Tested Version Updated

WordPress plugin readme's contain a "Tested up to:" field in them which indicates which version of WordPress the plugin is compatible with.  However, quite often one or more releases of WordPress are done without the plugin being updated, causing this to value to fall behind the current WordPress release. 

This script is designed to update the readme.txt file of multiple plugins in the wordpress.org plugin directory to be in sync with the current WordPress release version.

## Installation

The script is intended to be run from outside of your plugin repo and so requires very few changes to your current repo to work.

In it's simplest form you can do the following steps:

1. Clone the update script to /source/readme-tested-version-updater.
2. Start a shell and go to /source/readme-tested-version-updater.
3. Run `php update.php plugin-slug`.

## Usage

The script is intended to be run from it's own repo directory, you do not have to add it to your plugin's SVN tree.

The script accepts a list of plugin slugs to update:

To do an updatee, do the following:

1. Run "php update.php plugin-slug"

## Configuration

For the script to work, you must have three things accessible on your system's shell:

1. PHP
2. SVN

Ideally, these should be available in your path, however only PHP has that requirement, you can configured a path for both GIT and SVN.

The script uses a "update.ini" file to store several configuration variables to use, which will be explained shortly.

The update.ini files has the following format:

```
[General]
plugin-slugs=
temp-dir=

[SVN]
svn-url=https://plugins.svn.wordpress.org/{{plugin-slug}}
svn-username=
svn-path=
```

### General Settings
This section contains the following directives:

* plugin-slugs: A comman seperated list of plugin slugs to update.
* temp-dir: The temporary directory to use, by default the system temp directory.

### SVN Settings
This section contains the following directives:

* svn-url: The full URI of your plugin's SVN repo.
* svn-username: The user name to use when committing changes to the SVN tree.
* svn-path: Local path to the SVN utilities.

