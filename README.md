# Rose Hall Weddings & Events - Event Cost Calculator & Lead Capture WordPress Plugin (v2.0)

A production-ready WordPress plugin developed for Rose Hall Weddings & Events (Montego Bay, Jamaica). It replaces the legacy Google Form with an accessible, real-time event cost calculator, dynamic Weekday/Weekend venue pricing, file uploads, full-width wizard navigation, and a high-converting lead capture system.

---

## Table of Contents

1. [Version 2.0 Feature Highlights](#version-20-feature-highlights)
2. [Source Materials & Official Rate Card Analysis](#source-materials--official-rate-card-analysis)
3. [How the Estimate is Calculated](#how-the-estimate-is-calculated)
4. [Installation & Setup Guide](#installation--setup-guide)
5. [Administrator Configuration Guide](#administrator-configuration-guide)
6. [Manual Verification Checklist](#manual-verification-checklist)
7. [Technical Architecture & Security](#technical-architecture--security)

---

## 1. Version 2.0 Feature Highlights

1. **Date & Time Pickers**: Modern native date and time selectors replace freeform text. Dates prior to today are disabled by default.
2. **Dynamic Weekday vs. Weekend / Holiday Venue Pricing**:
   - Automatically determines day of the week (Monday–Thursday = Weekday; Friday, Saturday, Sunday = Weekend/Holiday).
   - Displays a live badge (e.g. `🗓 Saturday: Weekend/Holiday Rate`).
   - Venue choice tiles update their displayed prices dynamically in real time.
3. **Sticky Sidebar That Scrolls With The Page**:
   - The `.rhw-calc-sidebar` uses rock-solid CSS `position: sticky; top: 24px; align-self: start;` so clients can view live calculations and line items without having to scroll back.
4. **Full-Width Progress Bar**:
   - The wizard navigation spans 100% of the entire container above both the form column and the sticky sidebar.
5. **Interactive "Other" Choice Inputs**:
   - Selecting "Other" on radio buttons, checkboxes, or dropdowns slides open an inline text field allowing couples to type custom specifications.
6. **Smart Scroll & Focus on Missed Required Fields**:
   - If a client clicks Continue/Next with missing required answers, the window smoothly jumps to the first missed question with an error pulse animation.
7. **Image / File Uploads for Décor & Bouquet Questions**:
   - Supports image uploads (JPG, PNG, WEBP, GIF, PDF up to 10MB) for wedding décor design and bouquet inspiration with live thumbnail previews.
   - Uploads are safely handled via `wp_handle_upload()` and saved with the lead record.
8. **Independent Tour / Golf Participant Multiplier**:
   - If the client selects Great House tours or golf, they can specify the exact number of participants taking part (e.g. 20 of 75 guests), preventing inflated whole-guest-count tour charges.
9. **"Estimated Cost" & "Line Items" Terminology**:
   - Updated from "Estimated Investment" to "Estimated Cost", and "Itemized Line Items" to "Line Items".

---

## 2. Source Materials & Official Rate Card Analysis

### Official Rose Hall Weddings & Events Rate Card

| Venue | Weekdays Rate (Mon–Thu) | Weekends / Holidays Rate (Fri–Sun) | Capacity Details |
| :--- | :--- | :--- | :--- |
| **Rose Hall Great House** | **US\$3,000.00** | **US\$3,500.00** | Main Staircase: 150 seated; Main Lawn: 500 banquet / 1,000 standing; Ficus Tree: 200 standing |
| **White Witch Golf Club** | **US\$3,000.00** | **US\$3,500.00** | Terrace: 50 ceremony / 80 buffet / 100 plated; Driving Range: 100 seated; Main Lawn: 200 banquet |
| **Cinnamon Hill Great House** | **US\$3,000.00** | **US\$3,500.00** | Ceremony Lawn: 40 seated; Back Lawn: 80 seated; Main Lawn: 250 seated / 350 standing |
| **Palms on the Ocean's Edge** | **US\$2,000.00** | **US\$2,500.00** | Ceremony Peninsula: 250; Reception Lawn: 250; East Beach / Turtle Beach: 100 |
| **The Aqueduct (Ceremony only)** | **US\$2,000.00** | **US\$2,500.00** | Top Lawn: 300 seated / 400 standing / 250 banquet; Bottom Lawn: 70 standing |
| **Mount Zion Church** | **US\$1,000.00** | **US\$1,000.00** | Monday – Sunday Flat Rate; Capacity: 200 |

*Policy Note*: A 50% deposit is required to secure a preferred venue. All rates attract Jamaican Government Taxes (15%).

---

## 3. How the Estimate is Calculated

$$\text{Subtotal} = \text{Venue Rate} + \text{Rentals} + \text{Entertainment} + \text{Planning/Officiant} + \text{Bar} + \text{Tours}$$

1. **Venue Selection**:
   - Evaluated as $\text{Weekday Rate}$ or $\text{Weekend Rate}$ depending on the date selected in the date picker.
2. **Décor Rentals (Automated for Full Service)**:
   - Gold Chiavari Chairs: $\text{Guests} \times \$7.00$
   - Signing Table: $\$45.00$
   - Bistro Tables: $\lceil \text{Guests} / 15 \rceil \times \$55.00$
   - Sweetheart Table: $\$60.00$
   - 10-Seater Dining Tables: $\lceil \text{Guests} / 10 \rceil \times \$55.00$
   - Charger Plates: $\text{Guests} \times \$8.00$
   - Dinner Napkins: $\text{Guests} \times \$5.00$
   - Setup, Breakdown & Cleanup: $\$350.00$
3. **Signature Bar Package Formula**:
   $$\text{Per-Person Rate} = \text{Base 2-Hour Rate} + (\max(0, \text{Hours} - 2) \times \text{Additional Hour Rate})$$
   $$\text{Bar Total} = \text{Per-Person Rate} \times \text{Guests}$$
4. **Rose Hall Attractions Formula**:
   $$\text{Attraction Cost} = \text{Tour Participants Count} \times \text{Attraction Rate}$$
5. **Taxes & Service Charges**:
   $$\text{F\&B Service Charge} = \text{F\&B Subtotal} \times 0.20$$
   $$\text{Government Tax} = \text{Subtotal} \times 0.15$$
   $$\text{Estimated Cost} = \text{Subtotal} + \text{F\&B Service Charge} + \text{Government Tax}$$

---

## 4. Installation & Setup Guide

### 1-Click Upload via WordPress Admin
1. Go to **Plugins > Add New > Upload Plugin**.
2. Upload `rhw-event-calculator.zip`.
3. Click **Install Now** and **Activate**.
4. Insert `[event_cost_calculator]` on any page.

---

## 5. Manual Verification Checklist (v2.0)

- [x] **Date Picker**: Preferred Date uses native `type="date"` and time uses `type="time"`.
- [x] **Weekday vs Weekend Detection**: Selecting a Friday, Saturday, or Sunday shows the Weekend badge and adjusts venue rates to \$3,500/\$2,500. Selecting Monday–Thursday adjusts to \$3,000/\$2,000.
- [x] **Terminology**: Displays "Estimated Cost" and "Line Items" throughout.
- [x] **"Other" Inputs**: Selecting "Other" on Event Type, Service Type, or Referral reveals an inline text input.
- [x] **Jump to Missed Fields**: Omitting a required field and clicking Continue scrolls smoothly to the missed field with red border and shake animation.
- [x] **Sticky Sidebar**: `.rhw-calc-sidebar` stays locked in view while scrolling down long questionnaire pages.
- [x] **Full-Width Progress Bar**: Spans the full width of the container above the wizard and sidebar.
- [x] **Image Uploads**: Decor and bouquet upload fields accept image files, show image previews, and store URLs upon submission.
- [x] **Attraction Multiplier**: Tour pricing multiplies by the custom participant number (e.g. 20 guests) rather than the entire wedding count.
