# Agency Nexus: The All-in-One Agency OS for WordPress

**Agency Nexus** is a professional-grade "Agency Operating System" designed to centralize and automate the entire lifecycle of a freelancer or agency owner. Built on WordPress, it integrates CRM, project management, content operations, client collaboration, financial intelligence, team health, and a cutting-edge **AI Copilot Engine** into a single, high-performance dashboard.

Stop jumping between five different SaaS tools and paying a massive "Tab Tax." Control your agency from the platform you already know.

---

## 🚀 The Core Ecosystem

Agency Nexus is composed of 10 deeply integrated modules, fully enhanced by the native, context-aware AI Copilot:

### 1. 💼 SmartOnboard (Client Onboarding & Deals)
*   **Interactive Scope Builder:** Build service packages with dynamic pricing and addons.
*   **Proposal Generator:** Turn scopes into professional proposals with e-signature simulation.
*   **AI Proposal Rewriter:** Leverage the AI Copilot to rewrite, polish, and optimize proposed deliverables and scope objectives instantly from the proposal view.

### 2. 📅 ContentMatrix (Content Planning & Creation)
*   **Pillar Content Architect:** Map out topic clusters and maintain content authority.
*   **Visual Calendar:** Drag-and-drop scheduling across multiple social and blog platforms.
*   **AI-Driven Batch Automation:** Generate dozens of content drafts, highly engaging blog outline ideas, and SEO keyword gap analysis using context-aware AI prompts in seconds.

### 3. ✅ ApprovalFlow (Posting & Sign-off)
*   **Multi-Stage Drafting:** Move content from Idea -> Draft -> Review -> Approved.
*   **Version History:** Side-by-side comparison of revisions with threaded feedback and annotations.
*   **AI Content Refiner:** Refine, polish, and optimize titles, descriptions, and text drafts directly inside the client sign-off portal.

### 4. 🧠 EngageTrack (Lead Intelligence & CRM)
*   **Smart Capture:** High-converting forms with automated redirect builders.
*   **Lead Scoring & Summarization:** AI-inspired algorithm ranks prospects (0-100) based on potential value, while the Copilot analyzes submission data to provide strategic brief summaries.
*   **Social Hub:** Aggregate engagement from Instagram, LinkedIn, X, and Facebook with AI sentiment and interaction intent analysis.

### 5. 💰 MoneyFlow (Financial Intelligence)
*   **True ROI Tracking:** Automatically calculate project profitability: `Budget - (Labor + Expenses)`.
*   **Branded Invoicing:** Generate professional invoices with your logo and automated late fees.
*   **AI Invoicing Reminders:** Use the AI Copilot to draft context-specific, friendly yet firm payment reminders based on client invoice history.

### 6. 💬 ClientSync (Communication Hub)
*   **Unified Messaging:** Secure, real-time chat between staff and clients.
*   **AI Chat Replies & Message Polishing:** Let the AI Copilot draft context-aware answers to client inquiries or professionalize draft messages before they are sent.
*   **Shared Repository:** Role-based file sharing with restricted client views.

### 7. ⏰ TimeBlock Pro (Time & Schedule Management)
*   **Focus Mode:** A built-in Pomodoro productivity timer to eliminate distractions during "Deep Work."
*   **AI Smart Schedules:** Let the Copilot recommend focus blocks and task allocations based on historical team capacity and deadlines.
*   **Boundary Enforcement:** Set office hours with automated after-hours responders.

### 8. 🏗 FreebieFactory (Resource Library)
*   **Internal IP Vault:** Store contract templates, swipe files, and standard SOPs.
*   **Discovery Questionnaires:** Built-in tool with AI-powered questionnaire optimization to extract deep insights from new clients.
*   **Asset Marketplace:** Buy/Sell premium templates directly within the plugin.

### 9. 🤖 AutoPilot (Automation Center)
*   **Event Triggers:** Custom "If-This-Then-That" logic (e.g., *If Invoice Paid -> Start Project*).
*   **External Webhooks:** Native support for Zapier, Make.com, and Slack.
*   **AI CRM SOP Generation:** Instantly generate detailed, step-by-step task briefs and SOP instructions inside the Admin CRM dashboard.
*   **Daily Briefing:** Automated email summary for admins covering urgent tasks, new leads, and AI summaries of hot social interactions.

### 10. ❤️ BurnoutGuard (Health & Sustainability)
*   **Stress Level Tracking:** Team members log workload capacity and stress levels.
*   **Vacation Planner:** Centralized OOO calendar to prevent over-allocation.
*   **Referral Hub:** Seamlessly delegate overflow work to trusted external partners.

---

## 🤖 The Unified AI Copilot Engine

Agency Nexus includes an enterprise-grade, deeply integrated **AI Copilot Engine** (`Agency_Nexus_AI_Copilot` in `includes/class-ai-copilot.php`) that acts as a cognitive booster across all ten modules.

```
              +------------------------------------------+
              |          Agency Nexus Frontend           |
              |  (✨ AI Improve / 🪄 Generate with AI)   |
              +------------------------------------------+
                                    |
                                    | (AJAX Request: wp_ajax_an_ai_improve_content)
                                    v
              +------------------------------------------+
              |    Agency_Nexus_Admin_Dashboard::        |
              |       handle_ai_improve_content()        |
              +------------------------------------------+
                                    |
                                    v
              +------------------------------------------+
              |       Agency_Nexus_AI_Copilot            |
              |     (Settings: an_ai_provider, etc.)     |
              +------------------------------------------+
                /         |                \
               /          |                 \
              v           v                  v             v
        +---------+  +----------+     +------------+  +------------+
        | OpenAI  |  |  Google  |     | Anthropic  |  | Local Mock |
        | (GPT-4) |  | (Gemini) |     |  (Claude)  |  |  Fallback  |
        +---------+  +----------+     +------------+  +------------+
```

