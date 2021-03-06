Piwik comes with unit tests, integration tests, Javascript tests and Webtests.
This document briefly describes how to use and modify Piwik tests. 

## Continuous Integration

We use Travis CI for our continuous integration server. It automatically runs our battery of thousands of unit/integration/screenshot tests
after each commit to our GIT repo. More information at the links:

 * Piwik on Travis CI: https://travis-ci.org/piwik/piwik
 * QA in Piwik: http://piwik.org/qa/

Each core Piwik developer is responsible to keep the build green. If a developer breaks the build, he will receive an email from Travis CI.

The next section explains how you can run the test suite on your own dev machine.

## How To Run Piwik Tests

To run tests, you must use the Git master. Tests files are not in the Piwik zip archive.

You can get the latest Git revision at: http://github.com/piwik/piwik

```
$ git clone https://github.com/piwik/piwik.git
```

Next install Composer which will lets you download the libraries used in Piwik:
```
$ curl -sS https://getcomposer.org/installer | php
$php composer.phar install
```

To execute the tests:

 * In your php.ini make sure you have the setting to show all errors:
 `error_reporting = E_ALL | E_STRICT`

 * Go to tests/index.php to see the tests homepage
   and run the Integration tests via a visual UI, or run JS Tests

 * Next you will need to install PHPUnit

## PHPUnit Tests

1. 	Install PHPUnit on your system
	
		$ cd your/php/directory
		$ sudo pear upgrade PEAR
		$ pear config-set auto_discover 1
		$ sudo pear install --alldeps pear.phpunit.de/PHPUnit

	Doc at: http://www.phpunit.de/manual/current/en/installation.html

2. 	Configure PHPUnit: Copy the file `piwik/tests/PHPUnit/phpunit.xml.dist` to `phpunit.xml`.
	In this file, you will find the following lines.
	Please edit HTTP_HOST and REQUEST_URI to match the hostname and path of the Piwik files.
    For example if your Piwik is available at http://localhost/path/to/piwik/ you would write:

		<server name="HTTP_HOST" value="localhost"/>
		<server name="REQUEST_URI" value="/path/to/piwik/"/>

3.	Ensure the `[database_tests]` section in `piwik/config/config.php.ini` is set up correctly, 
	i.e. with the correct password to prevent the following error:
	`SQLSTATE[28000] [1045] Access denied for user 'root'@'localhost' (using password: NO)`


4. 	Run the tests (see the next section to run tests in the browser)

		$ cd /path/to/piwik/tests/PHPUnit
		$ phpunit --group Core
     	$ phpunit --group Plugins
     	$ phpunit --group Integration

	There are three main groups of tests: Core, Plugins and Integration
	For example run `phpunit --group Core`
	to run all Core Piwik tests. You may also combine groups like
	`phpunit --group Core,Plugins`

5.	Write more tests :)
	See ["Writing Unit tests with PHPUnit"](http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html)

### PHP 5.5: also update PHPUnit to latest

