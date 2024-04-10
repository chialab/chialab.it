# Chialab.it & friends

## Websites

* Chialab Design Company (https://www.chialab.it)
* Chialab Open Source (https://www.chialab.io)
* Illustratorium (https://www.illustratorium.it/)

## Local project setup

### Requirements

* PHP >= 8.2
* MySQL >= 8
* Composer
* Node.js
* Yarn

### Install dependencies

```bash
composer install
```

```bash
yarn install
```

### Config

Copy the `config/app_local.example.php` file to `config/app_local.php` and set your local configuration.

```bash
cp config/app_local.example.php config/app_local.php
```

and make sure to update the `Datasource` section with your MySQL connection settings.

Then, ensure the following environment variables are set:

* `FRONTEND_PLUGIN` - The frontend plugin to use (could be `BEdita/API` for API frontend or `Chialab` for websites frontends
* `THEME` - The theme to use (could be `Chialab` for chialab.it or `OpenSource` for chialab.io)

You can set the environment variables in your virtual host, configuration:

```
SetEnv FRONTEND_PLUGIN Chialab
SetEnv THEME Chialab
```

or in the `config/.env` file:

```
export FRONTEND_PLUGIN="Chialab"
export THEME="Chialab"
```

## Migrations

Run cake migrations to create or update the database schema:

```bash
composer migrate
```

Then, update the project model:

```bash
bin/cake project_model
```
