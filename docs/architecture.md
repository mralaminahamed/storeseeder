# Architecture Documentation

Welcome to the comprehensive architecture guide for StoreSeeder. This document provides detailed insights into the plugin's modern, enterprise-grade architecture designed for scalability, maintainability, and developer experience.

## 🏗️ Modern Plugin Structure

StoreSeeder follows a clean, modular architecture that separates concerns while maintaining tight integration with WordPress standards and a driver layer for each supported e-commerce platform.

```
storeseeder/
├── storeseeder.php              # Plugin header, constants, bootstrap
├── class-storeseeder.php        # Singleton orchestrator: menu, assets, REST wiring
├── includes/                    # PHP backend. PSR-4: StoreSeeder\ → includes/
│   ├── Generators/              # What data looks like — no platform knowledge
│   │   ├── Generator.php        #   abstract base: FakerPHP, batching, preview, logging
│   │   └── Resources/           #   17 concrete generators, one per resource
│   ├── Controllers/             # REST surface
│   │   ├── Controller.php       #   abstract base: params, validation, platform resolution
│   │   └── Resources/           #   17 controllers, one per resource
│   ├── MCP/                     # Model Context Protocol integration (optional)
│   │   ├── MCP_Server.php       #   ability + tool registration
│   │   └── Abilities/
│   │       ├── Ability.php      #   abstract base: dispatches through the REST API
│   │       └── Resources/       #   17 abilities, one per resource
│   └── Platforms/               # Where data goes
│       ├── Platform_Interface.php  # what a platform must answer
│       ├── Platform_Driver.php     # abstract base for shipped drivers
│       ├── Writer.php              # abstract base: persists one resource
│       ├── Registry.php            # holds drivers; owns storeseeder_platforms
│       ├── Resolver.php            # auto | explicit → one target platform
│       ├── Capability.php          # can this platform do this, and why not
│       ├── Resource.php            # the 17 canonical resource names
│       ├── Status.php              # canonical status vocabulary
│       └── Drivers/
│           └── Fluent_Cart/
│               ├── Platform.php    # capability matrix + writer map
│               └── Writers/        # 17 writers, one per resource
├── src/                         # React admin (TypeScript)
│   ├── index.tsx                # entry point, mounts into #storeseeder-root
│   ├── components/              # App.tsx, Pages/, shell/, generator/, home/,
│   │                            #   dashboard/, overlays/, ui/
│   ├── lib/                     # generators.ts, platform.ts, fieldsFromSchema.ts, …
│   ├── providers/               # Stats, Toast, Batch, Platform contexts
│   ├── theme/  types/           # theme provider, shared TypeScript types
│   └── styles.css  components.css
├── build/                       # Compiled assets — the only JS shipped
├── tests/
│   ├── php/                     # PHPUnit, mirroring the includes/ layout
│   └── e2e/                     # Playwright specs
├── docs/                        # Documentation
├── composer.json  package.json  # Dependencies and scripts
├── webpack.config.js  tsconfig.json  postcss.config.js  tailwind.config.js
└── phpcs.xml  phpstan.neon  phpunit.xml.dist
```

### Directory structure explanation

The layout states the class hierarchy: each abstract sits at the root of the scope it
governs, and its concrete children nest one level beneath it.

- **`includes/Generators/`** — shapes data. A generator names no platform: no models, no
  table names, no platform status strings, no database reads. That restriction is what lets
  one generator feed every platform, and lets a fixed seed produce the same data on all of
  them.
- **`includes/Platforms/`** — persists data. Writers are the only place a platform's models,
  tables and status spellings appear. Drivers register through the
  `storeseeder_platforms` filter, so a platform can be added from a separate plugin.
- **`includes/Controllers/`** — the REST surface, and where the target platform is resolved
  for a request.
- **`includes/MCP/`** — optional AI tooling; abilities dispatch through the REST API rather
  than calling generators directly.
- **`src/`** — the React admin.
- **`build/`** — compiled assets; the source in `src/` is not shipped in the plugin package.

## 🔗 Deep Fluent Cart Integration

StoreSeeder is built as a native extension of the Fluent Cart ecosystem, ensuring seamless compatibility and data integrity.

### 🎯 Native Model Integration

The plugin leverages Fluent Cart's core data models directly:

- **Product Model**: Full integration with product attributes, variations, and inventory systems
- **Customer Model**: Uses customer profiles, loyalty tiers, and purchase history tracking
- **Order Model**: Implements complete order processing with payment, shipping, and tax calculations
- **Coupon Model**: Supports advanced discount rules and validation logic
- **Location Model**: Geographic hierarchy for multi-region tax and shipping calculations

### 🗄️ Database Abstraction Layer

- **Consistent Data Access**: Uses Fluent Cart's Database class for all database operations
- **Query Optimization**: Leverages Fluent Cart's optimized query patterns
- **Transaction Management**: Ensures data consistency with proper rollback capabilities
- **Security**: Inherits Fluent Cart's SQL injection prevention and sanitization

