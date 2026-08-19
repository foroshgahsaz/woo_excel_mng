# راهنمای سریع شروع

## روش 1: استفاده مستقیم از CSV (ساده‌ترین روش)

### برای محصولات:
1. فایل `products-sample.csv` را در Excel باز کنید
2. File → Save As
3. فرمت را به **Excel Workbook (.xlsx)** تغییر دهید
4. ذخیره کنید
5. فایل `.xlsx` را در افزونه آپلود کنید

### برای شهرها:
1. فایل `shipping-sample.csv` را در Excel باز کنید
2. File → Save As
3. فرمت را به **Excel Workbook (.xlsx)** تغییر دهید
4. ذخیره کنید
5. فایل `.xlsx` را در افزونه آپلود کنید

---

## پیش‌نیاز نصب Composer (XAMPP / Windows)

قبل از `composer install` این extensionها باید در PHP فعال باشند:

- `ext-zip` — خواندن فایل `.xlsx`
- `ext-gd` — پیش‌نیاز PhpSpreadsheet

### فعال‌سازی در XAMPP

1. فایل `C:\xampp\php\php.ini` را باز کنید
2. این خطوط را پیدا کنید و `;` ابتدای آن‌ها را بردارید:

```ini
extension=gd
extension=zip
```

3. XAMPP Control Panel → Apache را Stop و Start کنید
4. در CMD بررسی کنید:

```bash
php -m | findstr /i "gd zip"
```

5. سپس در پوشه افزونه:

```bash
composer install
```

اگر فقط برای تست موقت می‌خواهید نصب کنید:

```bash
composer install --ignore-platform-req=ext-gd
```

---

## روش 2: تبدیل خودکار با PHP (نیاز به PhpSpreadsheet)

اگر PhpSpreadsheet نصب شده باشد:

```bash
cd samples
php convert-to-excel.php
```

یا در مرورگر:
```
http://yoursite.com/wp-content/plugins/woo_excel_mng/samples/convert-to-excel.php
```

---

## روش 3: کپی از CSV به Excel

1. فایل CSV را در Notepad یا Excel باز کنید
2. تمام محتوا را کپی کنید (Ctrl+A, Ctrl+C)
3. یک فایل Excel جدید باز کنید
4. در سلول A1 کلیک کنید
5. Paste کنید (Ctrl+V)
6. ستون اول را به عنوان Header انتخاب کنید
7. ذخیره کنید

---

## نکات مهم:

✅ **نام ستون‌ها باید دقیقاً همان باشد که در فایل نمونه است**
✅ **فایل باید با encoding UTF-8 باشد**
✅ **هدر (ردیف اول) باید وجود داشته باشد**
✅ **ردیف‌های خالی نادیده گرفته می‌شوند**

---

## تست سریع:

### 1. آپلود محصولات:
- به **مدیریت فروشگاه → محصولات** بروید
- فایل Excel محصولات را آپلود کنید
- باید پیام موفقیت ببینید

### 2. آپلود شهرها:
- به **مدیریت فروشگاه → حمل‌ونقل** بروید
- فایل Excel شهرها را آپلود کنید
- باید جدول مسیرها را ببینید

### 3. تعریف فرمول:
- به **مدیریت فروشگاه → فرمول‌ها** بروید
- محصول "لول" را انتخاب کنید
- فرمول: `({length} * {thickness} * {meter} * 0.8) + {base_price}`
- ذخیره کنید

### 4. تست Front-end:
- به صفحه محصول "لول" بروید
- Variation انتخاب کنید
- متراژ وارد کنید
- قیمت محاسبه می‌شود!

---

**موفق باشید! 🎉**

