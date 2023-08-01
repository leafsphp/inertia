<!-- markdownlint-disable no-inline-html -->
<p align="center">
    <br><br>
    <img src="https://leafphp.dev/logo-circle.png" height="100"/>
    <br><br>
</p>

# Leaf + Inertia

This is a simple package that helps you use [Inertia.js](https://inertiajs.com/) with [Leaf](https://leafphp.dev). It provides a `Inertia` class that makes it easy to return Inertia responses from your Leaf controllers and output Inertia template in your Leaf views.

Since Leaf supports multiple templating engines, inertia uses the engine configured in your view config.

## Installation

**Note: This is already done for you in Leaf MVC.**

Since this is the server-side adapter for Leaf, you need to install Inertia for whatever framework you're using on the client-side. You can find a list on the [Inertia website](https://inertiajs.com/).

```bash
npm install @inertiajs/react
```

After this, you can add the Leaf adapter to your project using the Leaf CLI:

```bash
leaf install inertia
```

Or with composer:

```bash
composer require leafs/inertia
```

## Usage

To get started, you need replace your Leaf view with the Inertia component. In place of your default Leaf view, you should return the `Inertia::render` method. This method accepts the name of your component as its first argument, and an array of data as its second argument:

```php
app()->get('/', function() {
    return Inertia::render('Home', [
        'name' => 'Leaf'
    ]);
});
```

**Full docs on [the leaf docs](https://leafphp.dev/modules/views/inertia/).**
