# Shop Billing System

A comprehensive web-based shop billing system built with PHP, MySQL, HTML, CSS, and JavaScript. This system provides digital solutions for managing day-to-day sales, billing, and product records, replacing manual billing processes with a fast and efficient system.

## 🚀 Features

### Admin Features
- **Dashboard**: Overview with statistics, recent bills, and low stock alerts
- **Product Management**: Add, edit, delete products with categories and stock management
- **Category Management**: Organize products into logical categories
- **User Management**: Create and manage cashier accounts, reset passwords
- **Sales Reports**: Comprehensive reports with filters (date, category, cashier)
- **Inventory Management**: Track stock levels and low stock alerts
- **Database Backup**: Backup and restore functionality

### Cashier Features
- **Dashboard**: Personal statistics and recent activity
- **Billing System**: Create bills, add products, calculate totals
- **Bill Management**: View, search, and print bills
- **Stock Integration**: Real-time stock updates after billing
- **Print Bills**: Professional bill printing with shop branding

### System Features
- **Secure Authentication**: Role-based access control (Admin/Cashier)
- **Responsive Design**: Modern, mobile-friendly interface
- **Real-time Updates**: Live stock management and calculations
- **Professional Bills**: Customizable bill templates with shop details
- **Data Export**: Export reports in various formats
- **Search & Filter**: Advanced search capabilities for bills and products

## 🛠️ Technology Stack

- **Frontend**: HTML, CSS3, JavaScript 
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **UI Framework**: Custom CSS with Font Awesome icons
- **Responsive**: Mobile-first design approach

## 📋 Requirements

- **Web Server**: Apache/mysql with PHP support
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Browser**: Modern browsers with JavaScript enabled(Brave)
- **Extensions**: PHP PDO, MySQL extensions

## 🚀 Installation

### 1. Clone/Download the Project
```bash
git clone <repository-url>
cd shop-billing-system
```

### 2. Database Setup
1. Create a MySQL database named `shop_billing_system`
2. Import the database schema:
   ```bash
   mysql -u root -p shop_billing_system < setup/database.sql
   ```
   Or use phpMyAdmin to import the SQL file.

### 3. Configure Database Connection
Edit `config/database.php` with your database credentials:
```php
$host = 'localhost';
$dbname = 'shop_billing_system';
$username = 'your_username';
$password = 'your_password';
```

### 4. Web Server Configuration
- Place the project in your web server's document root
- Ensure PHP has write permissions for session management
- Configure your web server to serve the application

### 5. Access the Application
- Open your browser and navigate to the project URL
- Default admin credentials:
  - **Username**: `admin`
  - **Password**: `admin123`

## 📁 Project Structure

```
shop-billing-system/
├── admin/                 # Admin panel files
│   ├── dashboard.php     # Admin dashboard
│   ├── products.php      # Product management
│   ├── categories.php    # Category management
│   ├── users.php         # User management
│   ├── reports.php       # Sales reports
│   ├── inventory.php     # Inventory management
│   └── backup.php        # Database backup
├── cashier/              # Cashier panel files
│   ├── dashboard.php     # Cashier dashboard
│   ├── billing.php       # Billing system
│   ├── bills.php         # Bill management
│   ├── search.php        # Bill search
│   ├── view_bill.php     # Bill view/print
│   └── profile.php       # User profile
├── assets/               # Static assets
│   └── css/
│       └── style.css     # Main stylesheet
├── config/               # Configuration files
│   └── database.php      # Database connection
├── setup/                # Setup files
│   └── database.sql      # Database schema
├── index.php             # Main entry point
├── logout.php            # Logout functionality
└── README.md             # This file
```

## 🔐 Default Users

After installation, the system comes with:

### Admin User
- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator

### Sample Data
- **Categories**: Electronics, Clothing, Food & Beverages, Home & Garden, Books
- **Products**: Sample products in each category with realistic prices and stock

## 📊 Database Schema

### Core Tables
- **users**: User accounts (admin/cashier)
- **categories**: Product categories
- **products**: Product information and stock
- **bills**: Bill headers and totals
- **bill_items**: Individual items in bills

### Key Relationships
- Products belong to categories
- Bills are created by cashiers
- Bill items link products to bills
- Stock is automatically updated after billing

## 🎯 Usage Guide

### For Administrators
1. **Login** with admin credentials
2. **Manage Products**: Add, edit, delete products and categories
3. **Create Cashier Accounts**: Set up user accounts for staff
4. **Monitor Sales**: View reports and analytics
5. **Track Inventory**: Monitor stock levels and alerts

### For Cashiers
1. **Login** with cashier credentials
2. **Create Bills**: Add products, set quantities, apply discounts
3. **Process Payments**: Choose payment method and finalize bills
4. **Print Bills**: Generate professional receipts for customers
5. **View History**: Access previous bills and customer information

## 🔧 Customization

### Shop Details
Edit shop information in `cashier/view_bill.php`:
- Shop name and logo
- Address and contact details
- Tax rates and business policies

### Styling
Modify `assets/css/style.css` to:
- Change color schemes
- Adjust layouts and spacing
- Customize fonts and typography

### Bill Template
Customize bill appearance in `cashier/view_bill.php`:
- Header design
- Item layout
- Footer information

## 📱 Responsive Design

The system is built with a mobile-first approach:
- Responsive grid layouts
- Touch-friendly interfaces
- Optimized for all screen sizes
- Print-friendly bill layouts

## 🔒 Security Features

- **Session Management**: Secure PHP sessions
- **Password Hashing**: Bcrypt password encryption
- **SQL Injection Protection**: Prepared statements
- **XSS Prevention**: Output escaping
- **Role-based Access**: Admin/Cashier separation

## 📈 Reporting & Analytics

### Available Reports
- **Sales Summary**: Total bills, revenue, averages
- **Product Performance**: Top-selling products
- **Category Analysis**: Sales by category
- **Cashier Performance**: Individual staff metrics
- **Daily Trends**: Revenue and bill trends over time

### Export Options
- **Print Reports**: Direct printing from browser
- **Data Export**: CSV/Excel export (planned feature)
- **Chart Visualization**: Interactive charts with Chart.js

## 🚀 Performance Features

- **Optimized Queries**: Efficient database queries
- **Caching**: Session-based caching
- **Responsive UI**: Fast loading interfaces
- **Auto-refresh**: Real-time dashboard updates

## 🐛 Troubleshooting

### Common Issues
1. **Database Connection Error**: Check database credentials in `config/database.php`
2. **Permission Denied**: Ensure web server has read/write access
3. **Session Issues**: Check PHP session configuration
4. **Page Not Found**: Verify web server configuration and URL rewriting

### Debug Mode
Enable error reporting in PHP for development:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🔄 Updates & Maintenance

### Regular Tasks
- **Database Backups**: Use admin backup functionality
- **Stock Updates**: Monitor and update product stock
- **User Management**: Review and update cashier accounts
- **Report Generation**: Regular sales analysis

### System Updates
- Keep PHP and MySQL versions updated
- Regular security patches
- Backup before major updates

## 📞 Support

For technical support or feature requests:
- **Email**: support@shopbilling.com
- **Documentation**: Check this README and inline code comments
- **Community**: Join our user community for tips and tricks

## 📄 License

This project is open-source and available under the MIT License.

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📝 Changelog

### Version 1.0.0
- Initial release
- Complete admin and cashier functionality
- Responsive design
- Basic reporting system
- Bill generation and printing

---

**Thank you for choosing Shop Billing System!** 🎉

This system is designed to streamline your business operations and provide a professional billing experience for your customers.
