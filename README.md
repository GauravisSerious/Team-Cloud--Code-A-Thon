<<<<<<< HEAD
# LocalConnect-Code-A-Thon
LocalConnect! Connecting lives!
=======
# LocalConnect

LocalConnect is a cloud-based e-commerce and networking platform designed to empower small businesses and artisans by providing a digital marketplace where they can showcase their products, manage sales, and connect with other entrepreneurs.

## Features

- **User Authentication & Roles**: Secure login system with three user roles (Customer, Business Owner, Admin)
- **Business Profiles**: Businesses can create and manage their profiles
- **Product Management**: Add, edit, and delete products with images
- **Shopping Experience**: Browse, search, and filter products
- **Order Processing**: Place and manage orders with status tracking
- **Community Forum**: Discussion board for networking and collaboration
- **Admin Panel**: Comprehensive administrative tools
- **Responsive Design**: Mobile-friendly interface with dark mode

## Technology Stack

- **Frontend**: HTML, CSS, JavaScript, Bootstrap 5
- **Backend**: PHP
- **Database**: MySQL
- **Server**: XAMPP (for local testing), phpMyAdmin (database management)

## Installation

### Prerequisites

- [XAMPP](https://www.apachefriends.org/download.html) (or any PHP server with MySQL)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Steps to Install

1. **Clone the repository**

   ```
   git clone https://github.com/yourusername/localconnect.git
   ```

2. **Move to server directory**

   Move the cloned directory to your XAMPP `htdocs` folder or your server's web root directory.

3. **Start the servers**

   Start Apache and MySQL services from the XAMPP control panel.

4. **Run the installation script**

   Open your browser and navigate to:
   ```
   http://localhost/localconnect/install.php
   ```
   This will set up the database and initial configuration.

5. **Login as admin**

   Once installation is complete, you can login with:
   - Username: `admin`
   - Password: `admin123`

   **Important**: Change the default admin password after your first login.

## Directory Structure

```
localconnect/
├── admin/                   # Admin panel files
├── api/                     # API endpoints
├── assets/                  # Static assets
│   ├── css/                 # CSS files
│   ├── js/                  # JavaScript files
│   └── images/              # Images and uploads
├── auth/                    # Authentication files
├── business/                # Business owner dashboard
├── config/                  # Configuration files
├── database/                # Database schema
├── includes/                # Common includes
├── products/                # Product browsing and details
├── utils/                   # Utility functions
├── index.php                # Homepage
├── install.php              # Installation script
└── README.md                # This file
```

## User Roles

1. **Customer**
   - Browse and purchase products
   - View order history
   - Participate in forums

2. **Business Owner**
   - Manage business profile
   - Add and manage products
   - Process customer orders
   - Participate in forums

3. **Admin**
   - Manage all users, businesses, and products
   - View platform statistics
   - Moderate forum content

## Support and Contribution

For support or to contribute to the project, please contact us at [your-email@example.com](mailto:your-email@example.com).

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Bootstrap](https://getbootstrap.com/) for the responsive UI components
- [Font Awesome](https://fontawesome.com/) for icons
- All contributors who have helped build and improve LocalConnect 
>>>>>>> b01a5c1 (first commit)
