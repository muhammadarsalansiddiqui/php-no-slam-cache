# PHP Cache Slamming problem
Cache slamming is an issue people often doesn't know about, but it's making most of the caching systems pretty ineffective, regardless of caching storage method: files, memcached, database etc.

It's because problem lies in lack of process synchronization not the storage method.

An example of thread racing and cache slamming is shown below:

Let's say we need to cache very resource consuming work, and it overally takes few seconds to complete, which is long time on busy internet sytems.

In a situation where there are few or more HTTP requests per second requiring such resource from cache, here is what happens when resource is not cached, or it's expired:

1. First process/thread fails to read resource from the cache, then begins to create resource, it will take few seconds and a lot of server power: processor/memory/io.

1. In the meantime, when first process is creating the resource, other processes/threads are trying to read cache, fails, and doing the same work what process/thread nr 1 is doing, because there is no such thing like synchronization builded into most of the cached systems available for PHP. 

1. Performance downspike happens, everything is slowed down, and it's magnified by number of concurrent threads and load the Job is creating. That means degradation of user experience on Your site. There are various measurement tests and opinions on The Net regarding page load time, but many indicates thet when page loads longer then 200 ms it starts to annoy The Visitor. Loading time longer than a dozen of seconds is simply unacceptable for casual Visitor on Your Website.

1. It continues to the moment when last of the job is done. When that time is higher than expiration time, then You are in serious troubles. 

**This is called cache slamming and it's wrong!**

> There should be only one process creating the resource at the time, while others should yeld, wait and sleep until first proces will finish the job. After that the sleeping processes should be woken up and read newly created resource from cache.

You may not see the problem until you have low traffic on your website, but when you are starting to achieve success and popularity grows, so website traffic, number of concurrent processes/threads requiring cached resource, and that may lead to problems like cache slamming.


# The Solution to Slamming and basic No Slam Cache usage


No Slam Cache Package offers solution to Cache Slamming Problem, providing process synchronization using PECL Sync package and SyncReaderWriter Class: http://php.net/manual/en/class.syncreaderwriter.php. 

To install PECL Sync package visit: https://pecl.php.net/package/sync, or use package manager on your linux distribution and install php-pecl binary.

It is many readers, one writer at once synchronization model.

Using No Slam Cache requires different than usual approach to creating the resource, NOT in that way:

 `if (!resource exists in the cache) { `

 `1. create resource: several DB calls, searching, etc., few seconds to complete`

 `2. put new resource into the cache `
  
 `3. return new resource`

 `} `

...BUT within SINGLE function call to the Cache Manager Object.


With No Slam Cache it is done by passing closure/callback function that creates resource, as argument for the Cache Manager function which retrieves resource from cache. 

If resource exists in the cache, closure is not executed and cached resource is returned using READ LOCK.

If resource does not exists in the cache then callback function is executed in synchronized block using WRITE BLOCK, and only one callback function for given group and key is executed at once.

Example of usage:

`$cache = new CacheMethod();`

`$cache->get($group, $key, $lifetimeInSeconds, $createCallback);`

Where **$group** is a cache group - think of it like name of SQL table, and **$key** is a cache key, think of it like unique ID of row in the table. 

Pair **$group** and **$key** must be unique, but **$key** value can be repeated in different Groups.

**$lifetimeInSeconds** - self explanatory

**$createCallback** - it's no arguments callback function which will create the resource, when it is expired or does not exist in the Cache.

Whole process of reading/writing to the Cache is synchronized, that is: 

**only one process will write to the cache while others will wait and then get recently created resource, instead of slamming the cache**. 

While resource exists in the Cache and it's not expired, it can be read concurrently by many PHP processes at once.

Real example with callback method and cache method file:

`$group = 'products';`

`$key = 150;`

`$lifetimeInSeconds = 30;`

`$createCallback = function() use($group, $key) { return 'I was created at '.date('Y-m-d H:i:s').' for group '.$group.' and key '.$key; }`

`$dir = __DIR__.'/inopx_cache';`

`if (!file_exists($dir)) {`

`mkdir($dir, 0775);`

`}`

`$cache = new \inopx\cache\CacheMethodFile($dir);`

`echo 'Cached value = '.$cache->get($group, $key, $lifetimeInSeconds, $createCallback);`

# Requirements

You need to install PECL Sync extension in order to use No Slam Cache: https://pecl.php.net/package/sync

# Startup / boostrap

Include **classloader.php** into your bootstrap file/procedure to load package classes.

Classloader is optional, as class names and position in directories are PSR-4 comptible.

# Cache Methods commons

Every Cache Method implements interface **\inopx\cache\InterfaceCacheMethod** and comes with constructor containing **$syncTimeoutSeconds** variable with default value of 30.

Interface **\inopx\cache\InterfaceCacheMethod** consists of **main get method** described earlier, and few others, look at API Documentation (compressed in doc-api.zip) for more details.

