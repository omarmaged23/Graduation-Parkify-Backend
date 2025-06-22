# 🚗 Parkify Backend

**Parkify** is a smart parking system that combines **AI**, **IoT**, and modern web/mobile technologies to optimize parking in private garages. This repository includes the **Laravel-based backend**, built with **Octane + FrankenPHP** for ultra-fast response times and high concurrency.
---
## 🎥 Demo

See Parkify in action:  
👉 [Watch Demo Video](https://drive.google.com/drive/u/0/folders/11eh6QYlDj93tCQoBidV0Z55gNJsBqdSG)

---
## ⚡ Tech Stack

- **Laravel 10+**
- **Laravel Octane + FrankenPHP**
- **MySQL**
- **MQTT (via HiveMQ)**
- **REST APIs (Sanctum Auth)**
- **ESP32 Integration**
- **Paymob Payment Gateway**
- **Twilio SMS API**

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
- Real-time availability display (public + reserved spots)
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
- Real-time light/servo control
- Displaying LCD messages on entry and exit gates

---

## 📲 Twilio Integration – SMS Notifications

We use **Twilio** to send **real-time SMS alerts** for critical events:
- 🚨 **Insufficient Balance** – The user receives an SMS if their balance is too low during exit.
- ✅ **Payment Confirmation** – After a successful recharge via Paymob, a confirmation SMS is sent to the user.

---

## 🚗 Entry/Exit Flow – User vs. Guest

### 🔐 User Entry
- License plate is detected by ESP32 camera → verified by AI (YOLOv8 + OCR)
- If reservation is valid → blocker unlocks via MQTT → entry allowed

### 🚪 User Exit
- Backend calculates parking fee and deducts it directly from the user’s balance
- If balance is sufficient → gate opens automatically
- If not → SMS alert via Twilio prompts the user to recharge

### 🧾 Guest Entry
- Public spot availability is checked in real-time
- If available and within allowed guest capacity → entry is granted

### 📤 Guest Exit
- Backend generates a **QR code invoice** displayed on the screen
- Guest scans and pays via Paymob payment gateway
- Upon successful payment → exit gate opens automatically

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
## Setup your paymob,twilio and cloud storage api keys before proceeding
php artisan key:generate
php artisan migrate --seed
php artisan octane:start --server=frankenphp