See [PHPUnit update](http://phpunit.de/manual/current/en/installation.html) or try this command:

    $ sudo pear install -a phpunit/PHPUnit

### Troubleshooting failing tests

If you get any of these errors:
 * `RuntimeException: Unable to create the cache directory ( piwik/tmp/templates_c/66/77).`
 * or `fopen( piwik/tmp/latest/testgz.txt): failed to open stream: No such file or directory`
 * or `Exception: Error while creating the file: piwik/tmp/latest/LATEST`
 * or `PHP Warning:  file_put_contents( piwik/tmp/logs/piwik.test.log): failed to open stream: Permission denied in [..]`

On your dev server, give your user permissions to write to the directory:

    $ sudo chmod 777 -R piwik/tmp/

### Troubleshooting SLOW tests

If the tests are running incredibly slow on your machine, maybe you are running mysql DB on an ext4 partition?
Here is the tip that will save you hours of research: if you use Mysql on ext4 partition,
make sure you add "nobarrier" option to /etc/fstab to disable some super slow IO feature.

Change from:
    `UUID=83237e54-445f-8b83-180f06459d46       /       ext4    errors=remount-ro     0       1`
to this:
    `UUID=83237e54-445f-8b83-180f06459d46       /       ext4    errors=remount-ro,nobarrier     0       1`

### Using latest GIT version
On ubuntu to use the latest GIT:

```
sudo add-apt-repository ppa:git-core/ppa
sudo apt-get update
sudo apt-get upgrade
```

## Integration Tests

Integration tests files are in `tests/PHPUnit/Integration/*Test.php`

Integration tests allow to test how major Piwik components interact together.
A test will typically generate hits to the Tracker (record visits and page views)
and then test all API responses and for each API output. It then checks that they match expected XML (or CSV, json, etc.).
If a test fails, you can compare the processed/ and expected/ directories in a graphical
text compare tool, such as WinMerge on Win, or MELD on Linux, to easily view changes between files.

For example using Meld, click on "Start new comparison", "Directory comparison",
in "Original" select "path/to/piwik/tests/PHPUnit/Integration/expected"
in "Mine" select "path/to/piwik/tests/PHPUnit/Integration/processed"

If changes are expected due to the code changes you make, simply copy the file from processed/ to
expected/, and test will then pass. Copying files is done easily using Meld (ALT+LEFT).
Otherwise, if you didn't expect to modify the API outputs, it might be that your changes are breaking some features unexpectedly.


## JavaScript Tests

piwik.js is unit tested and you can run tests via piwik/tests/javascript/

## Testing Data

You can import data over several days in Piwik:

1. 	Install Piwik
2. 	Create a site with URL http://piwik.org/
3. 	Create a Goal eg. URL Contains "blog"
4. 	Import data from an anonimized test log file in piwik/tests/resources/ directory. Run the following command:

		$ python /home/piwik/misc/log-analytics/import_logs.py --url=http://localhost/path/ /path/to/piwik/tests/resources/access.log-dev-anon-9-days-nov-2012.log.bz2 --idsite=1 --enable-http-errors --enable-http-redirects --enable-static --enable-bots

	This will import 9 days worth of data from Nov 20th-Nov 29th 2012.

5.	You can then archive the reports with:

        $ php5 /home/piwik/misc/cron/archive.php --url=http://localhost/path/

You should now have some interesting data to test with in November 2012!

## Scheduled Reports Tests

Piwik scheduled reports (HTML, PDF & SMS) are part of the integration test suite. They follow the same principles described in the INTEGRATION TESTS section of this document.

Piwik scheduled reports can contain PNG graphs when the user specifies it. Depending on the system under test, generated images differ slightly.
Therefore, PNG graphs are only tested if the system under test has the same characteristics as the integration server.
The characteristics of the integration server are described in `IntegrationTestCase::canImagesBeIncludedInScheduledReports()`


(*) to analyse and/or generate the expected files, you can either

 - set up the vagrant piwik vm (which is used by the integration server) or
 - retrieve the files from the [builds artifacts](http://builds-artifacts.piwik.org/)

## UI Screenshots Tests

Piwik contains UI tests that work by taking a screenshot of a URL and comparing it with
an expected screenshot. If the screenshots do not match, there is a bug somewhere. These
tests are in another repository but are included in Piwik as a submodule. To get the tests,
run the following commands:

    $ git submodule init
    $ git submodule update

### How to generate screenshots

In order to run UI tests, you need to have [phantomjs](http://phantomjs.org) version 1.9.0 or greater
installed on your machine. You can download phantomjs [here](http://phantomjs.org/download.html). phantomjs is headless, so even if you're on a server without the X window system, you can still run the
UI tests.

On Ubuntu:

    $ sudo apt-get install ttf-mscorefonts-installer imagemagick imagemagick-doc

To generate screenshots identical to those generated by Travis, we install some extra fonts. 

Imagick is used to generate the "difference" screenshots which show which pixels have changed.

Removing this font may be useful if your generated screenshots' fonts do not match the expected screenshots:

    $ sudo apt-get remove ttf-bitstream-vera

**Running Tests**

You can test the UI by running:

    $ cd PHPUnit
    $ phpunit --group UI

**Learn more**

Check out this blog post to learn more about Screenshot Tests in Piwik:
[QA Screenshot Testing blog post](http://piwik.org/blog/2013/10/our-latest-improvement-to-qa-screenshot-testing/)

## VisualPHPUnit

Piwik comes with a modified copy of VisualPHPUnit (see https://github.com/NSinopoli/VisualPHPUnit)
which you can use to run PHPUnit tests in your browser.

See tests/README.visualphpunit.md

## Profiling with XHProf

See tests/README.xhprof.md

## Benchmarks

See tests/PHPUnit/Benchmarks/README.txt

## Participate

You can help by improving existing tests, or identify some missing tests and implement them.
See http://piwik.org/participate/development-process
Please contact us at hello@piwik.org

