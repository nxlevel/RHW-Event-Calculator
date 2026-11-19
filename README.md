# Rose Hall Weddings & Events - Event Cost Calculator & Lead Capture WordPress Plugin

A production-ready WordPress plugin developed for Rose Hall Weddings & Events (Montego Bay, Jamaica). It replaces the legacy Google Form with a responsive, accessible, real-time event cost calculator and high-converting lead capture system.

---

## Table of Contents

1. [Overview & Highlights](#overview--highlights)
2. [Source Materials Analysis](#source-materials-analysis)
3. [How the Estimate is Calculated](#how-the-estimate-is-calculated)
4. [Assumptions & Missing Pricing Rules for Venue Approval](#assumptions--missing-pricing-rules-for-venue-approval)
5. [Installation & Setup Guide](#installation--setup-guide)
6. [Administrator Configuration Guide](#administrator-configuration-guide)
7. [Manual Verification Checklist](#manual-verification-checklist)
8. [Technical Architecture & Security](#technical-architecture--security)

---

## 1. Overview & Highlights

- **Dynamic Loan-Style Calculator**: Real-time reactive estimate recalculates instantly as prospective clients modify their guest count, venue, bar packages, rentals, or entertainment options.
- **Complete Google Form Parity**: Preserves all 29 questions and answer choices from the original Rose Hall questionnaire across 6 structured, accessible steps.
- **Exact Business Cost Matching**: Automatically computes rental ratios (e.g. 10-seater round tables, bistro cocktail tables, Chiavari chairs, linens, charger plates, napkins), entertainment starting fees, officiant fees, and full planning fees directly from the real proposal.
- **Authoritative Server-Side Validation**: Never relies on client-side calculations as the source of truth. All estimates are recalculated and validated on the WordPress server before saving.
- **Lead Capture & Notifications**: Automatically sends luxury HTML confirmation receipts to couples and instant notification alerts to the venue coordination team.
- **Full WordPress Admin Control**: Complete visual builder to customize questions, options, prices, taxes, service charges, colors, and styling, plus a comprehensive leads manager with CSV export.

---

## 2. Source Materials Analysis

The plugin is architected from three primary source documents provided by Rose Hall:
1. **Google Form (RHW Form - 7 Pages)**: Extracted all 29 questions covering contact details, event details, venue options, planner requirements, legal vs. symbolic status, plated vs. buffet dinner, bar options, decor/tent needs, bouquet preferences, MC & entertainment selections, hotel of stay, guest transportation, and Great House tour/golf attractions.
2. **Real Event Cost Breakdown (Proposal - 75 Guests at White Witch Golf Course)**:
   - Venue Rental: White Witch Golf Course = **$5,000.00**
   - Personal Flowers: Bridal Bouquet with Matching Boutonniere = **$225.00**
   - Ceremony Décor: Gold Chiavari Chairs w/ Ivory Cushions = **$7.00/guest** ($525 for 75 guests); Signing Table with Linen = **$45.00**
   - Cocktail Hour Décor: Bistro Tables w/ Ivory Linen = **$55.00/table** (5 tables for 75 guests = 1 per 15 guests)
   - Dinner Reception Décor: Sweetheart Table w/ Linen = **$60.00**; 10-seater Rounds or Trestle Tables w/ Linen = **$55.00/table** ($\lceil 75/10 \rceil = 8$ tables = $440); Charger Plates = **$8.00/guest** ($600); Napkins = **$5.00/guest** ($375)
   - Entertainment: In-House DJ (Ceremony, Cocktail, Reception) = **$1,500.00**; Master of Ceremony starting cost = **$500.00**; Violinist starting cost = **$350.00**; Fire Dancer starting cost = **$500.00**
   - Equipment & Services: Heavy Duty Generator = **$885.00**; Setup, breakdown and clean up fee = **$350.00**
   - Planning & Officiant: Officiant & Marriage Certificate Processing = **$550.00**; Full Planning Fee = **$2,500.00**
   - Taxes & Fees: 15% Government Tax on Subtotal; 20% Service Charge on Food & Beverage items.
3. **Signature Bar Options Sheet**:
   - **Basic Bar**: First 2 hours = **$30.00/person**; each additional hour = **$13.50/person** (Rum Punch, Red/White Wine, Beers, Sodas, Juices, Champagne, Water).
   - **Medium Bar**: First 2 hours = **$36.00/person**; each additional hour = **$16.50/person** (Adds Rums, Vodka, Scotch, Gin).
   - **Premium Bar**: First 2 hours = **$54.00/person**; each additional hour = **$22.00/person** (Adds Johnnie Walker, Hennessy, Jack Daniel's, Appleton Rums, Beefeater Gin, Grey Goose, Absolut, Tequila).
   - All Bar options attract the **20% F&B Service Charge**.

---

## 3. How the Estimate is Calculated

The calculation engine evaluates items in order:

$$\text{Subtotal} = \sum \text{Line Item Amounts}$$

Where individual line items are evaluated by rule:
1. **Fixed Fees**:
   $$\text{Amount} = \text{Unit Price}$$
   *(e.g., Venue Rental, Signing Table \$45, Sweetheart Table \$60, In-House DJ \$1,500, Generator \$885, Officiant \$550, Full Planning Fee \$2,500, Setup/Cleanup \$350)*
2. **Per-Guest Items**:
   $$\text{Amount} = \text{Guest Count} \times \text{Unit Price}$$
   *(e.g., Gold Chiavari Chairs \$7/guest, Charger Plates \$8/guest, Napkins \$5/guest, Great House Tours \$30–\$35/guest)*
3. **Table Ratios (Ceiling Functions)**:
   - Dining Tables: $\lceil \text{Guest Count} / 10 \rceil \times \$55.00$
   - Cocktail Bistro Tables: $\lceil \text{Guest Count} / 15 \rceil \times \$55.00$
4. **Signature Bar Packages (Duration-Based Formula)**:
   $$\text{Per-Person Rate} = \text{Base Rate (First 2 Hours)} + \max(0, \text{Hours} - 2) \times \text{Additional Hour Rate}$$
   $$\text{Bar Total} = \text{Per-Person Rate} \times \text{Guest Count}$$
5. **Food & Beverage Service Charge**:
   $$\text{F\&B Subtotal} = \sum \text{Items where } \text{is\_fb} = \text{true}$$
   $$\text{Service Charge} = \text{F\&B Subtotal} \times \frac{20}{100}$$
6. **Government Tax**:
   $$\text{Tax Amount} = \text{Subtotal} \times \frac{15}{100}$$
7. **Grand Total Estimate**:
   $$\text{Grand Total} = \text{Subtotal} + \text{Service Charge} + \text{Tax Amount}$$

*Verification Example*: For a 75-guest full-service wedding matching the exact sample proposal at White Witch Golf Course with In-House DJ, MC, Violinist, Fire Dancer, Officiant, Planner, Generator, and standard rentals, the subtotal calculates to exactly **\$14,680.00**, Tax is **\$2,202.00**, producing the exact proposal final amount of **\$16,882.00**.

---

## 4. Assumptions & Missing Pricing Rules for Venue Approval

The following items are flagged for review and have been made configurable in the plugin settings:

| Item / Policy | Default Plugin Setting | Status / Venue Approval Required |
| :--- | :--- | :--- |
| **Non-Quoted Venue Prices** | Rose Hall Great House (\$5,500), Cinnamon Hill (\$4,500), Aqueduct (\$3,500), The Palms (\$4,000), Mt. Zion (\$2,500) | **Flagged**: Only White Witch Golf Course (\$5,000) was in the sample proposal. The other 5 venues were linked in a Google Drive PDF. Venue can adjust any venue rental price in the builder. |
| **Catering / Buffet Menus** | Marked as "Curated during consultation" (\$0 in baseline estimate) | **Flagged**: The cost breakdown notes "Buffet (Let's discuss options)". If Rose Hall has standard per-person buffet rates (e.g. \$85/person), these can be input directly into the question's pricing rules. |
| **Bespoke Personal Flowers** | Bridal Bouquet (\$225 quoted); Bridesmaid bouquets (\$125 configurable) | **Flagged**: Centerpieces, arches, and custom boutonnieres are noted as "Let's discuss". |
| **Tents & Weather Shelter** | Inquiry / Consultation | **Flagged**: Outdoor tenting varies drastically by marquee size and style. It is currently treated as an inquiry item with the Generator priced at \$885. |
| **Transportation Costs** | Inquiry / Consultation | **Flagged**: Fleet requirements (mini-bus vs. coach) depend on hotel location and guest distribution. |
| **Deposit Calculation** | 25% of estimated total | **Flagged**: Proposal mentions "Deposit Paid: \$0.00". The plugin supports displaying a 25% or 50% provisional deposit requirement. |

---

## 5. Installation & Setup Guide

### Method A: Install via WordPress Admin Upload (Recommended)
1. In your WordPress Dashboard, navigate to **Plugins > Add New**.
2. Click **Upload Plugin** at the top.
3. Choose the generated `rhw-event-calculator.zip` file.
4. Click **Install Now**, then click **Activate Plugin**.

### Method B: Manual Directory Installation
1. Copy the folder `RHW-Event-Calculator` into your WordPress plugins directory:
   `/wp-content/plugins/rhw-event-calculator/`
2. In WordPress Admin, navigate to **Plugins > Installed Plugins**.
3. Locate **Rose Hall Weddings - Event Cost Calculator & Lead Capture** and click **Activate**.

### Automatic Seeding:
Upon activation, the plugin automatically creates the necessary database tables (`wp_rhw_forms` and `wp_rhw_submissions`) and seeds the complete **Rose Hall Weddings & Events - Official Calculator** with all 29 questions, pre-configured pricing, and branding.

### Displaying the Calculator:
To display the calculator on any page, post, or Elementor/Gutenberg block, paste the shortcode:
```text
[event_cost_calculator]
```
Or specify a particular form ID:
```text
[event_cost_calculator id="1"]
```

---

## 6. Administrator Configuration Guide

### Managing Questions & Sections
1. Go to **Event Calculator > All Forms** and click **Edit** on your form.
2. Under the **Sections & Questions** tab:
   - **Add/Edit Questions**: Click **Add Question** or expand an existing question card.
   - **Configure Question Types**: Radio, Checkboxes, Dropdown Select, Number, Text, Textarea, Email, Tel.
   - **Set Pricing Rules**: Expand choice items to set **Pricing Type** (`Fixed`, `Per Guest`, `10-Guest Tables`, `Bistro Tables`, `Bar Package`) and **Amount (\$)**.
   - **Configure Conditional Rules**: Set questions to show or hide based on answers to previous questions (e.g. show Tour details only if Attractions is "Yes").
   - **Reorder**: Drag questions up or down using the grab handle.

### Configuring Taxes, Currency & Disclaimers
1. Navigate to the **Calculations & Taxes** tab in the Form Builder:
   - **Government Tax Rate**: Default `15%`.
   - **F&B Service Charge**: Default `20%`.
   - **Currency Symbol**: Default `$` (USD).
   - **Customer Explanatory Text**: Edit the disclaimer displayed to couples below the live estimate.

### Configuring Email Notifications
1. Navigate to the **Email Notifications** tab:
   - **Recipient Emails**: Enter comma-separated addresses (e.g. `weddings@rosehall.com, sales@rosehall.com`).
   - **Email Subject**: Customize the subject template with `{customer_name}`, `{event_date}`, `{submission_id}`.
   - **Customer Receipt**: Check the box to automatically send couples an itemized copy of their preliminary quote upon submission.

### Managing Submitted Leads
1. Navigate to **Event Calculator > Submissions / Leads**.
2. View incoming leads with client name, contact details, guest count, venue, and calculated grand total.
3. Click **View Details** to open the full dossier, review questionnaire answers, update pipeline status (`New`, `Contacted`, `Consultation Booked`, `Formal Quote Sent`, `Booked`), and save internal specialist notes.
4. Click **Export Leads to CSV** to download all records for CRM or spreadsheet analysis.

---

## 7. Manual Verification Checklist

Follow this checklist to verify your installation:

- [ ] **Form Display**: Navigate to the page containing `[event_cost_calculator]`. Confirm the multi-step wizard, progress bar, questions, and sticky estimate summary render cleanly on desktop and mobile.
- [ ] **Guest Count Responsiveness**: Change guest count from `75` to `100`. Verify that chairs, table quantities, charger plates, napkins, and bar totals update immediately.
- [ ] **Live Loan Calculator Updates**:
  - Select "White Witch Golf Course": verify **+\$5,000.00** adds to the summary.
  - Check "DJ Services": verify **+\$1,500.00** adds.
  - Select "Basic Bar" with 2 hours: verify **+\$2,250.00** (75 × \$30) adds to subtotal and **+\$450.00** (20%) adds to F&B Service Charge.
  - Increase bar duration to 3 hours: verify **+\$1,012.50** (75 × \$13.50) adds dynamically.
- [ ] **Conditional Logic Visibility**:
  - In Section 1, select "Venue Only": verify full-service rental questions are hidden.
  - In Section 4, select Hotel "Other": verify text field "If other, state your place of stay" appears.
  - In Section 5, toggle Attractions "Yes": verify tour options and guest count appear.
- [ ] **Server-Side Validation**: Try submitting Step 6 with an invalid email or missing name. Confirm that the form halts and highlights the required fields with error messages.
- [ ] **Anti-Duplicate Submission**: Click submit twice rapidly; verify the button disables and displays a spinner, preventing duplicate rows in the database.
- [ ] **Submission Storage**: Go to **WordPress Admin > Event Calculator > Submissions / Leads**. Verify the new lead appears with the exact answers and calculated totals.
- [ ] **Email Dispatch**: Check the configured admin inbox and customer inbox for the formatted HTML notification and estimate receipt.

---

## 8. Technical Architecture & Security

- **PHP Compatibility**: Fully compatible with PHP 7.4 through PHP 8.3+.
- **WordPress Compatibility**: WordPress 5.8 through WordPress 6.7+.
- **Zero External Dependencies**: Standalone vanilla CSS and native jQuery without bloated external libraries.
- **Security & Sanitization**:
  - `check_ajax_referer` and WordPress nonces protect all frontend and administrative endpoints.
  - Strict capability check (`manage_options`) on all administrative operations.
  - Server-side sanitization on all inputs (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, `absint`, `floatval`).
  - Custom CSS field sanitized via `wp_strip_all_tags` to prevent XSS.
  - Database queries prepared using `$wpdb->prepare()`.
- **Safe Uninstall**: Submissions and configurations remain safe upon plugin deactivation or deletion unless the administrator explicitly enables the "Delete data upon uninstall" setting.
