# Rados Gateway API Client

PHP client for the Rados Gateway admin operations api.

## Requirements

* Minimum PHP 8.0

## Installation

### What type of installation should I choose?

It's very simple, choose for an express installation if your application already contains an HTTP library such 
as GuzzleHTTP, otherwise just perform an quick installation.

### Quick installation

For a quick installation we recommend you to install the `php-http/curl-client` package. This package is
a small abstraction around native php curl api. 

```bash
$ composer require php-http/curl-client guzzlehttp/psr7
```

After that you are ready to install this package:

```bash
$ composer require bk203/rgw-admin-client
```

## Usage

### Client configuration

Before you can use the api client you need to provide the `apiUrl`, `apiKey` and `secretKey`. You need to provided
them when creating an instance of the client class.

```php
use bk203\RgwAdminClient\Client;

$client = new Client('https://','apiKey','secretKey');
```

### Create and execute a request

There are two ways to interact with the rados api via this package, you can manually create the 
request and send them afterwards. See the code below for an example.

```php
$request = $client->createRequest('user', 'get', ['uid' => 'user-id']);

$response = $client->sendRequest($request);

var_dump($response);
```

You can also use the preferred short syntax. 

```php
$response = $client->get('user', ['uid' => 'user-id']);

var_dump($response);
```

See the [api docs](http://docs.ceph.com/docs/master/radosgw/adminops) for more information about the available api resources.

## Credits

- [Niels Tholenaar](https://github.com/nielstholenaar)
- [BK203](https://github.com/BK203)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