**$syncTimeoutSeconds** is a value of lock timeout, that is, if process waits longer than **$syncTimeoutSeconds** seconds, it fails to acquire lock and then, instead of throwing error, creates resource using callback and writes to the cache.

Because of that, it is important to override this value in case when the work creating resource may take longer time to complete.


# Cache Method Memcached

Class **\inopx\cache\CacheMethodMemcached** is a Memcached Storage Method Class with constructor:

`__construct( $memcachedHost = '127.0.0.1', $memcachedPort = 11211, $syncTimeoutSeconds = 30 )`

Where constructor arguments are pretty much self-explanatory.

# Cache Method PDO

Class **\inopx\cache\CacheMethodPDO** is a Database storage Method Class with constructor:

`__construct( PDO $PDOConnection, integer $sqlDialect = null, integer $syncTimeoutSeconds = 30 )`

Where **$PDOConnection** is a established connection to database (PDO Class), and **$sqlDialect** is one of the two dialects supported by this class: **\inopx\cache\CacheMethodPDO::SQL_DIALECT_MYSQL** or **\inopx\cache\CacheMethodPDO::SQL_DIALECT_POSTGRESQL**.

Before you may use this cache method, you must create database table by executing method **createSQLTable**.

Name of the Table and names of the Columns can be configured by altering class variables like: **$SQLTableName**, **$SQLColumnGroupName**, **$SQLColumnKeyName** and so on - look at API Documentation for more.

# Cache Method File

Class **\inopx\cache\CacheMethodFile** is a File Storage Method Class with constructor:

`__construct( string $baseDir = 'inopx_cache', integer $syncTimeoutSeconds = 30 )`

Where **$baseDir** is a base directory for cache files, without trailing separator. The base directory must exist and be writable for PHP.

For every group there will be spearated subdirectory in the base dir named after the group, but sanitised first for proper filesystem directory name.

Every key will be converted to number by **crc32** function if its not numeric, based on that number, the special subdirectory structure will be created if number exceeds 100. 

That is to ensure that no more than 100 files and 10 subdirectories are in every subdirectory of the group directory. 

Look at **\inopx\io\IOTool** Class and Method **getClusteredDir**

On some filesystems large number of files and/or subdirectories in one directory may leed to long disk seek times, and slow down IO. "Directory clustering" is preventing this problem from happening.

There is still potential problem of huge number of groups and therefore, huge number of subdirectories in the base directory.

**Beware of special characters in groups and keys** when using this cache method, as group and key will be respectivery subdirectory name and file name containing cached value. Those values will be sanitised first, which may lead to coflict when there are two similar keys with difference in special characters only.


# Dummy Cache

Class **\inopx\cache\CacheMethodDummy** is for simulating cache when you don't want to use cache, but it's convenient to provide cache method object.

# Key name prefix

Every cache method provides way to set **key prefix** by method **setCacheKeyPrefix** and get by method **getCacheKeyPrefix**.  When you use no-slam-cache in more than one application using the same memcached server, or the same database to store cache items, providing prefix is a way to avoid key name conflicts.

# The Deadlock Problem

When using any kind of process synchronization, a Deadlock problem may occur.

It happens when:

1. process nr 1 acquire lock A
2. then process nr 2 acquire lock B
3. then process nr 1 is trying to acquire lock B while process nr 2 is trying to acquire lock A

This leads to never-ending or lock timeout error situation called Deadlock.

If you need more detailed explanation, search the web, for example: https://en.wikipedia.org/wiki/Deadlock

> The best solution to avoid deadlocks is to never use nested locks, that is: when you acquire first lock, do not acquire any further locks until you unlock the first lock. It is smart usage of locking and guarantees no deadlocks.

Regarding No Slam Cache, it means you should never do any synchronization inside callback function creating the resource, especially using the cache within.

Callback like this is WRONG:

`$createCallback = function() use($myVar)`

`{`

`$value = 'My Value';`

`$cache = new \inopx\cache\CacheMethodFile('inopx_cache');`

`$value2 = $cache->get('mygroup',123,30, function() { return uniqid(''); });`

`return $value . ' ' . $value2;`

`}`

...because if it's used with cache, it will create nested lock, and if other process is locking in reversed order, there is possibility of deadlock.

# Test script

You can find **cli-test-cache.php** script in the main directory of No Slam Cache package.

It meant to be run in CLI mode for general purpose testing of Cache Methods and for testing concurrency.

Open Command Line Window / Linux Terminal and enter:

php cli-test-cache.php

Help information will appear with available commands.

You should open few Command Line Windows, put desired command in every window, and test concurrency.

Test script is initially configured for that, as it sleeps for 10 seconds in the callback function to give you time to execute the same script in the rest of the opened windows and observe results.

