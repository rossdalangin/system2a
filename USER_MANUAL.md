# Agency Nexus User Manual: Master Your Operations

Welcome to **Agency Nexus**, the ultimate Agency Operating System. This manual provides detailed instructions on how to set up, configure, and use every module and capability—including the powerful native **AI Copilot Engine**—to maximize your efficiency, automate administrative overhead, and scale your agency.

---

## 🟢 1. Initial Setup & Global Configuration

Before diving into the modules, ensure your core settings are calibrated:

1.  **Installation:** Activate the plugin. Note that Agency Nexus automatically creates custom database tables prefixed with `an_` to manage its advanced logic.
2.  **Licensing:** Go to **Nexus > Licensing**. Enter your key. This unlocks specific modules based on your tier (Starter, Pro, or VIP).
3.  **Branding:** In **Nexus > Settings**, upload your **Agency Logo**. This logo will automatically appear on all client invoices and proposals (VIP tier).
4.  **SMTP Configuration:** Under the "Email & SMTP" section in Settings, configure your mail server. This is critical for ensuring automated invoices and daily briefings reach their destination.
5.  **Demo Mode:** If you want to see how the system looks when fully operational, click **Add Best Sample Content** at the bottom of the Settings page. This populates your CRM with realistic leads, clients, invoices, and content matrices.

---

## 📈 2. Sales & CRM (EngageTrack)

**EngageTrack** turns your agency into a lead-generation machine, utilizing advanced analytics and AI-powered categorization.

*   **Lead Capture:** Use the **Generate Embed Code** button on the Leads page. Copy the HTML to your marketing site. Leads will flow directly into your dashboard, preserving UTM campaign parameters for ROI calculation.
*   **Lead Scoring & AI Summarization:** The system automatically scores prospects (0-100) based on potential value, budget, and source. For every lead captured, the **AI Copilot** parses the submission text, generating an executive brief summarizing their pain points and giving your sales reps custom conversion angles.
*   **Social Hub with AI Sentiment Analysis:** Link your Instagram, LinkedIn, X, and Facebook handles in **Social Settings**. The dashboard aggregates engagement and feeds incoming messages through the AI Copilot to analyze sentiment and flag "Priority Interactions" (e.g., highly negative queries or urgent requests) for immediate reply.
*   **Conversion:** When a prospect says "Yes," click **Convert to Client**. This creates a Client record and a WordPress 'Subscriber' user account simultaneously, firing off an automated welcome sequence.

---

## 🤝 3. Onboarding & Deals (SmartOnboard)

Streamline the transition from "Lead" to "Active Project" using interactive configuration tools and generative rewriting.

*   **Interactive Scope Builder:** Select a base service (SEO, Design, etc.) and toggle addons. The dynamic calculator updates the budget in real-time.
    *   *Sample Scope:* "High-Growth SEO" ($2,500 base) + "Backlink Outreach" ($500 addon) + "Technical Audit" ($750 addon) = **$3,750 Total**.
*   **Proposals & AI Rewriter:** After building a scope, generate a Proposal. Before sending, use the **✨ AI Rewrite** button. The AI Copilot takes the raw scope list and draft terms, instantly turning them into professional, compelling legal/project deliverables designed to drive higher sign-off conversion. Your client can view this on the frontend, review the terms, and provide a digital signature.
*   **Project Kickoff:** Once a proposal is signed, the system can automatically create an active Project in the database (via AutoPilot rules), notifying your team.

---

## 🚀 4. Delivery & Execution (Project Management)

The engine that drives your agency's fulfillment and team collaboration.

*   **Task Management & AI Briefs:** Add tasks to any project. Use the **AI SOP Generator** (powered by the Copilot) to generate a complete, step-by-step task brief and Standard Operating Procedure for that specific task type, ensuring your staff knows exactly how to execute without manual training.
*   **Dependencies:** Link tasks using the "Depends On" selector. This prevents "Task B" from being marked as completed before "Task A" is finalized, locking down delivery hygiene.
*   **Buffer Management:** Set **Buffer Days** in Project settings. This adds a visual safety margin to your Gantt charts to protect against unexpected delays and overruns.
*   **Time Tracking:** Team members log hours against specific tasks. Admins can view and edit these entries to ensure labor costs are accurate and compare them against estimated thresholds.

---

## ✍️ 5. Content Operations (ContentMatrix)

Scale your content engine and build topical authority using intelligent planning tools.

