# Architecture Guide

Understanding the Modular Router's core architectural principles and how it integrates with the Power Modules framework.

## Core Philosophy

The Modular Router extends the Power Modules framework's principle of **explicit boundaries** to HTTP routing. Each module:

- Defines its own routes through the `HasRoutes` interface
- Maintains controller encapsulation within its own DI container
- Gets automatic route prefixing based on module name
- Can customize route prefixes and middleware stacks

This approach ensures that routing concerns remain properly encapsulated within modules while providing a unified HTTP interface for the entire application.

## Module-Centric Design

### Automatic Route Discovery

The router uses the framework's extension system to automatically discover and register routes without manual configuration:

```mermaid
flowchart TD
    A[Application Build] --> B[RoutingSetup Extension]
    B --> C[Scan Modules for HasRoutes]
    C --> D[Register Controllers with Module Containers]
    D --> E[Apply Route Prefixes]
    E --> F[Configure Middleware Stacks]
    F --> G[Ready to Handle Requests]
```

This automatic discovery eliminates boilerplate while maintaining clear module boundaries.

### Route Organization Patterns

Routes are organized hierarchically by module ownership:

```
Application Routes
├── /user (UserModule)
│   ├── /profile
│   ├── /settings
│   └── /preferences
├── /admin (AdminModule)
│   ├── /dashboard
│   ├── /users
│   └── /reports
└── /api/v1 (ApiModule with custom prefix)
    ├── /users
    ├── /orders
    └── /health
```

This organization provides clear ownership and prevents route conflicts between modules.

## Dependency Injection Architecture

### Container Hierarchy

The router creates a layered container architecture that preserves module encapsulation:

```
Application Container
└── Router Container (internal)
    ├── RouterModule Services
    │   ├── ModularRouterInterface
    │   ├── League\Route\Router
    │   └── Strategy Configuration
    └── Controller Registrations
        ├── UserController → UserModule Container
        ├── AdminController → AdminModule Container
        └── ApiController → ApiModule Container
```

### Controller Resolution Strategy

Controllers are resolved using the **InstanceViaContainerResolver** pattern:

1. **Registration Phase**: Controllers are registered in the router's container with references to their originating module containers
2. **Resolution Phase**: When a request arrives, the controller is instantiated from its original module's container
3. **Dependency Injection**: The module container provides all required dependencies

This ensures that:
- Controllers access their module's private services
- Module boundaries are respected at runtime
- Dependencies are resolved from the correct context

### Middleware Resolution Chain

Middleware resolution follows a clear precedence hierarchy:

```
Request → Module Middleware → Route Middleware → Controller
                ↑                   ↑
          Module Container    Router Container
```

This design allows for:
- **Module-level concerns** (authentication, logging, CORS)
- **Route-specific concerns** (validation, rate limiting)
- **Flexible composition** of middleware stacks

### Lazy Middleware Resolution Strategy

The router employs an innovative lazy resolution approach that combines League Route's built-in lazy loading with the Power Modules container system:

**Registration Phase** (during bootstrap):
```php
// For each middleware class, register a container reference
$this->container->set($middlewareClassName, $moduleContainer, InstanceViaContainerResolver::class);
$middlewareAwareInterface->lazyMiddleware($middlewareClassName);
```

**Resolution Phase** (per request):
1. League Route calls `$routerContainer->get($middlewareClassName)` when route is matched
2. `InstanceViaContainerResolver` delegates to `$moduleContainer->get($middlewareClassName)`
3. Middleware instantiated from correct module with proper dependencies

**Key Benefits**:
- **Performance**: Middleware only resolved when routes are actually hit
- **Memory Efficiency**: No pre-instantiated middleware instances during bootstrap
- **Module Encapsulation**: Middleware resolves from originating module container
- **Framework Integration**: Leverages League Route's `ContainerAwareInterface` strategy
- **Consistency**: Same `InstanceViaContainerResolver` pattern used for controllers

This approach demonstrates how to work **with** existing framework patterns rather than against them, achieving both performance and architectural goals.

## Request Lifecycle

The router integrates seamlessly with the Power Modules framework lifecycle:

```mermaid
sequenceDiagram
    participant Client
    participant Router
    participant Module
    participant Controller
    participant Service
    
    Client->>Router: HTTP Request
    Router->>Router: Route Resolution
    Router->>Module: Resolve Controller
    Module->>Controller: Instantiate with Dependencies
    Controller->>Service: Business Logic
    Service-->>Controller: Result
    Controller-->>Router: Response
    Router->>Router: Apply Response Decorators
    Router-->>Client: Final Response
```

