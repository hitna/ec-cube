# EC-CUBE Development Guide

## Project Overview

EC-CUBE is Japan's leading open-source e-commerce platform. This is the 4.3 branch, built on Symfony 6.4 and PHP 8.1+.

- **Repository**: https://github.com/EC-CUBE/ec-cube
- **Documentation**: https://doc4.ec-cube.net/
- **License**: GPL-2.0 / proprietary dual license

## Technology Stack

- **PHP**: 8.1 / 8.2 / 8.3
- **Framework**: Symfony 6.4 (full-stack)
- **ORM**: Doctrine ORM 2.x, DBAL 3.x
- **Template**: Twig 3.8
- **Database**: PostgreSQL 12+ or MySQL 8.4
- **Frontend**: Sass (SCSS), webpack, jQuery
- **Testing**: PHPUnit (via symfony/phpunit-bridge), Codeception (E2E)
- **Static Analysis**: PHPStan
- **Code Style**: PHP-CS-Fixer

## Directory Structure

```
src/Eccube/           # Core application code
  Controller/         # HTTP controllers (admin and front)
  Entity/             # Doctrine ORM entities
  Repository/         # Doctrine repositories
  Service/            # Business logic services
    PurchaseFlow/     # Order processing pipeline
  Form/               # Symfony form types and extensions
  Event/              # Event subscribers
  EventListener/      # Event listeners
  Twig/               # Twig extensions and functions
  Plugin/             # Plugin management system
  Command/            # Symfony console commands
  Resource/
    doctrine/         # ORM mapping files (XML)
    template/         # Core Twig templates
    config/           # Service definitions

app/
  Customize/          # Project-specific customizations (safe from upgrades)
    Controller/
    Entity/
    Form/Extension/
    Repository/
    Service/
    Twig/
    Resource/template/
  Plugin/             # Installed plugins
  config/eccube/      # Application configuration (packages, routes, services)
  template/           # Template overrides
  DoctrineMigrations/ # Database migrations
  proxy/entity/       # Auto-generated entity proxy classes

html/                 # Web root / public document root
  template/
    admin/assets/     # Admin panel assets (CSS, JS, images)
    default/assets/   # Storefront assets

tests/
  Eccube/Tests/       # PHPUnit tests
```

## Development Commands

### Installation

```bash
# Docker (recommended)
docker compose -f docker-compose.yml -f docker-compose.pgsql.yml up -d

# Composer
composer create-project ec-cube/ec-cube ec-cube "4.3.x-dev" --keep-vcs
bin/console eccube:install
```

### Testing

```bash
# Run all unit tests
bin/phpunit

# Run a specific test file
bin/phpunit tests/Eccube/Tests/Web/ShoppingControllerTest.php

# Run tests matching a filter
bin/phpunit --filter testCompleteWithLogin
```

### Static Analysis

```bash
vendor/bin/phpstan analyse            # paths are configured in phpstan.neon.dist
vendor/bin/phpstan analyse app/Customize --level=1   # narrow the scope while iterating
```

### Code Style

```bash
# Check for violations
vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix
vendor/bin/php-cs-fixer fix
```

### Building Assets

```bash
npm ci
npm run build          # Build Sass and JavaScript

# Docker environment
docker compose -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.nodejs.yml run --rm -T nodejs npm ci
docker compose -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.nodejs.yml run --rm -T nodejs npm run build
```

### Cache Management

```bash
bin/console cache:clear
bin/console cache:warmup
```

### Database

```bash
bin/console doctrine:schema:update --dump-sql   # Preview SQL changes
bin/console doctrine:migrations:diff            # Generate migration
bin/console doctrine:migrations:migrate         # Run migrations
```

## Architecture

### PurchaseFlow (Order Processing Pipeline)

PurchaseFlow is the core order processing engine located in `src/Eccube/Service/PurchaseFlow/`.

