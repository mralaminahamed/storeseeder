# Usage Guide

## Getting Started

Fluent Cart FakerPress provides an intuitive interface for generating realistic test data for your Fluent Cart store. This guide covers basic usage, advanced features, and best practices.

## Admin Interface

### Accessing the Interface

1. Log into your WordPress admin dashboard
2. Navigate to **FC FakerPress** in the left sidebar
3. The main interface will load with tabbed navigation

### Interface Overview

- **Navigation Tabs**: Switch between different generators (Products, Customers, etc.)
- **Parameter Panel**: Configure generation settings
- **Results Panel**: View generation progress and results
- **Action Buttons**: Generate data or reset forms

## Basic Usage

### Generating Products

1. **Select Generator**: Click the **Products** tab
2. **Configure Parameters**:
   - **Count**: Number of products to generate (1-100)
   - **Product Type**: Simple, variable, or mixed
   - **Price Range**: Minimum and maximum prices
   - **Categories**: Enable/disable category assignment
3. **Generate**: Click **Generate Products**
4. **Monitor Progress**: Watch the progress indicator
5. **Review Results**: Check generated product IDs and links

### Generating Customers

1. **Select Generator**: Click the **Customers** tab
2. **Configure Parameters**:
   - **Count**: Number of customers to generate (1-50)
   - **Customer Type**: Individual or business
   - **Regions**: Geographic distribution
   - **Purchase History**: Include order history
3. **Generate**: Click **Generate Customers**
4. **Verify**: Check customer profiles in Fluent Cart

## Advanced Features

### Parameter Dependencies

Some parameters only appear when related options are enabled:
- **Variations**: Only shown when Product Type includes "variable"
- **Shipping**: Only available for physical products
- **Tax Settings**: Depends on WooCommerce tax configuration

### Batch Generation

Generate multiple data types in sequence:

1. Generate foundational data first (Customers)
2. Generate dependent data (Orders)
3. Use generated IDs for relationships

### Custom Configuration

Save frequently used configurations:
- Parameter presets for different scenarios
- Default values for common use cases
- Template configurations for team sharing

## REST API Usage

### Authentication

All API requests require authentication:

```javascript
const response = await fetch('/wp-json/fluent-cart-fakerpress/v1/products/generate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    count: 10,
    product_type: 'simple'
  })
});
```

### Available Endpoints

#### Products
```http
POST /wp-json/fluent-cart-fakerpress/v1/products/generate
```

Parameters:
- `count` (integer, 1-100): Number of products
- `product_type` (string): "simple", "variable", or "mixed"
- `price_min` (float): Minimum price
- `price_max` (float): Maximum price

#### Customers
```http
POST /wp-json/fluent-cart-fakerpress/v1/customers/generate
```

Parameters:
- `count` (integer, 1-50): Number of customers
- `customer_type` (string): "individual" or "business"
- `regions` (array): Geographic regions

### Response Format

```json
{
  "success": true,
  "data": {
    "generated": 10,
    "ids": [123, 124, 125],
    "message": "Products generated successfully"
  }
}
```

## Best Practices

### Development Workflow

1. **Start Small**: Generate small datasets first (1-5 items)
2. **Test Incrementally**: Verify each data type before scaling up
3. **Use Realistic Data**: Configure parameters for authentic scenarios
4. **Document Changes**: Track what data was generated for cleanup

### Performance Optimization

- **Batch Size**: Generate in smaller batches for better performance
- **Memory Limits**: Monitor PHP memory usage for large datasets
- **Database**: Ensure adequate database resources
- **Cleanup**: Remove test data regularly to maintain performance

### Data Integrity

- **Relationships**: Generate dependent data in correct order
- **Validation**: Use Fluent Cart's built-in validation
- **Consistency**: Maintain realistic data relationships
- **Cleanup**: Remove test data before going live

## Troubleshooting

### Common Issues

**Generation Fails Silently:**
- Check browser console for JavaScript errors
- Verify REST API endpoints are accessible
- Confirm Fluent Cart plugin is active

**Slow Performance:**
- Reduce batch sizes
- Check server resources
- Disable other plugins temporarily

**Data Not Appearing:**
- Clear Fluent Cart caches
- Check user permissions
- Verify database connections

### Debug Tools

Enable debug logging:

```php
// Add to wp-config.php
define( 'FLUENT_CART_FAKERPRESS_DEBUG', true );
```

Check logs in `/wp-content/debug.log` for detailed information.

## Examples

### E-commerce Demo Setup

```javascript
// Generate foundation data
await generateCustomers({ count: 20 });
await generateProducts({ count: 50, categories: true });

// Generate transactional data
await generateOrders({
  count: 100,
  include_coupons: true,
  date_range: 'last_30_days'
});
```

### Development Testing

```javascript
// Quick test data
await generateProducts({ count: 3, product_type: 'simple' });
await generateCustomers({ count: 2 });

// Performance testing
await generateOrders({ count: 1000, batch_size: 50 });
```

## Support

- **Documentation**: [GitHub Wiki](https://github.com/mralaminahamed/fluent-cart-fakerpress/wiki)
- **Issues**: [GitHub Issues](https://github.com/mralaminahamed/fluent-cart-fakerpress/issues)
- **Forums**: WordPress.org support forums