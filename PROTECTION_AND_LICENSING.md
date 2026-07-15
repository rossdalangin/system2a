# Protection & Licensing: Securing Your Agency Asset

As the owner of **Agency Nexus**, your intellectual property (IP) and your custom AI algorithms represent your most valuable assets. While WordPress is built on the GPL (General Public License), there are several strategies—both technical and business-oriented—to protect your revenue, prevent unauthorized API usage, and ensure long-term growth.

---

## 1. The GPL Reality & Business Strategy

WordPress PHP code must be GPL-compliant, meaning users have the legal right to modify it. However, the industry standard for commercial protection relies on **"The Value of the Network."**

*   **Continuous Updates:** Users pay for an active license key to access your automatic update server. Without a key, they miss out on critical security patches, compatibility updates, and new feature modules.
*   **Expert Support:** Only licensed users get access to your "Agency Support Desk." For active, high-revenue agencies, downtime or minor operational friction is far more expensive than a yearly license fee.
*   **AI Gateway & Cloud Dependencies:** Advanced, cloud-dependent AI features (such as sophisticated keyword forecasting, complex sentiment calculations, or remote model integrations) can be gated and validated on your license server, ensuring only authenticated keys can communicate with advanced cloud endpoints. For offline or unregistered users, the plugin gracefully falls back to the context-aware Local CoPilot mock engine.

---

## 2. Technical Protection Architecture

### **A. Remote License Validation**
Agency Nexus communicates securely with your "Main Domain" using the provided **Agency Nexus Store** companion plugin.

*   **Handshake:** On activation, the client-side plugin sends the Site URL and License Key to your store via a secure REST API endpoint (`/wp-json/agency-nexus-store/v1/validate`).
*   **Validation:** Your store validates the credentials against the `an_issued_licenses` database table. If valid, it returns the `tier` (Starter, Pro, or VIP) and logs the activation in `an_license_activations`.
*   **Enforcement:** The plugin stores this validation in a secure transient locally. If the handshake fails, the key is suspended, or a mismatch occurs, advanced Pro/VIP modules and unified AI capabilities are instantly locked.

### **B. Tier-Based Feature & AI Gating**
We use a granular capability and feature-gate system to restrict features based on the active license tier:

| Feature / Capability | Starter | Pro ($199/yr) | Agency VIP ($999) |
| :--- | :---: | :---: | :---: |
| **CRM & Project Management** | ✅ | ✅ | ✅ |
| **Lead Capture & Basic Forms** | ✅ | ✅ | ✅ |
| **MoneyFlow ROI Tracker** | ❌ | ✅ | ✅ |
| **AutoPilot Automation Rules** | ❌ | ✅ | ✅ |
| **Unified AI Copilot Suite** | ❌ | ✅ (Standard) | ✅ (Unlimited/Advanced) |
| **BurnoutGuard Team Health** | ❌ | ❌ | ✅ |
| **Referral Partner Hub** | ❌ | ❌ | ✅ |
| **White-Labeling (Logo & Portal)** | ❌ | ❌ | ✅ |
| **Multi-Site & Unlimited Sites** | ❌ | ❌ | ✅ |
| **Priority Account Manager** | ❌ | ❌ | ✅ |

*   **Starter Tier:** Contains core project tracking. All AI-powered links are disabled/hidden, and Pro modules are restricted.
*   **Pro Tier:** Grants access to standard AI Copilot actions (e.g., content drafting and message polishing), limited to standard API model calls (e.g., GPT-4o-mini or Claude-Haiku) to optimize server token expenses.
*   **Agency VIP Tier:** Grants unlimited, unrestricted access to top-tier AI models (e.g., GPT-4o, Claude-3-5-Sonnet, Gemini-1.5-Pro). It enables complete white-labeling (removing all "Agency Nexus" mentions and footers, and replacing them with custom agency branding).

---

## 3. Implementing Your Store (Main Domain Setup)

To start selling Agency Nexus, follow these steps on your primary marketing site:

1.  **Install the Store Companion:** Upload and activate the `agency-nexus-store` folder as a plugin on your main domain where your primary marketing site resides.
2.  **Configure Gateway Credentials:** In **AN Store > Settings**, enter your live **Stripe Secret Key** and **PayPal Business Email** to process secure, multi-currency credit card or wallet checkouts.
3.  **Set Tier Pricing:** Define your license fees for the Starter ($0), Pro ($199/yr), and VIP ($999/lifetime) tiers.
4.  **Display the Pricing Table:** Create a high-converting "Pricing" page on your WordPress site and insert the `[an_pricing_table]` shortcode.
5.  **Fulfillment Automation:** The store companion handles the entire checkout flow. Once a payment is confirmed:
    *   A unique license key is generated in the `an_issued_licenses` table.
    *   The customer receives an automated, beautiful HTML email containing their license key and a secure download link to the Pro/VIP ZIP file.
    *   The transaction, client domain, and renewal dates are tracked in your **AN Store > Payments** dashboard.

---

## 4. Anti-Piracy & API Abuse Recommendations

1.  **Site Limit Enforcement:** Use the store dashboard to monitor how many unique WordPress sites are active under a single license key. If a "Pro" key (limited to 1 site) appears on 50 domains, your server will automatically flag the abuse, enabling you to suspend or terminate the key with a single click.
2.  **AI Key Security:** If you choose to host a centralized API key to power your users' AI engines, enforce strict rate-limiting on your store validation server (e.g., maximum 100 AI queries per site per day) to protect your token budget from malicious scrapers.
3.  **Viral Branding Loop:** In the free version, include an unremovable "Powered by Agency Nexus" link in the client portal. This acts as a viral marketing engine. Users must upgrade to the VIP tier to remove it and customize the interface with their own brand identity.
4.  **Leverage FreebieFactory:** Don't waste time on complex code obfuscation tools (like IonCube) which slow down sites. Instead, build value into the Marketplace. If paid users receive ongoing premium SOPs, document templates, and design files directly from your central library, they will always stay subscribed.

---
**Technical Note:** Always ensure your main domain has a valid, modern SSL certificate. The license validation requests use secure `wp_remote_post()` over HTTPS and require a secure, reliable handshake to prevent errors.