`PurchaseFlow::validate()` runs the following stages **in this order**. Note that
validators run *before* preprocessors — this is the opposite of what the naming suggests,
and it is the single most common source of wrong assumptions when extending the flow:

1. **ItemValidator**: Validate each item (price change detection, stock, sale limits)
2. **ItemHolderValidator**: Validate the whole cart/order
3. **ItemPreprocessor**: Adjust each item
4. **ItemHolderPreprocessor**: Adjust the whole cart/order (delivery fees, payment charges, tax)
5. **DiscountProcessor**: Remove, then re-add discount line items
6. **ItemHolderPostValidator**: Final validation after all processing

`PurchaseFlow::prepare()` / `commit()` / `rollback()` then run the **PurchaseProcessor**
stage separately (reduce stock, award points, generate order numbers).

A practical consequence: an `ItemPreprocessor` cannot be used to change a unit price,
because `PriceChangeValidator` has already reset it to the `ProductClass` price
(`getPrice02IncTax()` for `CartItem`, `getPrice02()` for `OrderItem`) and reported a
price-change warning. To change unit prices you must replace or decorate that validator —
its service id is `eccube.purchase.flow.item.validator.price.change.validator`.

Configuration is in `app/config/eccube/packages/purchaseflow.yaml`. Each processor is wired
by a tag (`eccube.item.validator`, `eccube.item.preprocessor`, ...) carrying `flow_type`
(`cart` / `shopping` / `order`) and `priority`. Redefining a service id replaces its tags
too, so re-declare them when overriding.

### Event System

EC-CUBE extends Symfony's EventDispatcher for customization:

- **Template Events**: Inject content into specific template locations (e.g., `Event/EccubeEvents.php`)
- **Controller Events**: Modify request/response in controller lifecycle
- **Entity Events**: Doctrine lifecycle callbacks

### Plugin System

Plugins are self-contained packages in `app/Plugin/{PluginCode}/`:

- Each plugin has `PluginManager.php` for install/uninstall/enable/disable lifecycle hooks
- Plugins can add entities, controllers, forms, templates, and event subscribers
- Plugin metadata is defined in `composer.json` within the plugin directory

### Customization via app/Customize

All project-specific code should go in `app/Customize/` to survive core upgrades:

- **Entity extensions**: Use Doctrine traits to add fields to existing entities
- **Form extensions**: Use Symfony FormTypeExtension to add fields to existing forms
- **Template overrides**: Place templates in `app/template/` to override core templates
- **Service overrides**: Use Symfony service decoration or compiler passes

### Front-end Templates and Blocks

**Which sections appear on a page is stored in the database, not in templates.** The page
template only renders `{% block main %}`; everything around it comes from rows in
`dtb_layout`, `dtb_block` and `dtb_block_position`. The `section` column maps to the
`Layout::TARGET_ID_*` constants (`3` = header, `7` = main bottom, `10` = footer, `11` =
drawer). Reading `index.twig` alone will not tell you what the top page shows — query those
tables, or look at **Admin > コンテンツ管理 > レイアウト管理**.

To add a section you must both create `Block/<file_name>.twig` and insert a `dtb_block` row
plus a `dtb_block_position` row; adding only the template does nothing.

**Several core block templates are hardcoded demo content, not dynamic.** They look like they
read from the database but do not:

- `Block/new_item.twig` — product names, prices and images are literals. The names and prices
  live in `src/Eccube/Resource/locale/messages.*.yaml` as translation keys
  (`front.block.new_item.item_1_name` is `彩のジェラート"CUBE"`), and the images are
  hardcoded filenames such as `cube-1.png`.
- `Block/category.twig` — three placeholder images (`fpo_355x150.png`) with hardcoded
  category ids.

If you need these to reflect real data, override the template in `app/template/<theme>/Block/`
and supply the data from a Twig extension.

### Front-end Styling

Custom CSS belongs in `html/user_data/assets/css/customize.css`. `default_frame.twig` already
loads it **after** `assets/css/style.css`, so no template change is needed.

