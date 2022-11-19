Tau
===

> **Note**
> As of November 2022, this library no longer supports PHP 5.x

Tau is a small PHP library with common routines required in many apps. It is not a framework,
and thus does not specify how you code. Yay!

I mentioned small, right?

As of May 2015, it comes in at a whopping 166K. That's K, as in Kilobytes. 
As a comparison, one of the smaller and most popular PHP frameworks comes in
at 103 megabytes when it, and its dependencies (parts of Symphony, Doctrine, etc.)
are installed. Of course, that framework has a large userbase and has several
additional features, but nothing major.

Some things I'd still like to do:

* Improve Validate module
* Improve Debug module
* Implement Config module
* Better docblocks
* Standardize code

As far as I know, Tau is only used on one major website. The website gets millions of hits a month,
so it should be pretty stable.


Installation
============
Download the zip, uncompress it to your includes directory, and include "Tau.php."

Someday I may add it to composer, but I want to make it more full featured before doing that.

Phar
====
I'm not a big fan of Phar files because they take longer to load, but if you want to make one, the following are the easiest steps I've found:

Download and extract empir.zip from http://sourceforge.net/projects/empir/files/latest/empir-1.0.0.tar/download

Run the following command:

```
php -dphar.readonly=0 empir make Tau.phar Tau.php . --exclude="samples/*"
```
