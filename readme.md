***DigitalOcean DynDns Server***

This is a dyndns (ddns) server that will update a DNS record hosted by DigitalOcean.    
It "should" be compatible with the dyn.com protocol which makes it work with many    
newer routers.  I am new to PHP but my very good friend Richard has contributed most   
of the code, please use caution if you use it.  A log is created in the same folder named    
digitalocean.dyndns.log.  

**Requirements**

This is written in PHP and depends on the following modules which can be installed    
using composer.    
[kriswallsmith/buzz](https://github.com/kriswallsmith/Buzz)    
[toin0u/DigitalOceanV2](https://github.com/toin0u/DigitalOceanV2)    
[guzzlehttp/guzzle](https://github.com/guzzle/guzzle)    
[monolog/monolog](https://github.com/Seldaek/monolog)    

 **Installation**
 
```bash
$ git clone https://github.com/digitalm3/DigitalOcean_dyndns nic	
$ composer install    
```
Dont forget to change the key in update.php to your DigitalOcean Application Token

**Credits**    

Richard Dern    
DigitalOcean    
All of the contributers to PHP and the modules used   
And everyone else i've forgetten
