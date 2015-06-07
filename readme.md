***DigitalOcean DynDns Server***

This is a dyndns (ddns) server that will update DNS records hosted by DigitalOcean.  It "should" be compatible with the dyn.com protocol which makes it work with many newer routers.  I am new to PHP but Richard, my very good friend, has been helping me improve it along the way.  It has complete logging built into the server but please use with caution!

If you don't have an account with DigitalOcean, please consider using my [referral link](https://www.digitalocean.com/?refcode=f38e9b37dcef) to get a discount.

**Requirements**

This is written in PHP and depends on the following modules which can be installed using composer.    

* [kriswallsmith/buzz](https://github.com/kriswallsmith/Buzz)    
* [toin0u/DigitalOceanV2](https://github.com/toin0u/DigitalOceanV2)    
* [guzzlehttp/guzzle](https://github.com/guzzle/guzzle)    
* [monolog/monolog](https://github.com/Seldaek/monolog)    

**Installation**

> Create the desired log folder and give your web server write access.    
> NOTE: replace www-data with the account used to run your web server.

```bash
$ mkdir /var/log/dyndns 
$ chown -R www-data:www-data /var/log/dyndns 
$ chmod -R 770 /var/log/dyndns 
```
> Install DigitalOcean_dyndns.    
> NOTE: /nic is the default folder for dyn.com clients, changing it may cause issues.

```bash
$ cd /path/to/webserver
$ git clone https://github.com/digitalm3/DigitalOcean_dyndns nic
$ cd nic && curl -sS https://getcomposer.org/installer | php
$ composer.phar install
```
**Configuration**

Generate your Personal Access Token from [DigitalOcean](https://cloud.digitalocean.com/settings/applications) then edit file digitalocean.config.php file and set the following preferences.

* $oceanAuthKey - set to the token generated above.    
* $logFile - set to the desired location and name of your log file.    
* $disableDigitalOceanUpdate - set to false when you're finished testing.    

If you're looking for an easy way to view your log I recommend installing [monolog-viewer](https://github.com/Syonix/monolog-viewer) from Syonix.  It provides a nice interface to view our logs and makes it very convienient for keeping an eye on our updates.

**Credits**    

* Richard Dern    
* DigitalOcean    
* All of the contributers to PHP and the modules used   
* Everyone else i've forgetten