**Match the core's selector depth or your rules are silently ignored.** `style.css` uses the
`.ec-block .ec-block__element` pattern (two classes, specificity 0-2-0) in roughly 540 places.
A single-class override such as `.ec-headerSearch__keywordBtn { ... }` is 0-1-0 and loses —
with no error, no warning, and nothing in the console. Write overrides as:

```css
/* scope + block + element: 0-3-0, wins against the core's 0-2-0 */
.ec-layoutRole .ec-headerSearch .ec-headerSearch__keywordBtn { ... }
```

`.ec-layoutRole` wraps the header, contents and footer, so it works as a general scope.

Other things worth knowing before overriding front-end styles:

- `style.css` is generated (20,000+ lines) and defines ~870 selectors more than once, so the
  winning declaration is often not the first one you find. Search for every occurrence.
- Some core rules only show up visually, never in markup, and the properties that cause them
  are often split across rules. For example `.ec-select` sets `overflow: hidden` while
  `.ec-select.ec-select_search` sets a 50px left `border-radius` in a media query; together
  they clip a child element's background into a pill shape. Reset the properties you did not
  expect, not just the ones you are setting.
- A few class names contain typos that you must reproduce exactly, such as
  `.ec-cartRow__sutbtotal` (not `subtotal`).
- `html/template/<theme>/assets/css/*.css` is build output from `assets/scss/`. Never edit it
  directly; run `npm run build`.

### Entity Proxy System

EC-CUBE uses a proxy system for entities in `app/proxy/entity/`. When plugins or customizations add traits to entities, the proxy generator creates extended entity classes. Run `bin/console eccube:generate:proxies` to regenerate.

**The file under `src/Eccube/Entity/` is not the class that runs.** Each entity file wraps its
body in `if (!class_exists(Product::class)) { ... }`. Once proxies are generated, the copy in
`app/proxy/entity/src/Eccube/Entity/` is autoloaded first and the original is skipped. The
generated copy is what actually pulls in the `@EntityExtension` traits:

```php
// app/proxy/entity/src/Eccube/Entity/Product.php
class Product extends AbstractEntity
{
    use \Customize\Entity\ProductDiscountTrait;   // added by the generator
```

Two consequences when writing code against extended entities:

- Reading `src/Eccube/Entity/Product.php` will **not** show fields added by customizations or
  plugins. Check `app/proxy/entity/` instead, or the trait itself.
- Static analysis and IDEs do not see those fields either. Guard optional accessors with
  `method_exists($Product, 'getDiscountRate')` when the trait may be absent.

After adding or changing an `@EntityExtension` trait, regenerate proxies and clear the cache
before anything else, or you will debug a stale class.

## Coding Conventions

- Follow PSR-12 coding style (enforced by PHP-CS-Fixer)
- Use PHP type declarations for parameters and return types
- Entity classes use Doctrine **annotations** (`@ORM\...` in `src/Eccube/Entity/`). There is no XML mapping; `src/Eccube/Resource/doctrine/` only holds CSV import definitions and migration helpers
- Controllers extend `Eccube\Controller\AbstractController`
- Form types extend `Symfony\Component\Form\AbstractType`
- Repositories extend `Eccube\Repository\AbstractRepository`
- Use `@Route` annotations for routing
- Template files use `.twig` extension and follow Twig coding standards
- Admin templates are in `Resource/template/admin/`, storefront in `Resource/template/default/`

## Key Entities

- `Customer` — Registered customer
- `Product` / `ProductClass` — Products and their variations (size, color)
- `Order` / `OrderItem` — Orders and line items
- `Shipping` — Shipping information (multiple per order supported)
- `Cart` / `CartItem` — Shopping cart
- `Member` — Admin user
- `Plugin` — Installed plugin metadata
- `BaseInfo` — Store configuration (shop name, address, tax settings)

@CLAUDE.local.md
