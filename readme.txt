=== Bayarcash GiveWP ===
Contributors: webimpian
Tags: FPX, DuitNow, Direct Debit, DuitNow QR
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Accept online donation & QR from Malaysia. Currently, Bayarcash support FPX, Direct Debit and DuitNow payment channels.

== Description ==

Bayarcash is a Malaysia online payment platform that support FPX, Direct Debit & DuitNow payment channels.

== How it works ==

This plugin will connect to Bayarcash endpoint to secure payment processing between bank & ewallet in Malaysia.

Please visit our website [https://bayarcash.com/](https://bayarcash.com/) for terms of use and privacy policy, or email to hai@bayarcash.com for any inquiries.

== Features ==

- One-off donation via FPX (CASA & credit card account)
- Donation via DuitNow Online Banking/Wallets
- Donation via DuitNow QR
- Weekly & monthly recurring donation via Direct Debit. Deduction happen automatic directly via bank account (flat rate fees). Required [**Recurring Donations for GiveWP**](https://givewp.com/addons/recurring-donations/)
- Support multiple Bayarcash account per website
- Support multiple portal key per donation form for better reporting & finance reconciliation
- Impose fees to donor. Required [**GiveWP Fee Recovery**](https://givewp.com/addons/fee-recovery/)
- Shariah-compliance payment gateway

Register as [**Bayarcash merchant here**](https://bayarcash.com/register/)

== Requirements ==

To use Bayarcash GiveWP requires minimum:

- PHP 7.4
- WordPress 5.6
- GiveWP 1.8
- GiveWP Recurring Donations 2.4 (for Direct Debit)

== Installation ==

Make sure that you already have GiveWP plugin installed and activated.

1. Login to your **WordPress Dashboard**
2. Go to **Plugins > Add New**
3. Search **Bayarcash GiveWP** and click **Install**
4. **Activate** the plugin through the **Plugins** screen in WordPress

== Screenshots ==

1. Bayarcash general setting page. Insert your account PAT & portal key to start collection.
2. Enabling Bayarcash on gateway page. You can change label "Bayarcash" to something more verbose like "Alhamdulillah Jom Sedekah".
3. Beside general Bayarcash setting, you can also define per form setting for PAT and portal key.
4. Bayarcash support GiveWP Fee Recovery addon. Impose fees to donor by percentage or flat rate fees.

== Frequently Asked Questions ==

= Where can I register as Bayarcash merchant? =
You can register as merchant [here](https://bayarcash.com/register/). We accept organisation that has active SSM certificate, ROS for non-governmental organization (NGO), state-certified for madrasah & sekolah tahfiz and yayasan.

= What does it mean by shariah-compliance payment gateway? =
Please note that in order for us to comply with our shariah-compliance policy, we do not support organisation involved in:

- The production or sale of pork, alcohol and alcohol-related activities, non-halal food and beverages, tobacco product (including e-cigarettes), drug paraphernalia, pornography, guns, and other arms
- Gaming and betting
- Shariah non-compliant entertainment
- Conventional insurance
- Jihadist or terrorist activities
- Fraud and corruption organization

[Click here](https://bayarcash.com/wp-content/uploads/sites/2/2022/09/elzar-bayarcash.jpeg) to view shariah-certificate endorsement by our official advisor Dr. Zaharuddin Abd Rahman from Elzar Shariah Solutions & Advisory.

== Changelog ==

= 4.1.0 =
* Added support for DuitNow QR, SPayLater,Boost PayFlex & QRIS payment methods

= 4.0.0 =
* New: Added setting to support multi-channel for Bayarcash
* New: Integrated support for DuitNow and Line of Credit payment methods
* New: Implemented Bayarcash SDK for enhanced API interactions
* New: Streamlined token verification process using Vue.js, reducing admin page load
* New: Added checksum verification for increased security
* Enhancement: Optimized admin settings page with dynamic portal key selection
* Enhancement: Improved cron requery function for better performance
* Enhancement: Refined per-payment form settings
* Enhancement: Added phone field from Bayarcash to donor details
* Enhancement: Extended donation data export to include Bayarcash phone field for default export.
* Enhancement: Implemented regex validation for identification number in recurring forms
* Fix: Resolved token verification issue for new users

= 3.0.1 =
* Fix invalid token/key,
* Fix missing metabox option.
* Fix recurring function.
* Fix missing remark "RM1.00 bank verification fees" for form with multi-step form layout.
* Add recurring.
* Add weekly recurring.
* Add verify PAT.
* Enhancements, option to enable/disable phone number field on non-recurring form.
* Enhancements, standardize "Purpose of Payment".
* Enhancements, set Maximum Email Length to 27 Characters for Recurring Donations.

= 3.0.0 =
* Refactoring and code improvements.

= 2.1.9 =
* Resolve compatability issue with PHP 8.0.

= 2.1.8 =
* Add security measure to ensure server response are not tampered.

= 2.1.7 =
* Add support for multiple Bayarcash accounts for different GiveWP campaigns.

= 2.1.6 =
* Fix re-query order status update respond mapping from https://console.bayar.cash console.

= 2.1.5 =
* Set donation status failed when re-query transaction status cancelled.

= 2.1.4 =
* Improve payment query efficiency by limiting re-query for only Bayarcash payments (payments that have post meta of bayarcash_fpx_transaction_exchange_no).

= 2.1.3 =
* Add re-query when donation status is abandoned.

= 2.1.2 =
* Fix show blank page when attempt to pay on recent GiveWP plugin version.

= 2.1.1 =
* Add response sanitizer and validator.
* Handle other response exception.

= 2.1.0 =
* Split payment gateway code into its own file.
* Add cronjob code to re-query payment status from https://console.bayar.cash console.

= 2.0.2 =
* Fetch the associated portal key based on the order number during the callback process.

= 2.0.1 =
* Enable portal key customization at meta box for donation form.

= 2.0.0 =
* Replace parameter s3a with RefNo for more user friendly submission request.
* Replace combination of Portal Auth Username and Portal Auth Password with Bearer Token in order to fit updated Bayarcash console portal requirement.
* Add parameter payment_gateway = 1 to the transaction request form.

= 1.0.1 =
* Add features to include the transaction fee (tested using GiveWP Fee Recovery plugin).

= 1.0.0 =
* Initial release.
