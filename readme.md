***DigitalOcean DynDns Server***

This is a dyndns (ddns) server that will update DNS records hosted by DigitalOcean.  It "should" be compatible with the dyn.com protocol which makes it work with many newer routers.  I am new to PHP but my very good friend, Richard, has contributed a lot of the code.  A log is created in the same folder named digitalocean.dyndns.log, please use caution when using it.

**Requirements**

This is written in PHP and depends on the following modules which can be installed using composer.    

* [kriswallsmith/buzz](https://github.com/kriswallsmith/Buzz)    
* [toin0u/DigitalOceanV2](https://github.com/toin0u/DigitalOceanV2)    
* [guzzlehttp/guzzle](https://github.com/guzzle/guzzle)    
* [monolog/monolog](https://github.com/Seldaek/monolog)    

 **Installation**
 
```bash
$ cd /path/to/webserver
$ git clone https://github.com/digitalm3/DigitalOcean_dyndns nic
$ cd nic && curl -sS https://getcomposer.org/installer | php
$ composer.phar install
```
**Configuration**

Generate your Personal Access Token from [DigitalOcean](https://cloud.digitalocean.com/settings/applications)
    
Insert the token into digitalocean.config.php and set disable_update to false when you're finished testing.  If you're looking for an easy way to view your log I recommend installing [monolog-viewer](https://github.com/Syonix/monolog-viewer) from Syonix.  It's a nice app to view our logs with loads of options and makes it very convienient for keeping an eye on our updates.

**Credits**    

* Richard Dern    
* DigitalOcean    
* All of the contributers to PHP and the modules used   
* Everyone else i've forgetten
