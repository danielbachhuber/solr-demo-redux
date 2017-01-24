solr-demo-redux
===============

These files comprise the [solr-demo-redux](http://dev-solr-demo-redux.pantheonsite.io/) website, a demonstration of Pantheon's [Solr Power](https://github.com/pantheon-systems/solr-power) WordPress plugin.

## Overview

In addition to the Pantheon WordPress upstream (e.g. WordPress core files), this repository contains:

* [wp-content/themes/solr-demo-redux](https://github.com/danielbachhuber/solr-demo-redux/tree/master/wp-content/themes/solr-demo-redux) - WordPress theme with the functional logic for the demonstration website. Take a look at the annotated [functions.php](https://github.com/danielbachhuber/solr-demo-redux/blob/master/wp-content/themes/solr-demo-redux/functions.php) for the core logic.
* [wp-content/plugins/solr-power](https://github.com/danielbachhuber/solr-demo-redux/tree/master/wp-content/plugins/solr-power) - Latest version of the Solr Power plugin.
* [wp-content/plugins/wp-native-php-sessions](https://github.com/danielbachhuber/solr-demo-redux/tree/master/wp-content/plugins/wp-native-php-sessions) - Latest version of the WP Native PHP Sessions plugin. Sessions are used to track state of "Enable Solr" button.

## Installing

Replicate the `solr-demo-redux` site using Terminus. Make sure to replace instances of `solr-demo-daniel` in this example with the site name of your choosing.

### 1. Create a new Pantheon site with the Solr add-on

Use a site name and label of your choosing. `e8fe8550-1ab9-4964-8838-2b9abdccf4bf` is the id for the Pantheon WordPress upstream.

```
# Create a new Pantheon site with <name> <label> <upstream-id>
$ terminus site:create solr-demo-daniel "Solr Demo Daniel" e8fe8550-1ab9-4964-8838-2b9abdccf4bf
 [notice] Creating a new site...
 [notice] Deploying CMS...
 [notice] Deployed CMS

# Change the connection mode from SFTP to Git
$ terminus connection:set solr-demo-daniel.dev git
 [notice] Enabled git push mode for "dev"
 
# Engage the light of the sun
$ terminus solr:enable solr-demo-daniel
 [notice] Solr enabled. Converging bindings.
 [notice] Brought environments to desired configuration state
```

### 2. Clone the repository for the new Pantheon site, set this repository as the upstream, and push

Now that you have a Pantheon site to work with, merge this repository's code into your site.

```
# Fetch the Git clone URL for the site's 'dev' environment
$ terminus connection:info solr-demo-daniel.dev
 [snip]

# Clone the dev repository locally
$ git clone ssh://codeserver.dev.user-id@codeserver.dev.site-id.drush.in:2222/~/repository.git solr-demo-daniel
Cloning into 'solr-demo-daniel'...
remote: Counting objects: 8677, done.
remote: Compressing objects: 100% (4740/4740), done.
remote: Total 8677 (delta 5428), reused 6918 (delta 3828)
Receiving objects: 100% (8677/8677), 23.64 MiB | 1.81 MiB/s, done.
Resolving deltas: 100% (5428/5428), done.

# Change into the cloned repository, and set this repository as its upstream
$ cd solr-demo-daniel
$ git remote add upstream git@github.com:danielbachhuber/solr-demo-redux.git

# Fetch the upstream in preparation for merging it into your site repository
$ git fetch upstream
remote: Counting objects: 2490, done.
remote: Compressing objects: 100% (1530/1530), done.
remote: Total 2490 (delta 882), reused 2484 (delta 878), pack-reused 0
Receiving objects: 100% (2490/2490), 1.90 MiB | 1.59 MiB/s, done.
Resolving deltas: 100% (882/882), completed with 3 local objects.
From github.com:danielbachhuber/solr-demo-redux
 * [new branch]      master     -> upstream/master

# Merge the upstream master branch and push to your environment
$ git merge upstream/master
 [snip]
$ git push origin master
Counting objects: 2491, done.
Delta compression using up to 4 threads.
Compressing objects: 100% (1527/1527), done.
Writing objects: 100% (2491/2491), 1.90 MiB | 724.00 KiB/s, done.
Total 2491 (delta 882), reused 2490 (delta 882)
To ssh://codeserver.dev.02582235-f416-4f99-8ef1-f6695a6e27e8.drush.in:2222/~/repository.git
   8b0ccc6..ead8baf  master -> master
```

### 3. Import the `solr-demo-redux` database

Now that the code is pushed to your 'dev' environment, import the `solr-demo-redux` SQL file to gain access to a database of 200k books.

TBD - Need to upload the database SQL file somewhere public
TBD - Include book import command instructions

### 4. Run Solr index process

Now that you have data to work with in your Pantheon environment, run the Solr indexing process.

TBD - `wp solr index` takes a long time on a large site, so we need to sort that out.