### ⚙️ Core Technical Specifications
*   **Multi-Provider Support:** Interfaces natively with **OpenAI (ChatGPT)**, **Google Gemini**, and **Anthropic Claude** using WordPress options:
    *   `an_ai_enabled` (bool) - Toggle the AI suite globally.
    *   `an_ai_provider` (string) - Choose active provider (`openai`, `gemini`, `claude`, or `local`).
    *   `an_openai_key`, `an_gemini_key`, `an_claude_key` - Respective API keys.
    *   `an_ai_model` (string) - Active LLM model configuration.
*   **Seamless Client-Side UI:** Front-end buttons and actions like **✨ AI Improve** and **🪄 Generate with AI Copilot** are rendered unconditionally. If external API keys are missing or unconfigured, the system automatically falls back to a built-in context-aware **Local CoPilot engine** that runs smoothly without external network dependency.
*   **Unified AJAX Handler:** All AI actions route through a secure, non-blocking admin AJAX action `wp_ajax_an_ai_improve_content`, processed by `Agency_Nexus_Admin_Dashboard::handle_ai_improve_content` with robust WP_Error safeguards and exhaustive error logging.
*   **Local Mock Fallback:** Ideal for local offline development. Developers can run full AI simulations without incurring API costs.

---

## 🛠 Advanced Project Management

Agency Nexus doesn't just list tasks; it manages the execution flow:
*   **Task Dependencies:** Map relationships between tasks (e.g., *Task B cannot start until Task A is done*).
*   **Detailed Briefs:** Every task supports rich text instructions, internal attachments, and **AI-generated SOP briefs**.
*   **Buffer Management:** Set "Risk Buffers" in project settings to account for scope creep.
*   **Audit Logs:** Track every administrative action for security and accountability.

---

## 📦 Installation & Setup

1.  **Requirement:** WordPress 5.8+ and PHP 7.4+ (Fully compatible with PHP 8.1+ & PHP 8.3+).
2.  **Upload:** Place the `agency-nexus` folder in your `/wp-content/plugins/` directory.
3.  **Activate:** Go to **Plugins > Installed Plugins** and click 'Activate' on Agency Nexus.
4.  **License:** Navigate to **Nexus > Licensing** and enter a key (e.g., `PRO-1234`) to unlock modules.
5.  **Configure AI:** Navigate to **Nexus > Settings > AI Copilot Configuration** to enable AI, enter keys, and select models.
6.  **Demo:** Use the **Add Best Sample Content** button in Settings to instantly see the system in action.

---

## 💰 Licensing Tiers
*   **Starter (Free):** Core CRM, Project Management, and Task Tracking.
*   **Pro ($199/yr):** ROI Intelligence, Autopilot Rules, Messaging, Lead Scoring, and standard AI Copilot assists.
*   **Agency VIP ($999/lifetime):** Full White-Labeling, Unlimited Sites, Priority Support, BurnoutGuard, and unrestricted AI capabilities with advanced model features.

---

## 📂 Documentation & Growth Assets

This repository is a complete business-in-a-box. Refer to these files for success:
*   **User Manual:** `USER_MANUAL.md` - The definitive guide for you, your team, and your clients.
*   **Operating Procedures:** `TUTORIAL_AND_SOP.md` - Operational workflows for Admins and Team members with integrated AI prompts.
*   **Marketing Strategy:** `MARKETING_STRATEGY.md` - How to position and sell the "AI-Enhanced Agency OS."
*   **Copywriting Assets:** `SALES_LETTER_AND_LANDING_PAGE.md` - High-converting web copy emphasizing the Frankenstein Stack vs. Nexus.
*   **Video Resources:** `SALES_VIDEO_SCRIPT.md` and `VIDEO_SCRIPT.md` (Technical & AI Walkthrough).
*   **Sales Scripts:** `OUTREACH_SCRIPTS.md` and `7_DAY_EMAIL_SERIES.md`.

---

## 🛠 Developer & Customization Guide

Agency Nexus is built with a highly modular architecture, making it easy for developers to extend its functionality.

### 🔌 Modular Hooks
*   `agency_nexus_project_status_updated`: Fired whenever a project status changes. Ideal for custom integrations.
*   `agency_nexus_dashboard_widgets`: Action hook to add custom widgets to the main Nexus dashboard.

### 🌐 REST API Endpoints
The plugin exposes several endpoints for external integrations (e.g., mobile apps or custom lead forms):
*   `POST /wp-json/agency-nexus/v1/leads/capture`: Programmatically ingest leads from any source.
*   `GET /wp-json/agency-nexus/v1/projects`: Retrieve authorized project data for the current user.

### 🧪 Database Schema
All custom tables are prefixed with `an_` (e.g., `wp_an_projects`, `wp_an_tasks`). Refer to `includes/class-db-manager.php` for the full schema definitions.

---
**Agency Nexus.** *Stop managing. Start scaling.*
