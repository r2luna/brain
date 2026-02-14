# Upgrading to v2.0

Version 2.0 introduces new configuration options and enhanced flexibility for organizing your Brain components.

## Breaking Changes

### Configuration File

The configuration file has been completely restructured. If you've published the config in v1.x, republish it:

```bash
php artisan vendor:publish --provider="Brain\BrainServiceProvider" --force
```

### Default Behavior

**Domain Organization** — In v1.x, Brain automatically organized components into domain subdirectories. In v2.0, this is **opt-in**:

```php
// v1.x (automatic domains)
app/Brain/Users/Processes/CreateUser.php

// v2.0 default (flat structure)
app/Brain/Processes/CreateUser.php

// v2.0 with BRAIN_USE_DOMAINS=true
app/Brain/Users/Processes/CreateUser.php
```

## Migration Guide

To keep v1.x behavior, add this to your `.env`:

```bash
BRAIN_USE_DOMAINS=true
```

## New Features in v2.0

### Flexible Root Directory

```bash
BRAIN_ROOT=Domain  # app/Domain instead of app/Brain
```

### Optional Class Suffixes

```bash
BRAIN_USE_SUFFIX=true
# php artisan make:process CreateUser → CreateUserProcess.php
```

### Enhanced Logging

Built-in event system for tracking execution. See [Events & Logging](/events).
