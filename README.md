
# sp-cli

## Overview
Documentation for early version of a Showpad CLI working with current API. (not updated for summer release yet).

You can automate some simple tasks as uploading files and folders (with automatic tagging). **These are all experimental so use with care! Do nut use CRUD operations on live customers!!**

Main usage is currently downloading analytics data in one command. (see usage)

## Installation
### Git

    git clone this repo

### Installing composer

    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"


This will install composer (tool to manage php dependencies).

Now run “php composer.phar install” in the folder - it will install dependencies for you.

    php composer.phar install


Run “chmod +x sp-cli.php” to make php file executable

    chmod +x sp-cli.php

## Usage
### Oauth

 1. Create OAuth client in Showpad with your credentials.
 2. Create yaml file with configuration (see example.yaml for details)


Once you have this, you can start using the tool.
### Usage

#### Downloading Analytics data
The tool gives the option to download event data in a date range, the data model or download all of the data at once. The customer flag creates a folder where it saves all of the data.

##### Only events:

    ./sp-cli.php -c showpad.yaml --export events --start 2017-04-01 --end 2017-06-30 --customer showpad

##### All:

    ./sp-cli.php -c showpad.yaml --export all --start 2017-04-01 --end 2017-06-30 --customer showpad

###### Only data model:

    ./sp-cli.php -c showpad.yaml --export datamodel --start 2017-04-01 --end 2017-06-30 --customer showpad

### Other commands - no docs yet - todo - look in code

Upload  (upload single file)
Update (update a file)
Createtag (create a single tag)
Createchannel (create a single channel)
Csvtags (import tags from csv)
Exporttags  (export all tags to csv)
Replacetags (replace tags from csv - from previous operation)
Folder (upload full folder to Showpad, tag files with hierarchical folders)
