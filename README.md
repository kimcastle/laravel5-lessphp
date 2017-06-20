# laravel5-lessphp
lessphp is a compiler for LESS by PHP

(1) First, pull in the package through Composer.

```json
"require": {
    "kimcastle/laravel5-lessphp": "1.0.0"
}
```

(2) And then, if using Laravel 5, include the service provider within config/app.php.

```php
'providers' => [
    "Kimcastle\Laravel5Less\LessServiceProvider::class"
];
```

In the aliases section, add:

```php
'Less' => Kimcastle\Laravel5Less\Facades\Less::class
```

(3) Uage

Within your models or controllers, you can perform modification to the outputted CSS. Here are some examples:

```php
use Kimcastle\Laravel5Less\Facades\Less;

class TestController extend Controller
{
     public function less()
     {
          Less::modifyVars(['@externalBgColor' => 'red'])->compile('less_test');
          
          Less::modifyVars(['@externalBgColor' => '#ff0001'])->url('less_test', true);
     }
}

```

OK!




