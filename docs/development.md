# 🛠️ Development Guide

Welcome to the StoreSeeder v2.0.0 development guide! This comprehensive resource will help you contribute effectively to the project, now featuring complete TypeScript support and parameter schema alignment.

## 🚀 Quick Development Setup

### Prerequisites

- **PHP**: 7.4+ (8.0+ recommended)
- **Node.js**: 16+ (18+ recommended for TypeScript)
- **Composer**: 2.0+
- **WordPress**: 5.0+ with Fluent Cart plugin
- **Git**: For version control
- **TypeScript**: 4.5+ (included with project dependencies)

### One-Command Setup

```bash
# Clone and setup in one go
git clone https://github.com/mralaminahamed/storeseeder.git
cd storeseeder
composer install && npm install && npm run build
```

### v2.0.0: TypeScript Migration

**All React components have been migrated to TypeScript (.tsx) for better type safety and developer experience.**

- **Type Definitions**: Comprehensive interfaces for all generator parameters
- **Parameter Validation**: Type-safe parameter schemas with proper validation
- **API Integration**: Strongly typed API responses and error handling
- **Build System**: Enhanced webpack configuration for TypeScript compilation

## 🏗️ Build System & Commands

### Development Workflow

```bash
# Start development server with hot reload
npm run start

# Production build (optimized for deployment)
npm run build

# Update packages
npm run packages-update
```

### Code Quality Assurance

```bash
# Full quality check suite
composer run lint         # PHP CodeSniffer (WordPress standards)
composer run analyse      # PHP static analysis (level 8)

# Auto-fix issues where possible
composer run format       # Auto-fix PHP code style

# Build and package management
npm run packages-update   # Update WordPress packages
```

### Testing Commands

```bash
# Run PHP unit tests
composer test

# Run with code coverage
composer test:coverage

# WordPress integration tests
phpunit
```

## 📋 Coding Standards & Quality

### PHP Standards (WordPress Coding Standards)

- **PSR-4 Autoloading**: Strict namespace and file structure compliance
- **WordPress Functions**: Use WordPress core functions over native PHP where possible
- **Security**: Nonce verification, input sanitization, and prepared statements
- **Documentation**: PHPDoc blocks for all classes, methods, and properties
- **Error Handling**: Proper exception handling with user-friendly messages

### JavaScript Standards (WordPress JavaScript Standards)

- **ES6+ Features**: Modern JavaScript with Babel transpilation
- **React Best Practices**: Functional components with hooks
- **Accessibility**: WCAG compliance with proper ARIA attributes
- **Performance**: Code splitting and lazy loading for optimal performance
- **WordPress Integration**: wp.i18n for internationalization

### CSS Standards (WordPress CSS Standards)

- **Tailwind CSS**: Utility-first approach with WordPress admin integration
- **BEM Methodology**: Block Element Modifier naming convention
- **CSS Variables**: WordPress admin color scheme integration
- **Responsive Design**: Mobile-first approach with WordPress breakpoints

## 🔧 Architecture & Development Patterns

### Generator Development Workflow

#### 1. Backend Generator Implementation

```php
<?php
namespace StoreSeeder\Generators;

class MyNewGenerator extends Generator {
    protected function validate_dependencies(): bool {
        // Check for required data
        return true;
    }

    protected function prepare_generation_data(array $params): array {
        // Process and validate parameters
        return $params;
    }

    protected function generate_single_item(array $params): array {
        // Generate single item logic
        return [
            'id' => 123,
            'name' => 'Generated Item',
            'created_at' => current_time('mysql'),
        ];
    }

    protected function post_generation_cleanup(): void {
        // Cleanup operations
        wp_cache_flush();
    }
}
```

#### 2. REST API Controller

```php
<?php
namespace StoreSeeder\Controllers;

class MyNew extends Controller {
    protected function validate_request_params(WP_REST_Request $request): array {
        // Parameter validation logic
        return $request->get_params();
    }

    protected function prepare_response_data(array $data): array {
        // Response formatting
        return [
            'success' => true,
            'data' => $data,
            'message' => __('Items generated successfully', 'storeseeder'),
        ];
    }

    protected function get_generator_instance(): Generator {
        return new MyNewGenerator();
    }
}
```

#### 3. Frontend Component (TypeScript)

```tsx
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

interface MyNewGeneratorParams {
    count: number;
    type: 'basic' | 'advanced';
    options: {
        enabled: boolean;
        settings: Record<string, any>;
    };
}

export default function MyNewGenerator() {
    const [params, setParams] = useState<MyNewGeneratorParams>({
        count: 10,
        type: 'basic',
        options: {
            enabled: true,
            settings: {},
        },
    });

    const [isGenerating, setIsGenerating] = useState(false);

    const handleGenerate = async () => {
        setIsGenerating(true);
        try {
            const response = await apiFetch({
                path: '/storeseeder/v1/my-new',
                method: 'POST',
                data: params,
            });

            // Handle success
            console.log('Generation completed:', response);
        } catch (error) {
            // Handle error
            console.error('Generation failed:', error);
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="space-y-6">
            <h3 className="text-lg font-medium">
                {__('My New Generator', 'storeseeder')}
            </h3>

            {/* Form controls */}
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        {__('Count', 'storeseeder')}
                    </label>
                    <input
                        type="number"
                        value={params.count}
                        onChange={(e) => setParams(prev => ({
                            ...prev,
                            count: parseInt(e.target.value) || 0
                        }))}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        {__('Type', 'storeseeder')}
                    </label>
                    <select
                        value={params.type}
                        onChange={(e) => setParams(prev => ({
                            ...prev,
                            type: e.target.value as 'basic' | 'advanced'
                        }))}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="basic">
                            {__('Basic', 'storeseeder')}
                        </option>
                        <option value="advanced">
                            {__('Advanced', 'storeseeder')}
                        </option>
                    </select>
                </div>
            </div>

            {/* Generate button */}
            <div className="flex justify-end">
                <button
                    onClick={handleGenerate}
                    disabled={isGenerating}
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                >
                    {isGenerating ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {__('Generating...', 'storeseeder')}
                        </>
                    ) : (
                        __('Generate Items', 'storeseeder')
                    )}
                </button>
            </div>
        </div>
    );
}
```

