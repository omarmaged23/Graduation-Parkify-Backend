# 🚗 Parkify Backend

**Parkify** is a smart parking system that combines **AI**, **IoT**, and modern web/mobile technologies to optimize parking in private garages. This repository includes the **Laravel-based backend**, built with **Octane + FrankenPHP** for ultra-fast response times and high concurrency.

---

## ⚡ Tech Stack

- **Laravel 10+**
- **Laravel Octane + FrankenPHP**
- **MySQL**
- **MQTT (via HiveMQ)**
- **REST APIs (JWT Auth)**
- **ESP32 Integration**
- **Paymob Payment Gateway**

---

## 📱 Key Features

### 🧑‍💼 Admin Features
- Multi-branch management (create/edit/delete locations)
- Add/edit/delete parking spots
- ESP32-controlled spot assignment and activation
- Real-time analytics and reporting (downloadable)
- Manage all user accounts (disable, edit, delete)
- View detailed logs and parking/payment history
- Admin-controlled loyalty system (rewards, point config, refunds)
- Role-based permissions and secure login with tracking

### 🚗 User Features
- Account registration and secure login
- Vehicle registration + license plate linking
- Spot reservation and auto-cancellation
- Real-time availability map (public + reserved spots)
- Unlock spot blockers using mobile app (via MQTT)
- Live countdown + session tracking
- Payment via Visa, MasterCard, Meeza (via Paymob)
- Guest checkout via QR code at exit
- Earn points per session + redeem for discounts

---

## 📡 MQTT Integration

MQTT is used for **low-latency control** of ESP32 devices for:
- Spot updates
- Unlocking blockers
- Detecting overstays
- Real-time light/servo control

### Topics:
- `parking/spot/{id}/status`
- `parking/spot/{id}/unlock`
- `parking/spot/{id}/overstay`

---

## 🚀 Performance with Laravel Octane + FrankenPHP

Using **FrankenPHP** with **Laravel Octane**, we achieved:
- ⚡️ Ultra-fast response times
- 🔁 Concurrency without bottlenecks
- 🧠 Optimized real-time interactions (perfect for MQTT, AI callbacks)

---

## 🧪 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- MySQL
- MQTT Broker (e.g., HiveMQ)

### Installation

```bash
git clone https://github.com/your-username/parkify-backend.git
cd parkify-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan octane:start --server=frankenphp