*   **Pillar Architect:** Plan content in clusters. Mark high-level pieces as **Pillars** and link supporting cluster content to them.
    *   *Sample Pillar Map:* **Pillar:** "Agency Operations" -> **Clusters:** "Client Onboarding SOP", "Reducing Tool Fatigue", "Scaling Project Margins".
*   **Forecasting & SEO Keyword Gaps:** Each content item includes an engagement prediction. The **AI Copilot** parses your primary URLs and identifies topical gaps, automatically recommending high-ranking SEO keyword clusters to target next.
*   **Visual Calendar:** Drag and drop content items to reschedule. Syncs automatically with your team's workload.
*   **Batch Automation & Content Polish:** Use the Batch Automation tool to generate 10+ draft titles and skeletons for a project in a single click. Every draft features unconditional **✨ AI Improve** and **🪄 Polish** links to refine titles, SEO descriptions, and intro copy on the fly.

---

## ✅ 6. Client Sign-off (ApprovalFlow)

Eliminate "Email Ping-Pong" during the review process with side-by-side versioning.

*   **Draft System:** Move content through stages (Idea, Draft, Review, Approved).
*   **Comparison Engine:** View version history side-by-side to see exactly what changed between revisions.
*   **AI Content Refiner:** From the client approval view, if a client requests modifications, staff can highlight text and click **✨ AI Improve** to instantly apply adjustments, grammar fixes, or tone changes requested by the client.
*   **Client Portal:** Clients see a simplified view of items awaiting their signature. They can approve or request changes with a single click, automating notifications.

---

## 💰 7. Financials & ROI (MoneyFlow)

Know exactly how much profit you're making on every project, with automated collections.

*   **True Profit Formula:** The system calculates: `Project Budget - (Team Hours * Hourly Rate) - Hard Expenses`.
*   **Invoicing:** Generate PDF-style invoices. You can add one-click **Late Fees (5%)** to any overdue invoice.
*   **AI Invoice Reminders:** Click the **🪄 Draft AI Reminder** button next to overdue invoices. The AI Copilot analyzes the client's past history, invoice details, and the length of the delay to draft a perfectly balanced, personalized payment reminder email.
*   **Online Payments:** Clients can pay their invoices directly via Stripe or PayPal from the printable invoice view.
*   **Financial Reports:** View 6-month P&L trends and automated tax estimations (25%) in the Reports dashboard.

---

## 💬 8. Client Collaboration (ClientSync)

Secure, branded communication that stays inside your ecosystem.

*   **Communication Hub:** A real-time chat interface for all client interactions. No more lost emails.
*   **AI Chat Replies & Polishing:** Next to the message editor, team members have access to the **✨ AI Polish** and **🪄 AI Suggest** assistants. The AI Copilot can read the thread history to suggest replies or instantly professionalize a rough draft written by a staff member before sending.
*   **Shared Files:** A dedicated repository where you can upload deliverables and clients can upload assets (brand guides, etc.).
*   **Meeting Scheduler:** Integrated tool to book strategy sessions without leaving WordPress.

---

## ⏰ 9. Productivity (TimeBlock Pro)

Maximized focus for you and your team, powered by intelligent capacity algorithms.

*   **Focus Mode:** Launch the Pomodoro timer during deep work sessions. The system can log these as "Focus Sprints" in your productivity report.
*   **AI Smart Schedules:** Click **🪄 Generate Smart Schedule** in the dashboard. The AI Copilot reads your team's current tasks, deadlines, and logged capacity trends to generate an optimized focus schedule, highlighting optimal focus blocks and predicting potential capacity bottlenecks.
*   **Boundaries:** Set your "Communication Hours" in Settings. The system will automatically notify clients that you are away if they message you after hours.
*   **Capacity Reports:** See which team members are over-leveraged and who has room for new tasks.

---

## 🏗 10. Assets & IP (FreebieFactory)

Turn your agency's knowledge into a scalable asset.

*   **Internal Library:** Store your best contract clauses, email swipes, and SOP templates.
*   **Questionnaire Builder & AI Optimizer:** Create discovery forms to send to clients during onboarding. Use the **✨ AI Optimize Questionnaire** function to rewrite questions for clarity, ensuring clients provide the exact context required for delivery.
*   **Marketplace:** Sell your frameworks or buy premium templates from the Agency Nexus community.

---

## 🤖 11. Global AI Copilot Settings & Architecture

The **AI Copilot Engine** is a core engine that drives automation across the ecosystem. Admins can fully customize its settings under **Nexus > Settings > AI Copilot**.

