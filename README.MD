# 🚀 MultiStockSync Backend API

![multistock-logo](https://github.com/user-attachments/assets/031a2f15-8f48-4c25-98a8-6328c504919b)
# MultiStockSync Backend API Documentation

## 📋 Introduction
This documentation provides an overview of the API endpoints available in the Laravel backend for the Multi-Stock-Sync-Back project. Each endpoint is described with its purpose, request method, and parameters.

## Base URL
The base URL for all API endpoints is:

```
http://your-domain.com/api
```

## 🛠 Prerequisites

Before getting started, ensure you have:
- PHP (version 8.1 or higher) 🐘
- Composer 📦
- Laravel Framework
- Git installed 🖥️

## 🚦 Quick Start Guide

### 1. Clone the Repository 📂
```bash
git clone https://github.com/AstronautMarkus/Multi-Stock-Sync-Back.git
```

### 2. Verify PHP and Composer 🕵️
```bash
php --version
composer --version
```

### 3. Install Dependencies 📦
```bash
composer install
```

## 🔧 4. Steps to Configure .env File

1. **Change name file**
   ```bash
   Navigate to the directory where the .env.example file is located.

   change name .env.example to .env
   ```

2. **Edit Database Settings** 
   ```env
   DB_CONNECTION=mysql
   DB_HOST=localhost        # Local server
   DB_PORT=3306             # Default MySQL port
   DB_DATABASE=MultiStock   # Your database name
   DB_USERNAME=su_usuario   # Your database username
   DB_PASSWORD=su_contraseña # Your database password
   ```

3. **Important Replacements**
   - `user_name`: Your actual MySQL username
   - `password`: Your actual MySQL password
   - `MultiStock`: Desired database name

💡 **Tip:** Ensure your MySQL server is running before configuring.

## 5. Install key migrate 📦
```bash
php artisan key:generate
```

### 6. Run migrations 📦
```bash
php artisan migrate
```

### 7. Start Development Server 🌐
```bash
php artisan serve
```

## 🌐 API Endpoints

Our API covers multiple domains:

[![API Docs](https://img.shields.io/badge/Documentation-API-orange?style=for-the-badge&logo=read-the-docs)](https://github.com/AstronautMarkus/Multi-Stock-Sync-Back/blob/main/Docs/MultiStockBack-MercadoLibre.md)


This documentation provides a starting point for understanding and using the API endpoints available in the Laravel backend. For more detailed information, please refer to the specific endpoint documentation or contact the development team.

## 📞 Support

For more information, please contact the development team or open an issue in the GitHub repository.

**Happy Coding!** 💻🚀