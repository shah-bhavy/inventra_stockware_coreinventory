# 📦 CoreInventory – Inventory Management System

CoreInventory is a modular **Inventory Management System (IMS)** designed to digitize and streamline stock operations within a business.  
It replaces manual registers and spreadsheets with a centralized and real-time inventory tracking application.

---

# 🎯 Target Users

**Inventory Managers**
- Monitor inventory levels
- Manage incoming and outgoing stock

**Warehouse Staff**
- Perform stock transfers
- Handle picking and shelving
- Conduct stock counting

---

# ✨ Key Features

## 🔐 Authentication
- User Login / Signup
- OTP-based password reset
- Secure dashboard access

---

## 📊 Dashboard

The dashboard provides a quick overview of inventory operations with key metrics:

- Total Products in Stock
- Low Stock / Out-of-Stock Items
- Pending Receipts
- Pending Deliveries
- Scheduled Internal Transfers

Users can also filter data by:

- Document Type (Receipts / Delivery / Internal / Adjustments)
- Status (Draft, Waiting, Ready, Done, Canceled)
- Warehouse or Location
- Product Category

---

# 📦 Product Management

Users can create and manage products with the following details:

- Product Name
- SKU / Product Code
- Product Category
- Unit of Measure
- Initial Stock (optional)

The system also supports:

- Product stock tracking per location
- Product categories
- Reordering rules

---

# 🚚 Inventory Operations

The system supports four major inventory operations.

## 1️⃣ Receipts (Incoming Goods)

Used when products arrive from suppliers.

Process:
1. Create a new receipt
2. Add supplier and products
3. Enter received quantities
4. Validate receipt

Result:
Stock automatically increases.

Example:
Receive **50 units of Steel Rods → Stock +50**

---

## 2️⃣ Delivery Orders (Outgoing Goods)

Used when products are shipped to customers.

Process:
1. Pick items
2. Pack items
3. Validate delivery order

Result:
Stock automatically decreases.

Example:
Sales order for **10 chairs → Stock -10**

---

## 3️⃣ Internal Transfers

Move inventory between internal locations.

Examples:
- Main Warehouse → Production Floor
- Rack A → Rack B
- Warehouse 1 → Warehouse 2

The total stock remains the same but the **location of stock changes**.

All movements are recorded in the **inventory ledger**.

---

## 4️⃣ Inventory Adjustments

Used when physical stock does not match system records.

Steps:
1. Select product and location
2. Enter counted quantity
3. System updates stock automatically

Example:
3 damaged items → Stock **-3**

---

# ⚙️ Additional Features

- Low stock alerts
- Multi-warehouse support
- SKU search
- Smart filtering
- Complete stock movement history
- Inventory ledger tracking

---

# 🔄 Example Inventory Flow

Step 1 – Receive goods from vendor  
Receive **100 kg Steel → Stock +100**

Step 2 – Move to production rack  
Internal Transfer **Main Store → Production Rack**

Step 3 – Deliver finished goods  
Deliver **20 steel frames → Stock -20**

Step 4 – Adjust damaged items  
3 kg damaged → **Stock -3**

Every action is recorded in the **stock ledger for tracking and auditing**.

---

# 🧱 Suggested Project Structure
CoreInventory/
│
├── frontend/
│ ├── dashboard
│ ├── products
│ ├── operations
│ └── authentication
│
├── backend/
│ ├── controllers
│ ├── models
│ ├── routes
│ └── services
│
├── database/
│ └── schema.sql
│
├── docs/
│ └── system-design
│
└── README.md


---

# 🚀 Future Improvements

- Real-time stock updates
- Mobile responsive dashboard
- Barcode / QR code scanning
- Stock analytics and reporting
- Notification system for low inventory
