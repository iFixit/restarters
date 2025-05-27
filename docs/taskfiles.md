# Taskfiles Documentation

This document provides comprehensive documentation for the Taskfiles used in the Restarters project. The project uses [Task](https://taskfile.dev/) as a task runner to manage development workflows, Docker operations, and build processes.

## Overview

The Taskfiles are organized in a modular structure with a main `Taskfile.yml` that includes specialized taskfiles for different aspects of the project:

```
Taskfile.yml              # Main taskfile with includes and environment setup
├── Taskfiles/
    ├── docker.yml        # Docker container management
    ├── app.yml           # Laravel application tasks
    ├── deps.yml          # Dependency management
    └── build.yml         # Docker image building
```

## Main Taskfile Structure

### Taskfile.yml

The main taskfile serves as the entry point and orchestrates the modular taskfiles:

#### Includes
- **docker**: Docker container management tasks
- **app**: Laravel application-specific tasks  
- **deps**: Dependency management (Composer, NPM)
- **build**: Docker image building tasks

#### Environment Configuration
The taskfile loads environment variables from multiple `.env` files in order of precedence:
1. `.env.docker.local` (highest priority)
2. `.env.docker`
3. `.env.local`
4. `.env.base` (lowest priority)

#### Available Tasks
- `task` or `task default`: List all available tasks
- `task env:generate`: Generate `.env` file from `.env.template`
- `task setup`: Perform the complete development environment setup

## Docker Tasks (docker.yml)

Manages Docker container lifecycle and operations for the Laravel application.

### Container Profiles

The project uses Docker Compose profiles to manage different sets of containers:

- **core**: Essential services (app, database, redis)
- **debug**: Core + development tools (phpMyAdmin, Mailhog)
- **discourse**: Core + Discourse integration containers
- **all**: All available containers

### Available Tasks

#### Container Management
```bash
# Start containers by profile
task docker:up-core        # Start core services only
task docker:up-debug       # Start core + debug tools
task docker:up-discourse   # Start core + discourse
task docker:up-all         # Start all containers

# Stop containers by profile
task docker:down-core      # Stop core services
task docker:down-debug     # Stop core + debug tools
task docker:down-discourse # Stop core + discourse
task docker:down-all       # Stop all containers

# Restart containers by profile
task docker:restart-core
task docker:restart-debug
task docker:restart-discourse
task docker:restart-all

# Rebuild containers (removes volumes and images)
task docker:rebuild-core
task docker:rebuild-debug
task docker:rebuild-discourse
task docker:rebuild-all
```

#### Container Interaction
```bash
# View application logs
task docker:logs

# Open shell in application container
task docker:shell

# Run bash commands in container
task docker:run:bash -- ls -la

# Run artisan commands in container
task docker:run:artisan -- migrate

# Run tests in container
task docker:run:tests
```

#### Database Management
```bash
# Initialize test database
task docker:db:init-test
```

### Key Features
- **Auto-detection**: Automatically detects `docker-compose` vs `docker compose` command
- **Profile-based**: Uses Docker Compose profiles for flexible container management
- **Environment generation**: Automatically generates `.env` file before starting containers

## Application Tasks (app.yml)

Manages Laravel-specific operations with automatic Docker container execution.

### Available Tasks

#### Laravel & Artisan
```bash
# Run any artisan command
task app:artisan -- migrate
task app:artisan -- make:controller UserController

# Run tests
task app:tests
task app:tests --filter=UserTest
```

#### Cache Management
```bash
# Clear all Laravel caches
task app:clear:caches
```

#### Database Operations
```bash
# Run migrations
task app:db:migrate
task app:db:migrate -- --seed

# Run database seeders
task app:db:seed
task app:db:seed -- --class=UserSeeder
```

#### Code Generation
```bash
# Generate JavaScript translations
task app:generate:translations

# Generate Swagger API documentation
task app:generate:swagger

# Generate application key
task app:generate:key
```

### Smart Container Detection

All app tasks automatically detect whether they're running inside or outside a Docker container:
- **Outside container**: Automatically executes the command inside the Docker container
- **Inside container**: Runs the command directly

This is achieved through the `IN_DOCKER` environment variable check.

## Dependency Management (deps.yml)

Handles package management for both PHP (Composer) and JavaScript (NPM) dependencies.

### Available Tasks

#### Package Management
```bash
# Clear all dependencies
task deps:clear

# Run composer commands
task deps:composer -- require laravel/sanctum
task deps:composer -- update

# Install composer dependencies
task deps:composer:install

# Run npm commands  
task deps:npm -- install
task deps:npm -- run dev

# Install npm dependencies
task deps:npm:install
```



### Smart Dependency Management

- **Source tracking**: Composer install tracks `composer.json` and `composer.lock` changes
- **Generated files**: Automatically tracks generated files like `vendor/autoload.php`
- **Container awareness**: Like app tasks, automatically runs inside Docker when needed

## Build Tasks (build.yml)

Manages Docker image building for different environments.

### Available Tasks

```bash
# Build development image
task build:dev
task build:dev NAME=myapp TAG=v1.0.0

# Build staging image  
task build:staging
task build:staging NAME=myapp TAG=staging-v1.0.0

# Build production image
task build:prod
task build:prod NAME=myapp TAG=prod-v1.0.0
```

### Build Configurations

- **Development** (`build:dev`): Uses `Dockerfile` for development builds
- **Staging** (`build:staging`): Uses `Dockerfile.prod` with staging defaults
- **Production** (`build:prod`): Uses `Dockerfile.prod` with production defaults

### Customization

All build tasks accept optional parameters:
- `NAME`: Docker image name (default: "restarters")
- `TAG`: Docker image tag (default varies by environment)

## Development Environment Setup

The main `Taskfile.yml` includes a comprehensive `setup` task that orchestrates the complete development environment initialization.

### Setup Task

```bash
# Complete development environment setup
task setup
```

The `setup` task performs the following operations in sequence:
1. **Install Composer dependencies** (`task deps:composer:install`)
2. **Run database migrations** (`task app:db:migrate`)
3. **Generate JavaScript translations** (`task app:generate:translations`)
4. **Generate Swagger API documentation** (`task app:generate:swagger`)
5. **Generate application key** (`task app:generate:key`)
6. **Install NPM dependencies** with legacy peer deps (`task deps:npm:install`)
7. **Rebuild node-sass** (`task deps:npm`)

## Usage Examples

### Common Development Workflows

#### Initial Project Setup
```bash
# Start core containers
task docker:up-core
```

#### Daily Development
```bash
# Start containers with debug tools
task docker:up-debug

# Run migrations after pulling changes
task app:db:migrate

# Run tests
task app:tests

# Clear caches after config changes
task app:clear:caches
```

#### Adding Dependencies
```bash
# Add PHP package
task deps:composer -- require spatie/laravel-permission

# Add NPM package
task deps:npm -- install vue-router
```

#### Database Operations
```bash
# Create and run migration
task app:artisan -- make:migration create_users_table
task app:db:migrate

# Seed database
task app:db:seed
```

#### Building for Deployment
```bash
# Build production image
task build:prod NAME=restarters TAG=v2.1.0
```

## Advanced Features

### Environment Variable Precedence

The taskfiles support multiple environment configurations with clear precedence:
1. `.env.docker.local` (local overrides)
2. `.env.docker` (Docker-specific config)
3. `.env.local` (local config overrides)
4. `.env.base` (base configuration)

### Task Namespacing

Tasks are organized into logical namespaces:
- `docker:*` - Container operations
- `app:*` - Laravel application tasks
- `deps:*` - Dependency management
- `build:*` - Image building

### Variable Inheritance

The taskfiles use variable inheritance and namespacing:
- `DOCKER_NAMESPACE` allows flexible task composition
- Variables can be overridden at runtime
- Default values provide sensible fallbacks

### Error Handling

- Commands use `ignore_error: true` where appropriate
- Silent execution for routine operations
- Comprehensive error messages for debugging

## Best Practices

1. **Use profiles**: Start only the containers you need with appropriate profiles
2. **Environment files**: Keep sensitive data in `.env.docker.local` (gitignored)
3. **Task composition**: Leverage task dependencies for complex workflows
4. **Container awareness**: Let tasks automatically handle Docker execution
5. **Namespace consistency**: Use consistent namespacing when extending taskfiles

## Troubleshooting

### Common Issues

1. **Docker command not found**: Task auto-detects `docker-compose` vs `docker compose`
2. **Permission errors**: Ensure Docker daemon is running and user has permissions
3. **Port conflicts**: Check if ports are already in use before starting containers
4. **Environment variables**: Verify `.env` files are properly configured

### Debugging

```bash
# View container logs
task docker:logs

# View Laravel Logs
tail -f storage/logs/laravel.log

# Verify environment
task app:artisan -- env
```