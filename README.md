# just-deploy
Deploy projects without any extra setup.

## 1. Install

```
composer require --dev dfba/just-deploy:0.1.*
```

or:

```
php composer.phar require --dev dfba/just-deploy:0.1.*
```

## 2. Configure

1. Copy the files from the `examples` folder into your project root.
2. Adjust the credentials in `JustDeployProduction.php`.
3. Commit `JustDeploy.php` to version control, but keep `JustDeployProduction.php` out of it.

## 3. Deploy

Windows:

```
vendor\bin\just-deploy production
```

Linux/Unix:

```
./vendor/bin/just-deploy production
```