## 🧪 Testing Strategy

### Unit Testing

```php
<?php
use PHPUnit\Framework\TestCase;
use StoreSeeder\Generators\ProductGenerator;

class ProductGeneratorTest extends TestCase {
    private $generator;

    protected function setUp(): void {
        $this->generator = new ProductGenerator();
    }

    public function test_generate_single_product(): void {
        $params = [
            'product_type' => 'simple',
            'price_range' => ['min' => 10, 'max' => 100],
        ];

        $result = $this->generator->generate($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertGreaterThan(0, $result['id']);
    }
}
```

### Integration Testing

```php
public function test_rest_api_integration(): void {
    $admin_user = $this->create_admin_user();
    wp_set_current_user($admin_user);

    $request = $this->create_request('POST', '/storeseeder/v1/products', [
        'count' => 5,
        'product_type' => 'simple',
    ]);

    $response = rest_do_request($request);

    $this->assertEquals(200, $response->get_status());
    $this->assertArrayHasKey('success', $response->get_data());
    $this->assertTrue($response->get_data()['success']);
}
```

### Frontend Testing (Jest + React Testing Library)

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ProductGenerator from '../components/Generators/ProductGenerator';

test('generates products successfully', async () => {
    // Mock API
    global.fetch = jest.fn(() =>
        Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: { generated: 5 },
                message: 'Products generated successfully'
            })
        })
    );

    render(<ProductGenerator />);

    const generateButton = screen.getByRole('button', { name: /generate/i });
    fireEvent.click(generateButton);

    await waitFor(() => {
        expect(screen.getByText('Products generated successfully')).toBeInTheDocument();
    });
});
```

## 🚀 Deployment & Release Process

### Version Management

```bash
# Update version in package.json and composer.json
npm version patch  # or minor, major
composer update --lock

# Build production assets
npm run build

# Create release archive
composer run release
```

### WordPress.org Deployment

The project includes automated deployment workflows:

- **GitHub Actions**: Automatic deployment on tag creation
- **Asset Management**: Screenshots and banners automatically included
- **Version Sync**: Consistent versioning across all files

### Release Checklist

- [ ] Update version numbers in all files
- [ ] Update changelog with new features
- [ ] Run full test suite
- [ ] Build production assets
- [ ] Test plugin activation
- [ ] Verify WordPress.org compatibility
- [ ] Create GitHub release with assets

## 🔍 Debugging & Troubleshooting

### Common Development Issues

#### Build Failures

```bash
# Clear node_modules and rebuild
rm -rf node_modules package-lock.json
npm install

# Clear composer cache
composer clear-cache

# Check for TypeScript errors
npx tsc --noEmit
```

#### WordPress Integration Issues

```php
// Enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check plugin activation
if (!function_exists('storeseeder')) {
    error_log('StoreSeeder not loaded');
}
```

#### API Debugging

```javascript
// Enable API debugging in browser console
localStorage.setItem('debug', 'storeseeder:*');

// Log API requests
wp.apiFetch.use((options, next) => {
    console.log('API Request:', options);
    return next(options);
});
```

## 📚 Advanced Development Topics

### Custom Generator Development

1. **Extend Base Generator**: Create specialized generators for custom data types
2. **Parameter Schema**: Define comprehensive parameter validation schemas
3. **Database Integration**: Implement custom database operations and relationships
4. **WordPress Hooks**: Integrate with WordPress action/filter system

### Performance Optimization

- **Query Optimization**: Use WordPress database optimization techniques
- **Caching Strategy**: Implement appropriate caching for frequently accessed data
- **Memory Management**: Handle large datasets efficiently
- **Background Processing**: Use WordPress cron for long-running operations

### Security Best Practices

- **Input Validation**: Comprehensive parameter validation and sanitization
- **Capability Checks**: Proper WordPress capability verification
- **Nonce Protection**: CSRF protection for all forms and API calls
- **SQL Injection Prevention**: Prepared statements for all database queries

## 🤝 Contributing Guidelines

### Pull Request Process

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** changes (`git commit -m 'Add amazing feature'`)
4. **Push** to branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Code Review Checklist

- [ ] Code follows WordPress coding standards
- [ ] TypeScript types are properly defined
- [ ] Tests are included and passing
- [ ] Documentation is updated
- [ ] Security best practices are followed
- [ ] Performance impact is considered

### Commit Message Format

```
type(scope): description

[optional body]

[optional footer]
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## 📞 Support & Resources

- **GitHub Issues**: Bug reports and feature requests
- **WordPress.org Forums**: Community support
- **Documentation**: Comprehensive guides and API reference
- **Slack Channel**: Real-time developer discussions

---

*This development guide is continuously updated. Last updated: November 11, 2025*