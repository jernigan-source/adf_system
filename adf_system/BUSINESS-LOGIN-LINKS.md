# Business-Specific Login Links

## Overview
Sistem login sekarang menggunakan link khusus per bisnis untuk meningkatkan keamanan dan user experience. Tidak ada lagi dropdown yang menampilkan semua bisnis kepada semua user.

## How It Works

### 1. Direct Business Access
User mendapatkan link spesifik untuk business mereka:

**Narayana Hotel Staff:**
```
http://localhost:8081/adf_system/login.php?biz=narayana-hotel
```

**Ben's Cafe Staff:**
```
http://localhost:8081/adf_system/login.php?biz=bens-cafe
```

### 2. Generic Login
User juga bisa login tanpa parameter business:
```
http://localhost:8081/adf_system/login.php
```

Sistem akan otomatis mendeteksi business apa yang bisa diakses user berdasarkan permissions di master database.

### 3. Auto-Detection Logic

#### User dengan 1 Business Access:
- Login berhasil → Langsung masuk ke business tersebut
- Tidak perlu pilih-pilih lagi

**Contoh:** Sandra login → Otomatis masuk ke Narayana Hotel

#### User dengan Multiple Business Access:
- Login berhasil → Redirect ke `select-business.php`
- User pilih business yang ingin diakses
- Validasi permission sebelum masuk

**Contoh:** Admin login → Bisa pilih antara Narayana Hotel atau Ben's Cafe

### 4. Security Validation

Setiap login attempt divalidasi dengan:
1. Username/password verification
2. Check user exists in master database
3. Query business permissions from `user_menu_permissions` table
4. Validate business access before allowing entry

**If user tries to access business without permission:**
- Error message: "Anda tidak punya akses ke bisnis tersebut!"
- Auto logout to prevent unauthorized access

## Database Structure

### Master Database: `adf_system`

#### Table: `user_menu_permissions`
```sql
user_id         (FK to users.id)
business_id     (FK to businesses.id)
menu_id         (FK to menu_items.id)
can_view        (TINYINT 1/0)
can_create      (TINYINT 1/0)
can_edit        (TINYINT 1/0)
can_delete      (TINYINT 1/0)
```

**Example:**
```sql
-- Sandra has 8 menu permissions for Narayana Hotel only
SELECT COUNT(*) FROM user_menu_permissions 
WHERE user_id = 2 AND business_id = 1;
-- Returns: 8

-- Sandra has NO permissions for Ben's Cafe
SELECT COUNT(*) FROM user_menu_permissions 
WHERE user_id = 2 AND business_id = 2;
-- Returns: 0
```

## User Management

### Creating New Staff User

1. **Register in Business Database:**
   - Go to Developer Panel → Business Users
   - Select business → Add user
   - Set username, password, role

2. **Sync to Master Database:**
   - Run `sync-business-users.php`
   - Creates user record in master
   - Creates `user_menu_permissions` entries
   - Creates `user_business_assignment` entry

3. **Share Login Link:**
   - Give staff the business-specific link
   - Example: `login.php?biz=narayana-hotel`

### Granting Multiple Business Access

To give user access to multiple businesses:

```sql
-- 1. Assign user to second business
INSERT INTO user_business_assignment (user_id, business_id, assigned_at)
VALUES (2, 2, NOW());

-- 2. Grant menu permissions for second business
INSERT INTO user_menu_permissions 
(user_id, business_id, menu_id, can_view, can_create, can_edit, can_delete)
SELECT 2, 2, menu_id, can_view, can_create, can_edit, can_delete
FROM user_menu_permissions
WHERE user_id = 2 AND business_id = 1;
```

## File Structure

### Core Login Files

| File | Purpose |
|------|---------|
| `login.php` | Main login page with business detection |
| `select-business.php` | Business selection for multi-access users |
| `api/switch-business.php` | Handle business switching via AJAX |
| `developer-access.php` | Developer quick-access (no login required) |

### Key Functions in `login.php`

