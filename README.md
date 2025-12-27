# Jules LearnPress WooCommerce Integration

**A free, lightweight plugin for seamless integration of LearnPress with WooCommerce**

[![WordPress](https://img.shields.io/badge/WordPress-%5E6.0-blue)](https://wordpress.org/)
[![LearnPress](https://img.shields.io/badge/LearnPress-Free%20Version-green)](https://thimpress.com/learnpress/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%5E8.0-purple)](https://woocommerce.com/)
[![License: GPLv2](https://img.shields.io/badge/License-GPLv2-brightgreen)](https://www.gnu.org/licenses/gpl-2.0.html)

This plugin allows you to **selectively sync** LearnPress courses with WooCommerce, turning them into virtual products. This enables full use of WooCommerce coupons (including product-specific restrictions) for your courses — without needing the official premium LearnPress WooCommerce add-on.

### Key Features
- **Selective course syncing**: Choose individual courses or entire course categories to sync via a dedicated settings page.
- **Automatic virtual product creation**: Synced courses become WooCommerce virtual products with matching title, price, description, and featured image.
- **Full coupon compatibility**: Synced products reliably appear in WooCommerce coupon "Product restrictions" dropdown.
- **Automatic enrollment**: Users are automatically enrolled in the LearnPress course upon successful WooCommerce purchase.
- **User-friendly settings page**: Dedicated menu with checkboxes, search, bulk actions, and category filtering.
- **Lightweight & secure**: No external dependencies, safe AJAX handling for large numbers of courses.
- **Compatible with free LearnPress version**.

### Installation
1. Download the plugin zip file (`jules-lp-woo-integration.zip`) from this repository.
2. In your WordPress admin dashboard, go to **Plugins > Add New**.
3. Click **Upload Plugin**, select the zip file, and install.
4. **Activate** the plugin.
5. Navigate to the new menu **LearnPress > Woo Integration** (or standalone **LP Woo Integration**) and sync your desired courses.

> **Requirements**: LearnPress (free) and WooCommerce must be installed and active.

### Usage
1. After activation, visit the plugin settings page.
2. Select courses using checkboxes (or filter by category and use bulk selection).
3. Click **Sync Selected Courses**.
4. Go to **WooCommerce > Marketing > Coupons**, create a new coupon — synced courses will appear in the product search dropdown.
5. Test: Add a course to cart and apply the coupon.

### Screenshots
<!-- Add screenshots when available -->
<!-- ![Settings Page](screenshots/settings-page.png) -->
<!-- ![Coupon Restrictions](screenshots/coupon-restrictions.png) -->

### Limitations & Notes
- Tested primarily with the free version of LearnPress (premium add-ons may cause conflicts).
- Existing courses require manual sync from the settings page.
- Price changes in LearnPress automatically update the linked WooCommerce product.
- If products don't appear in coupons, clear WordPress/WooCommerce cache and re-sync.

### Changelog
- **1.0.0** (Initial Release)
  - Selective course syncing
  - Dedicated settings page with bulk actions
  - Automatic enrollment on purchase
  - Full WooCommerce coupon compatibility

### Contributing
Found a bug or have an improvement idea? Pull requests are welcome!  
Please use Issues to report problems.

### License
This plugin is released under **GPLv2 or later** — same as WordPress.

---

**Built with assistance from AI Jules** 🚀  
If this plugin helps you, consider giving the repository a ⭐!
