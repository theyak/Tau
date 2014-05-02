Tau
===

Tau is a small PHP library with common routines required in many apps. It is not a framework, and thus does not specify
how you code. Yay!

I mentioned small, right?

As of July 2013, it comes in at a whopping 149K. That's K, as in Kilobytes. As a comparison, one of my favorite, and 
most popular small frameworks is a bit larger, at 103M, when it and its dependencies (parts of Symphony, Doctrine, etc.)
are installed. That's M, as in Megabytes. And in all honesty, it doesn't do a whole lot more than Tau!

Granted, there are some things that the aforementioned, unnamed framework can do that Tau can not. Some things
were kept out simply because I already use other libraries (e.g., Slim and uLogin for routing and user authentication
respectively), and other things were left out because I've never used them. 

That doesn't mean Tau is done. Nope, not at all. Some things I'd like to do in the near (whatever that means) future:

* Improve Validate module
* Improve Debug module
* Implement Config module
* Better docblocks
* Standardize code
* Get married

After that, I'm sure there will be more things I want to do with it.

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