```php
// 1. Accept business parameter
$forcedBusiness = isset($_GET['biz']) ? sanitize($_GET['biz']) : null;

// 2. Query user's accessible businesses
$bizStmt = $masterPdo->prepare("
    SELECT DISTINCT b.id, b.business_code, b.business_name
    FROM businesses b
    JOIN user_menu_permissions p ON b.id = p.business_id
    WHERE p.user_id = ?
");

// 3. Route based on access count
if (count($userBusinesses) === 1) {
    // Auto-login to single business
    setActiveBusinessId($businessId);
    redirect('index.php');
} else {
    // Show selection page
    redirect('select-business.php');
}
```

## Security Benefits

### Before (Dropdown System):
- ❌ All users see all businesses in dropdown
- ❌ Confusing for single-business staff
- ❌ Security through obscurity (rely on permission check after selection)

### After (Link-Based System):
- ✅ Users only see what they can access
- ✅ Clean UX for single-business staff (no selection needed)
- ✅ Business-specific branding on login page
- ✅ Permission validation before AND after login

## Testing Scenarios

### Test 1: Sandra Login (Single Business)
```
URL: login.php?biz=narayana-hotel
Username: sandra
Password: sandra123

Expected:
1. Login successful
2. Auto-redirect to Narayana Hotel dashboard
3. No business selection page shown
```

### Test 2: Sandra Unauthorized Access
```
URL: login.php?biz=bens-cafe
Username: sandra
Password: sandra123

Expected:
1. Login fails
2. Error: "Anda tidak punya akses ke bisnis tersebut!"
3. Session destroyed
```

### Test 3: Admin Login (Multiple Businesses)
```
URL: login.php
Username: admin
Password: admin123

Expected:
1. Login successful
2. Redirect to select-business.php
3. Shows: Narayana Hotel, Ben's Cafe
4. User selects business
5. Redirect to dashboard
```

### Test 4: Generic Login Single-Business User
```
URL: login.php
Username: sandra
Password: sandra123

Expected:
1. Login successful
2. Auto-detect: Sandra has only Narayana Hotel access
3. Auto-login to Narayana Hotel
4. No selection page shown
```

## URL Patterns

### Production URLs

Replace `localhost:8081/adf_system` with your production domain:

**Narayana Hotel:**
```
https://yourdomain.com/login.php?biz=narayana-hotel
```

**Ben's Cafe:**
```
https://yourdomain.com/login.php?biz=bens-cafe
```

**Generic:**
```
https://yourdomain.com/login.php
```

## Adding New Business

When adding new business to system:

1. **Update `login.php` Display Map:**
```php
$businessMap = [
    'narayana-hotel' => [...],
    'bens-cafe' => [...],
    'new-business' => [
        'icon' => '🏪',
        'name' => 'New Business Name',
        'subtitle' => 'Location',
        'db_name' => 'adf_newbusiness'
    ]
];
```

2. **Create Business in Master DB:**
```sql
INSERT INTO businesses (business_code, business_name, database_name)
VALUES ('NEWBUSINESS', 'New Business Name', 'adf_newbusiness');
```

3. **Assign Menus to Business:**
```sql
INSERT INTO business_menu_config (business_id, menu_id)
SELECT <new_business_id>, id FROM menu_items;
```

4. **Share Link:**
```
login.php?biz=new-business
```

## Support & Troubleshooting

### User Can't Login
1. Check user exists in master database
2. Verify `user_menu_permissions` has entries for user + business
3. Check `user_business_assignment` table

### User Sees Wrong Business
1. Verify URL has correct `?biz=` parameter
2. Check business_code in `businesses` table matches URL param
3. Validate business ID in permissions table

### Developer Quick-Access Not Working
- Use `developer-access.php?business=narayana-hotel`
- Developer access bypasses all permission checks
- Only accessible to role_id = 5 (Developer)

## Change Log

### Version 2.0 (Current)
- ✅ Removed business dropdown from login page
- ✅ Implemented URL-based business detection
- ✅ Auto-login for single-business users
- ✅ Business selection page for multi-access users
- ✅ Dynamic login page branding based on business
- ✅ Enhanced security with permission validation

### Version 1.0 (Previous)
- ❌ Dropdown showing all businesses
- ❌ Manual business selection required
- ❌ Permission check only after login
