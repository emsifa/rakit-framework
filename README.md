Rakit Framework (MOVED [HERE](https://github.com/rakit/framework))
===================================================================

[![Build Status](https://img.shields.io/travis/emsifa/rakit-framework.svg?style=flat-square)](https://travis-ci.org/emsifa/rakit-framework)
[![Dependency Status](https://www.versioneye.com/user/projects/5683f558eb4f47003c000b64/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/5683f558eb4f47003c000b64)
[![Github Issues](http://githubbadges.herokuapp.com/emsifa/rakit-framework/issues.svg?style=flat-square)](https://github.com/emsifa/rakit-framework/issues)
[![License](http://img.shields.io/:license-mit-blue.svg?style=flat-square)](http://doge.mit-license.org)



Rakit Framework adalah _micro PHP framework_ yang dikembangkan untuk menangani website skala kecil - enterprise. 
Framework ini terinspirasi penuh oleh Laravel/Lumen framework, hanya saja dengan size yang terbilang cukup ringan (dibawah 500KB).
_Micro Framework_ sendiri berarti Rakit Framework tidak membatasi developer dalam membangun struktur aplikasinya, struktur dapat dibuat mengadaptasi MVC seperti gaya Codeigniter, Laravel/Lumen, dsb.

Saat ini Rakit Framework masih dalam tahap pengembangan.

## Features

* RESTful Routing
* Route Middleware (with params)
* Hook
* Automatic Resolution (Constructor Injection and Callable Injection)
* Lazy loading
* Easy file upload

## Basic Examples

#### Hello World

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->get('/', function() {
    return "Hello World!";
});

$app->run();

```

#### Json Response

Untuk mengirimkan JSON, cukup return sebuah array dari controller/middleware.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->get('/', function() {
    return [
        'status' => 'ok',
        'message' => 'Hello World'
    ];
});

$app->run();

```

#### Route Parameter

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->get('/hello/:name', function($name) {
    return "Hello {$name}";
});

$app->run();

```

#### Route Optional Parameter

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->get('/hello/:name(/:age)', function($name, $age = 18) {
    return "My name is {$name}, i am {$age} years old";
});

$app->run();

```

#### Route Conditional Parameter

Gunakan method `where($param, $regex)` untuk mengkondisikan parameter.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->get('/hello/:name/:age', function($name, $age = 18) {
    return "My name is {$name}, i am {$age} years old";
})->where('age', '[0-9]+');

$app->run();

```

Contoh diatas, jika kamu mengakses `yoursite.com/hello/John/asd` akan menampilkan error 404 karena `asd` mewakili parameter `age`, dan itu tidak match dengan `[0-9]+`.

#### Route Group

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->group('/admin/:type', function($group) {

    // route GET /admin/:type/settings
    $group->get('/settings', function() {
        return "Page settings";
    });

    // nested group
    $group->group('/article', function($group) {

        // route GET /admin/:type/article/create
        $group->get('/create', function() {
            return "Page create article";
        });

        // route POST /admin/:type/article/create
        $group->post('/create', function() {
            return "Article posted!";
        });

    });

})->where('type', '(admin|writer)');

$app->run();

```

Contoh diatas akan mendaftarkan 3 buah route, yaitu:

* GET `/admin/:type/settings`
* GET `/admin/:type/article/create`
* POST `/admin/:type/article/create`

dan ketiganya mengharuskan parameter `type` antara 'admin' atau 'writer'. 


#### Basic Middleware

Untuk mendaftarkan middleware, dapat menggunakan method `middleware($name, $callable)`.
Untuk menggunakannya, dapat menggunakan method middleware(), atau memanggil method dengan nama middleware tersebut.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

// mendaftarkan middleware 'auth'
$app->middleware('auth', function($req, $res, $next) {
    if(!isset($_SESSION['user'])) {
        return $res->send("Mesti login dulu om", 403);
    }

    return $next();
});

// menggunakan middleware 'auth' tersebut memanfaatkan Route::__call()
$app->get('/admin', function() {
    return "Admin Page";
})->auth();

// atau, menggunakan middleware 'auth' tersebut melalui method Route::middleware()
$app->get('/admin', function() {
    return "Admin Page";
})->middleware(['auth']);

$app->run();
```

#### Using Middleware for Manipulate Response

Contoh dibawah ini adalah penggunaan middleware dalam memanipulasi response body.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->middleware('uppercase', function($req, $res, $next) {
    $next();
    return strtoupper($res->body);
});

$app->get('/', function() {
    return "Hello World!";
})->uppercase();

$app->run();
```

Contoh diatas jika dijalankan akan menampilkan "HELLO WORLD!"


#### Middleware with parameters

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->middleware('auth', function($req, $res, $next, $tipe) {
    if(!isset($_SESSION['user']) OR $tipe !== $_SESSION['user']['tipe']) {
        return $res->send("Mesti login sebagai {$tipe} dulu om", 403);
    }

    return $next();
});

$app->get('/siswa', function() {
    return "Admin Page";
})->auth('siswa');

// atau...
$app->get('/siswa', function() {
    return "Admin Page";
})->middleware(['auth:siswa']);

$app->run();
```

#### Multiple Middleware

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

$app->middleware('uppercase', function($req, $res, $next) {
    $next();
    return strtoupper($res->body);
});

$app->middleware('jsonify', function($req, $res, $next) {
    $next();
    if(false == $res->isJson()) {
        return [
            'status' => $res->getStatus(),
            'body' => $res->body
        ];
    }
});

$app->get('/siswa', function() {
    return "Admin Page";
})->uppercase()->jsonify();

// atau...
$app->get('/siswa', function() {
    return "Admin Page";
})->middleware(['uppercase', 'jsonify']);

$app->run();
```

#### Using middleware in group

Menggunakan middleware di group pada dasarnya sama saja. Di penutup group, cukup gunakan nama middleware 
sebagai method, atau gunakan method middleware.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

// mendaftarkan middleware 'auth' ke aplikasi
$app->middleware('auth', function($req, $res, $next, $tipe) {
    if(!isset($_SESSION['user']) OR $tipe !== $_SESSION['user']['tipe']) {
        return $res->send("Mesti login sebagai {$tipe} dulu om", 403);
    }

    return $next();
});


$app->group('/admin/:type', function($group) {

    // route GET /admin/:type/settings
    $group->get('/settings', function() {
        return "Page settings";
    });

    // nested group
    $group->group('/article', function($group) {

        // route GET /admin/:type/article/create
        $group->get('/create', function() {
            return "Page create article";
        });

        // route POST /admin/:type/article/create
        $group->post('/create', function() {
            return "Article posted!";
        });

    });

})
->where('type', '(admin|writer)')
->auth(); // menggunakan middleware auth tersebut

$app->run();

```

Pada contoh diatas, 3 route di dalam group tersebut akan menggunakan middleware `auth`.

#### Play with Hook

Hook adalah sekumpulan koleksi function/callable dengan alias tertentu yang dapat dipanggil sewaktu-waktu dalam aplikasi.

##### The Basic

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

// mendaftarkan hook 'nyan'
$app->on('nyan', function($response) {
    $response->body .= " nyan";
});

$app->get('/', function() use ($app) {
    $app->response->body = "Hello";

    // menjalankan hook
    $app->hook->apply('nyan', [$app->response]);
});

$app->run();
```

Contoh diatas apabila dijalankan, akan menghasilkan output 'Hello nyan'.

##### Handle Response Status Code with Hook

Pada dasarnya rakit framework akan memanggil beberapa hook saat aplikasi berjalan. Diantaranya saat response akhir (beberapa saat sebelum response di kirim) menunjukan http response status code tersebut.


Contoh:

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;

$app = new App('MyAwesomeApp');

// handle Response code 200
$app->on(200, function($response) {
    // mengubah response body menjadi "OK!"
    $response->body = "OK!";
});

// handle error 404
$app->on(404, function($response) {
    $response->body = "Error 404! Page not found";
});

// Or you can do this
$app->on('40x', function($response) {
    $response->body = "Error ".$response->getStatus();
});

$app->on('5xx', function($response) {
    $response->body = "Terjadi kesalahan pada server";
});

```

##### Do Something from Specified Request Method

Jika kamu ingin melakukan sesuatu sesaat setelah Request match dengan Route tertentu, kamu bisa 
mendaftarkan hook sesuai method tersebut.

```php

$app = new App('MyAwesomeApp');

// When request method is GET
$app->on('GET', function() use ($app) {
    // do something
});

// When request method is POST
$app->on('POST', function() use ($app) {
    // do something
});

$app->on('POST', function() use ($app) {
    // statements here will executed too (when request method is POST)
});

``` 

#### Callable injection

Pada dasarnya hampir semua class dan callable di Rakit Framework injectable. Dalam artian secara otomatis
Rakit Framework akan menginject dependency ke dalam parameter constructor(jika berupa class) atau callable tersebut.

Dibawah ini adalah contoh inject Request dan Response object ke dalam callable action controller.

```php
<?php // index.php (at root project)

require("vendor/autoload.php");

use Rakit\Framework\App;
use Rakit\Framework\Http\Request;
use Rakit\Framework\Http\Response;

$app = new App('MyAwesomeApp');

$app->post('/account', function(Request $request, Response $response) {
    $nama = $request->get("nama");
    $uploaded_foto = $request->file("foto");
    $uploaded_foto->move("./uploads");

    // ...

    return $response->json([
        'status' => 'ok',
        'message' => 'blah blah blah'
    ]);
});

$app->run();
```

> Object yang dapet diinject kedalam constructor atau callable adalah Object yang terdaftar dalam container aplikasi.
(baca: mendaftarkan object ke dalam container)

### Easy File Upload

Rakit framework dibuat dengan sintax yang mudah dipahami. Jika pada PHP Native, kamu melakukan upload file dengan kode yang kira-kira seperti ini:

```php
<?php

// cek ada upload photo atau tidak
if(isset($_FILES['photo']) AND is_uploaded_file($_FILES['photo']['tmp_name'])) {

    $tmp = $_FILES['photo']['tmp_name'];
    $filename = $_FILES['photo']['name'];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    // set nama baru dengan ekstensi sesuai dengan file yg terupload
    $newname = 'newname.'.$extension;
    $upload_dir = 'my/upload/dir';
    $destination = $upload_dir.'/'.$newname;

    if( ! is_writeable($upload_dir) OR ! move_uploaded_file($tmp, $destination)) {
        // file tidak ter-upload
    }
}
```

Dengan rakit framework akan menjadi seperti ini:

```php
// action coba-coba upload photo
public function tryUploadPhoto(Request $req) 
{
    $photo = $req->file('photo');
    // cek ada upload photo atau tidak
    if($photo) {
        // set nama baru dengan ekstensi sesuai dengan file yg terupload
        $photo->name = 'newname';

        try {
            $photo->move('my/upload/dir');
        } catch (\Exception $e) {
            // terjadi kesalahan, file tidak ter-upload
        }
    }

}
```

#### Upload Multiple File

Dengan PHP Native, upload multiple file akan seperti ini:

```php
// cek ada upload multiple image atau tidak
if(isset($_FILES['image']) AND is_array($_FILES['image']['tmp_name'])) {

    $upload_dir = 'my/upload/dir';
    
    foreach($_FILES['image']['tmp_name'] as $i => $tmp_file) {
        $filename = $_FILES['image']['name'][$i];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $newname = 'image-'.($i+1).'.'.$extension;
        $destination = $upload_dir.'/'.$newname;

        move_uploaded_file($tmp_file, $destination);
    }

}
```

Dengan rakit framework akan jadi semudah ini:

```php
public function tryMultipleFileUpload(Request $req)
{
    $images = $req->files('image');
    foreach($images as $i => $image) {
        $image->name = 'image-'.($i+1);
        $image->move('my/upload/dir');
    }
}
```

> Contoh-contoh upload diatas hanya untuk memperlihatkan bagaimana perbandingan metode upload file native dengan rakit framework. Dalam implementasinya, kamu harus
menambahkan beberapa baris code untuk memvalidasi file yang akan di upload tersebut.

## Coming soon

Saat ini rakit framework masih dalam pengembangan, beberapa test belum ditambahkan. 