### 🧠 Business Logic Compliance

- **Validation Rules**: Enforces Fluent Cart's data validation and business rules
- **Relationship Integrity**: Maintains proper foreign key relationships and dependencies
- **State Management**: Respects Fluent Cart's object states and lifecycle management
- **Event System**: Integrates with Fluent Cart's action/filter hooks for extensibility

### 🏷️ Advanced Meta Data Systems

- **Order Item Meta**: Stores detailed line item information and customizations
- **Product Meta**: Handles additional product specifications and attributes
- **Customer Meta**: Manages extended customer information and preferences
- **Dynamic Attributes**: Creates and manages product attribute systems automatically

## 🎨 Design Patterns & Best Practices

StoreSeeder implements proven design patterns to ensure maintainability, extensibility, and code quality.

### 📋 Abstract Base Classes

The plugin uses abstract base classes to enforce consistency and reduce code duplication:

#### `Generator` Abstract Class

```php
abstract class Generator {
    protected function validate_dependencies(): bool;
    protected function prepare_generation_data(array $params): array;
    abstract protected function generate_single_item(array $params): array;
    protected function post_generation_cleanup(): void;

    public function generate(array $params): array {
        // Template method pattern implementation
    }
}
```

**Key Features:**

- **Dependency Validation**: Ensures required data exists before generation
- **Parameter Preparation**: Standardizes input processing and validation
- **Single Item Generation**: Abstract method for specific generator logic
- **Cleanup Operations**: Post-generation cleanup and optimization

#### `Controller` Abstract Class

```php
abstract class Controller extends WP_REST_Controller {
    protected function validate_request_params(WP_REST_Request $request): array;
    protected function prepare_response_data(array $data): array;
    abstract protected function get_generator_instance(): Generator;

    public function generate_items(WP_REST_Request $request): WP_REST_Response {
        // Standardized REST API handling
    }
}
```

**Key Features:**

- **Parameter Validation**: Comprehensive input sanitization and validation
- **Response Formatting**: Consistent API response structure
- **Error Handling**: Standardized error responses with proper HTTP status codes
- **Generator Integration**: Clean separation between API and generation logic

### 🏗️ Architectural Patterns

#### Template Method Pattern

All generators follow a consistent workflow:

1. **Validate Dependencies** → Check for required data
2. **Prepare Parameters** → Process and validate input
3. **Generate Data** → Create realistic test data
4. **Post-Processing** → Apply business rules and relationships
5. **Cleanup** → Optimize and finalize data

#### Factory Pattern

Dynamic generator instantiation based on type:

```php
class Generator_Factory {
    public static function create(string $type): Generator {
        return match($type) {
            'product' => new Product_Generator(),
            'customer' => new Customer_Generator(),
            // ... other generators
        };
    }
}
```

#### Strategy Pattern

Configurable generation strategies for different scenarios:

- **Realistic Mode**: Production-like data with business logic
- **Development Mode**: Simplified data for quick testing
- **Stress Test Mode**: Large datasets for performance testing

#### Observer Pattern

Event-driven architecture for extensibility:

- **Generation Hooks**: `storeseeder_before_generation`
- **Progress Tracking**: `storeseeder_generation_progress`
- **Cleanup Hooks**: `storeseeder_after_generation`

## ⚛️ Modern Frontend Architecture

The frontend is built with React 18 and React Router v7, providing a modern, maintainable, and performant user interface.

### 🚦 React Router v7 Implementation

StoreSeeder uses React Router v7's data router for optimal WordPress admin integration:

#### Router Configuration

```javascript
const router = createHashRouter([
  {
    path: "/",
    element: <RootLayout />,
    children: [
      {
        index: true,
        element: <HomePage />,
      },
      {
        path: "generator/:type",
        element: <GeneratorPage />,
        loader: async ({ params }) => {
          // Data loading for generator configuration
          return loadGeneratorConfig(params.type);
        },
      },
    ],
  },
]);
```

**Key Benefits:**

- **Hash-Based Routing**: Compatible with WordPress admin's URL structure
- **Data Loading**: Pre-load generator configurations and dependencies
- **Error Boundaries**: Graceful error handling for failed data loads
- **Code Splitting**: Automatic route-based code splitting for performance

### 🧩 Component Architecture

#### Page Components (`src/components/Pages/`)

Route-focused components that handle specific URLs and layouts:

- **`RootLayout.tsx`** — application wrapper, WordPress admin integration
- **`HomePage.tsx`** — dashboard: stat cards, recent activity, generator grid
- **`GeneratorPage.tsx`** — one generator: config column, live preview, run bar
- **`SettingsPage.tsx`** — generation defaults, run history, sample data
- **`PluginsPage.tsx`** — the author's other plugins

#### Generator Components (`src/components/generator/`)

- **`ConfigColumn.tsx`** — header, dependency notes, the target-platform prompt, and the
  field sections derived from the generator's parameter schema
