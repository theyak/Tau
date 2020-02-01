.. title:: Tau, a minimal library for PHP

Installation
============
Place the contents of the repository where you'd like, for example your project's *lib* folder. Then include it.


.. code-block:: php

    $client = new GuzzleHttp\Client();
    $res = $client->request('GET', 'https://api.github.com/user', [
        'auth' => ['user', 'pass']
    ]);
    echo $res->getStatusCode();
    // "200"
    echo $res->getHeader('content-type')[0];
    // 'application/json; charset=utf8'
    echo $res->getBody();
    // {"type":"User"...'

    // Send an asynchronous request.
    $request = new \GuzzleHttp\Psr7\Request('GET', 'http://httpbin.org');
    $promise = $client->sendAsync($request)->then(function ($response) {
        echo 'I completed! ' . $response->getBody();
    });
    $promise->wait();

  
As this library was developed circa 2010, and as far as the author knows, not in use outside of personal
projects, there is no composer package available.

User Guide
==========

.. toctree::
 :maxdepth: 2

  database