### ⚙️ Setting Options
*   **Enable AI Suite (`an_ai_enabled`):** Global toggle to turn the AI assist buttons and automated summaries on or off.
*   **Active Provider (`an_ai_provider`):** Choose the LLM provider you wish to route requests to. Supported providers are:
    *   `openai` - Uses OpenAI's advanced GPT models.
    *   `gemini` - Uses Google's fast and context-heavy Gemini models.
    *   `claude` - Uses Anthropic's highly analytical Claude models.
    *   `local` - Built-in context-aware Local CoPilot mock engine (ideal for local development or when API keys are disabled/unconfigured).
*   **OpenAI Key (`an_openai_key`):** Enter your OpenAI API key (format: `sk-...`).
*   **Gemini Key (`an_gemini_key`):** Enter your Google Gemini API key.
*   **Claude Key (`an_claude_key`):** Enter your Anthropic Claude API key.
*   **Active Model (`an_ai_model`):** Define the model parameter sent to the provider API (e.g., `gpt-4o`, `claude-3-5-sonnet`, `gemini-1.5-pro`).

### 🔍 Behind the Scenes: AJAX Architecture
Every AI button (such as "✨ AI Improve" and "🪄 Generate with AI Copilot") makes a non-blocking request to the central WordPress AJAX action `wp_ajax_an_ai_improve_content`. This is routed to `Agency_Nexus_Admin_Dashboard::handle_ai_improve_content`, which performs security and capability checks, selects the active provider based on options, and securely interacts with the chosen API.

If an API key is missing or calls fail, the backend degrades gracefully, logging detailed debug data to `wp-content/debug.log` while seamlessly utilizing the context-aware **Local CoPilot engine** to prevent frontend disruptions.

---

## 🤖 12. Automation (AutoPilot)

Put your agency on cruise control.

*   **Trigger Rules:** Create "If-This-Then-That" logic using JSON conditions.
    *   *Sample Rule:* `{"trigger": "project_completed", "condition": {"budget_min": 5000}, "action": "trigger_zapier"}`.
*   **Webhooks:** Connect to Zapier or Slack to push updates to your favorite external tools.
*   **Daily Briefing:** Every morning, the system sends an email to the admin with:
    1.  All tasks overdue or due today.
    2.  Leads captured in the last 24h.
    3.  Urgent social interactions and AI sentiment alerts.

---

## 🔒 13. Security & Compliance

*   **Encryption at Rest:** Enable "Encrypt internal notes" in **Security & Privacy**. This protects sensitive client data using AES-256-CBC encryption.
*   **GDPR Compliance:** Use the **Data Portability** tool to export a client's entire history (projects, invoices, messages) into a single JSON file.
*   **Audit Logging:** Monitor administrative actions to ensure the integrity of your agency's data.

---

## ❓ Frequently Asked Questions (FAQ)

**Q: Do I need an OpenAI/Gemini/Claude account to use the AI features?**
A: No! While connecting your own API keys enables advanced LLM intelligence, Agency Nexus includes a context-aware **Local CoPilot engine** that runs unconditionally by default when no external keys are configured, allowing you to use content polishing and smart templates right out of the box.

**Q: Can I use Agency Nexus on a multisite network?**
A: Yes. The Agency VIP tier supports unlimited site activations across your entire network.

**Q: Does the plugin slow down my site?**
A: No. Agency Nexus is built with performance in mind. It uses custom tables, lazy-loading for data, and optimized AJAX handlers, ensuring your frontend marketing pages remain lightning fast.

**Q: How secure is my client data?**
A: Extremely. We use AES-256-CBC encryption for sensitive notes and descriptions. Only authorized users with the correct roles can access client data.

---

## 🛠 Troubleshooting & Support

1.  **Invoices Not Sending:** Ensure your SMTP settings are correct in **Nexus > Settings**. Send a test email to verify connectivity.
2.  **Shortcode Not Rendering:** Check your license tier in **Nexus > Licensing**. Some shortcodes (like the Marketplace) require a Pro or VIP key.
3.  **AI Key Authorization Errors:** Ensure you have configured the correct key format in Settings. Check `wp-content/debug.log` for raw API responses from OpenAI, Gemini, or Claude.
4.  **Media Upload Errors:** If you cannot upload files to the Shared Repository, ensure the `upload_files` capability is granted to your client user role.

**Need Priority Support?** Contact your Agency VIP account manager directly through the support portal.