### Framework Integration Points

1. **Module Registration**: Modules define their routing contracts through interfaces
2. **Setup Phase**: The `RoutingSetup` extension wires everything together
3. **Runtime Resolution**: Requests flow through the module system naturally
4. **Response Processing**: Global decorators provide cross-cutting concerns

## Design Principles

### Encapsulation First

Each module owns its routes, controllers, and dependencies completely:

- **Route Definitions**: Modules define their own URL structure
- **Controller Dependencies**: Resolved from module-specific containers
- **Middleware Stacks**: Module-level and route-level composition
- **Business Logic**: Contained within module boundaries

### Convention over Configuration

The router minimizes boilerplate through intelligent defaults:

- **Automatic Discovery**: No manual route registration required
- **Conventional Prefixing**: Module names become URL prefixes
- **Standard Contracts**: Simple interfaces for common patterns
- **Override Mechanisms**: Escape hatches for custom requirements

### Composition over Inheritance

Complex routing behavior emerges from simple, composable pieces:

- **Module Interfaces**: Single-purpose contracts (`HasRoutes`, `HasMiddleware`)
- **Middleware Stacking**: Layered concerns without coupling
- **Response Decorators**: Global transformations without modification
- **Strategy Pattern**: Pluggable routing strategies

## Architectural Benefits

### Team Scalability
- **Independent Development**: Modules can be developed in parallel
- **Clear Ownership**: Route ownership maps to team boundaries
- **Reduced Conflicts**: Module prefixes prevent route collisions
- **Easy Onboarding**: New developers understand boundaries quickly

### System Evolution
- **Incremental Changes**: Modify individual modules without affecting others
- **Feature Flags**: Enable/disable modules conditionally
- **API Versioning**: Multiple API modules can coexist
- **Migration Paths**: Legacy and new systems can run side-by-side

### Testing Strategy
- **Unit Testing**: Test modules in complete isolation
- **Integration Testing**: Verify module interactions through HTTP
- **Contract Testing**: Ensure interface compliance
- **End-to-End Testing**: Full request/response cycles

## Controller Resolution Strategy

### How Controllers Are Resolved

The router uses a sophisticated controller resolution strategy that maintains module encapsulation:

**Registration Process**:
1. Controllers are registered using their **fully qualified class name** (e.g., `App\User\UserController`)
2. Each controller registration includes a reference to its originating module's container
3. The `InstanceViaContainerResolver` handles resolution from the correct module container

**Resolution Process**:
1. When a request arrives, the router looks up the controller by its fully qualified class name
2. The `InstanceViaContainerResolver` receives the module container as the resolution context
3. The controller is instantiated from its originating module's container with proper dependencies

**Key Benefits**:
- **True Module Encapsulation**: Controllers access only their module's services
- **No Class Name Conflicts**: Different namespaces prevent collisions naturally
- **Proper Dependency Resolution**: Each controller gets dependencies from its own module
- **Container Isolation**: Modules cannot accidentally access other modules' private services

### Controller Sharing Patterns

**Namespace Separation (Recommended)**:
```php
// UserModule
App\User\UserController::class

// AdminModule  
App\Admin\UserController::class
```
These are completely separate classes despite similar names.

**Intentional Controller Sharing (Advanced)**:
```php
// Multiple modules can deliberately share the same controller class
App\Shared\HealthController::class
```
In this case, the last registered module's container will be used for resolution, which is usually acceptable for shared components.

### Module Loading Order

Module registration order has minimal impact:
- **Controller resolution**: Fully qualified names prevent conflicts
- **Middleware precedence**: Module middleware runs in registration order
- **Configuration merging**: Later modules can override earlier configuration

**Best Practice**: Design modules to be order-independent for maximum flexibility.

## Extension Points

### Custom Strategies

Replace the default League/Route strategy for specialized behavior:
- JSON-first APIs
- GraphQL endpoints  
- Custom authentication flows
- Specialized error handling

### Response Decorators

Add global response transformations:
- CORS headers for browser APIs
- Security headers for all responses
- Performance tracking and metrics
- API versioning information

### Middleware Composition

Create sophisticated middleware stacks:
- Authentication and authorization
- Request/response logging
- Rate limiting and throttling
- Content negotiation

For detailed API documentation and implementation examples, see the [API Reference](api-reference.md).