=== Sitemu Writer ===
Tags: ai writer, openrouter, automated content, seo, wordpress
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 2.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automated AI content writer integrated with Open Router for high-quality article generation.

== Description ==

Sitemu Writer is a powerful AI-driven content generation plugin for WordPress. It leverages the Open Router API to generate comprehensive, SEO-friendly articles automatically.

**Key Features:**
*   **Smart Product Manager (New in v2.3.0):** Add unlimited products and let AI automatically select the most relevant one to promote in each article.
*   **Soft Selling CTA:** AI integrates product recommendations naturally within the content flow.
*   **Smart Internal Linking:** Automatically links to your random existing posts to boost SEO and pageviews.
*   **Open Router Integration:** Connect to top-tier AI models via Open Router (e.g., Mistral, GPT-4, Claude).
*   **Automatic Article Generation:** Generate articles based on keywords and topics stored in your database.
*   **Default Featured Image:** Select a default image from your Media Library to be used for all generated articles.
*   **SEO Optimized Prompting:** Uses advanced, role-based prompting to ensure content is structured, engaging, and SEO-ready.
*   **Yoast SEO Support:** Automatically populates Yoast SEO meta titles and descriptions.
*   **Scheduled Posting:** (Optional) Set up schedules to automatically generate and publish content.

== Installation ==

1.  Upload the `sitemu-writer` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Sitemu Writer > Settings** and configure your Open Router API Key.
4.  Select a Default Featured Image and configure your preferred text model.

== Changelog ==

= 2.3.0 =
*   **Feature:** Added Unlimited Product Manager in Settings. Users can now input multiple products.
*   **Feature:** "Smart AI Selection" logic. AI now intelligently picks one relevant product from the list to promote per article.
*   **Optimization:** Random Internal Linking to better distribute link juice to older posts.
*   **Optimization:** Token usage optimization by sending only a random subset of products to the AI prompt.

= 2.2.0 =
*   **Feature:** Added option to select a "Default Featured Image" from the Media Library.
*   **Update:** Switched AI engine integration from Hugging Face to Open Router.
*   **Update:** Improved prompt engineering for better article structure and SEO.
*   **Fix:** Resolved issues with image generation by replacing it with a stable default image selection.

= 2.1.0 =
*   Initial migration steps to Open Router.
*   Updated settings page UI.

= 2.0.1 =
*   Previous version using Hugging Face (Legacy).