- **`FieldSection.tsx`** and **`fields/`** — the schema-driven controls: `Chips`,
  `FieldSelect`, `NumberField`, `RangeField`, `Stepper`, `TextField`, `Toggle`
- **`PreviewTable.tsx`** — debounced, read-only rows from the `/preview` route
- **`RunBar.tsx`** — count, seed, metadata toggle, and the run actions

There is no shared generator component. Every generator renders from its parameter schema
through `lib/fieldsFromSchema.ts`, so adding one needs no new React.

#### Component Communication Flow

```
User Action → Page Component → Generator Component → REST API → PHP Controller → Generator → Database
                      ↓
              Real-time Feedback ← Progress Updates ← Generation Status
```

### 🎨 Styling & Theming

#### Tailwind CSS Integration

- **WordPress Admin Colors**: Automatic adaptation to user's color scheme
- **CSS Variables**: Dynamic theming with WordPress admin color integration
- **Responsive Design**: Mobile-first approach with WordPress breakpoints
- **Component Library**: Consistent design system across all components

#### Color Scheme Integration

```css
:root {
  --wp-admin-color-primary: #2271b1;
  --wp-admin-color-secondary: #135e96;
  /* Additional WordPress admin colors */
}

.generator-button {
  background-color: var(--wp-admin-color-primary);
  border-color: var(--wp-admin-color-secondary);
}
```

### 🔄 State Management

#### Local Component State

- **Parameter State**: Complex nested objects for generator configuration
- **Progress State**: Real-time generation progress and status updates
- **Validation State**: Form validation and error handling

#### Data Flow Architecture

1. **User Input** → Component state updates
2. **Validation** → Client-side parameter validation
3. **API Request** → REST API call with validated parameters
4. **Server Processing** → PHP validation and generation
5. **Response Handling** → UI updates with results or errors

## ⚡ Performance Optimization

StoreSeeder is designed for high-performance data generation, even with large datasets and complex relationships.

### 📊 Batch Processing Architecture

#### Intelligent Chunking

- **Memory-Efficient Processing**: Processes data in configurable chunks to prevent memory exhaustion
- **Progress Tracking**: Real-time progress updates with resumable operations
- **Error Recovery**: Failed batches can be retried without restarting the entire process

#### Configuration Options

```javascript
const generationConfig = {
  batch_size: 50, // Items per batch
  memory_limit: "256M", // PHP memory limit monitoring
  timeout_protection: true, // Automatic timeout handling
  progress_callback: (progress) => updateUI(progress),
};
```

### 🗄️ Database Optimization

#### Query Optimization Strategies

- **Prepared Statements**: All database queries use prepared statements for security and performance
- **Bulk Inserts**: Multiple records inserted in single transactions where possible
- **Index Utilization**: Leverages existing Fluent Cart database indexes
- **Connection Pooling**: Efficient database connection management

#### Transaction Management

```php
$database->transaction(function() use ($items) {
    foreach ($items as $item) {
        $this->insert_item($item);
        $this->update_relationships($item);
    }
}); // Automatic rollback on failure
```

### 🚀 Memory Management

#### Garbage Collection Optimization

- **Object Cleanup**: Explicit cleanup of large objects after processing
- **Memory Monitoring**: Tracks memory usage and triggers cleanup when approaching limits
- **Streaming Processing**: Processes large datasets without loading everything into memory

#### Resource Management

- **File Handle Management**: Proper opening/closing of file resources
- **Cache Invalidation**: Strategic cache clearing to prevent memory bloat
- **Temporary Data Cleanup**: Automatic removal of temporary generation data

### 📈 Caching Strategies

#### Multi-Level Caching

- **Object Cache**: WordPress object cache for frequently accessed data
- **Transient Cache**: Temporary caching for generation session data
- **Dependency Cache**: Cached validation of data dependencies and relationships

#### Cache Invalidation

- **Smart Invalidation**: Only clears relevant cache entries after generation
- **Dependency Tracking**: Tracks which cache entries depend on generated data
- **Performance Monitoring**: Cache hit/miss ratios for optimization

### 🔧 Advanced Optimizations

#### Algorithm Optimizations

- **Relationship Pre-computation**: Calculates complex relationships before generation
- **Data Normalization**: Reuses common data patterns to reduce processing
- **Parallel Processing**: Utilizes WordPress background processing where available

#### Monitoring & Profiling

- **Performance Metrics**: Tracks generation speed and resource usage
- **Bottleneck Identification**: Identifies slow operations for optimization
- **Scalability Testing**: Validates performance with increasing dataset sizes

### 📊 Performance Benchmarks

| Dataset Size | Generation Time | Memory Usage | CPU Usage |
| ------------ | --------------- | ------------ | --------- |
| 100 items    | < 5 seconds     | < 32MB       | < 10%     |
| 1,000 items  | < 30 seconds    | < 128MB      | < 25%     |
| 10,000 items | < 5 minutes     | < 512MB      | < 50%     |

\*Benchmarks performed on standard WordPress hosting with PHP 8.0+